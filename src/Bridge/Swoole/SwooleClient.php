<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Bridge\Swoole;

use Cake\Collection\Collection;
use Cake\Core\Configure;
use Cake\Http\Cookie\CookieInterface;
use Cake\Http\Cookie\SameSiteEnum;
use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Closure;
use Crustum\Stimulus\Bridge\Swoole\Actions\ConvertSwooleRequestToCakeRequest;
use Crustum\Stimulus\Contracts\Client;
use Crustum\Stimulus\Contracts\ServesStaticFiles;
use Crustum\Stimulus\Http\MimeType;
use Crustum\Stimulus\Http\RequestContext;
use Crustum\Stimulus\Http\StimulusResponse;
use Crustum\Stimulus\StimulusManager;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * Swoole server bridge client.
 *
 * Swoole requests marshal into requests through the convert action,
 * worker PSR-7 responses stream
 * back through the Swoole response, and the debug flag reads the
 * configuration. Symfony response pieces
 * map onto PSR-7: headers iterate from `getHeaders`, cookies parse from
 * `Set-Cookie` headers, and bodies (including file and streamed bodies) flow
 * through chunked writes. Every Swoole response stays untyped and resolves
 * behind the extension guard, so the file loads where the extension is
 * absent; cookie partitioning additionally honors `SWOOLE_VERSION_ID`.
 */
class SwooleClient implements Client, ServesStaticFiles
{
    /**
     * HTTP reason phrases for status codes beyond the Swoole defaults.
     *
     * @var array<int, string>
     */
    protected const STATUS_CODE_REASONS = [
        419 => 'Page Expired',
        425 => 'Too Early',
        431 => 'Request Header Fields Too Large',
        451 => 'Unavailable For Legal Reasons',
    ];

    /**
     * Creates a new Swoole client.
     *
     * @param int $chunkSize Maximum response body bytes sent in one chunk.
     */
    public function __construct(protected int $chunkSize = 1048576)
    {
    }

    /**
     * Marshals the given request context into a request.
     *
     * @param \Crustum\Stimulus\Http\RequestContext $context Raw request context carrying the Swoole request.
     * @return array<int, mixed> Marshalled request and context pair.
     */
    public function marshalRequest(RequestContext $context): array
    {
        return [
            (new ConvertSwooleRequestToCakeRequest())(
                $context->data['swooleRequest'] ?? null,
                PHP_SAPI,
            ),
            $context,
        ];
    }

    /**
     * Determines if the request can be served as a static file.
     *
     * @param \Cake\Http\ServerRequest $request Incoming request.
     * @param \Crustum\Stimulus\Http\RequestContext $context Raw request context from the server.
     * @return bool Whether the request maps to a servable static file.
     */
    public function canServeRequestAsStaticFile(ServerRequest $request, RequestContext $context): bool
    {
        $stimulusConfig = $context->data['stimulusConfig'] ?? [];

        if (
            array_key_exists('serve_static_files', $stimulusConfig) &&
            !$stimulusConfig['serve_static_files']
        ) {
            return false;
        }

        $publicPath = $context->data['publicPath'] ?? false;
        $path = $this->requestPath($request);

        if (!$publicPath || $path === '/') {
            return false;
        }

        $pathToFile = realpath($publicPath . '/' . ltrim($path, '/'));

        if ($this->isValidFileWithinSymlink($request, (string)$publicPath, $pathToFile)) {
            $pathToFile = $publicPath . '/' . ltrim($path, '/');
        }

        return $this->fileIsServable((string)$publicPath, $pathToFile);
    }

    /**
     * Determines if the request is for a valid static file within a symlink.
     *
     * @param \Cake\Http\ServerRequest $request Incoming request.
     * @param string $publicPath Public directory serving static files.
     * @param string|false $pathToFile Resolved file path, or false when unresolvable.
     * @return bool Whether the file resolves through a symlink directory.
     */
    protected function isValidFileWithinSymlink(
        ServerRequest $request,
        string $publicPath,
        string|false $pathToFile,
    ): bool {
        if (!is_string($pathToFile)) {
            return false;
        }

        $pathAfterSymlink = $this->pathAfterSymlink($publicPath, ltrim($this->requestPath($request), '/'));

        return $pathAfterSymlink !== false && str_ends_with($pathToFile, $pathAfterSymlink);
    }

    /**
     * Returns the path after the symlink when the public file sits in a symlinked directory.
     *
     * Iterates imperatively, shifting path segments until it finds the
     * symlink instead of collecting them.
     *
     * @param string $publicPath Public directory serving static files.
     * @param string $path Request path without the leading slash.
     * @return string|false Path after the symlink, or false when no symlink matches.
     */
    protected function pathAfterSymlink(string $publicPath, string $path): string|false
    {
        $directories = explode('/', $path);

        while ($directory = array_shift($directories)) {
            $publicPath .= '/' . $directory;

            if (is_link($publicPath)) {
                return implode('/', $directories);
            }
        }

        return false;
    }

    /**
     * Determines if the given file is servable.
     *
     * @param string $publicPath Public directory serving static files.
     * @param string|false $pathToFile Candidate file path, or false when unresolvable.
     * @return bool Whether the file serves as a static asset.
     */
    protected function fileIsServable(string $publicPath, string|false $pathToFile): bool
    {
        return is_string($pathToFile) &&
            $pathToFile !== '' &&
            !in_array(pathinfo($pathToFile, PATHINFO_EXTENSION), ['php', 'htaccess', 'config'], true) &&
            str_starts_with($pathToFile, $publicPath) &&
            is_file($pathToFile);
    }

    /**
     * Serves the static file that was requested.
     *
     * Header patterns match through `fnmatch`.
     *
     * @param \Cake\Http\ServerRequest $request Incoming request.
     * @param \Crustum\Stimulus\Http\RequestContext $context Raw request context from the server.
     * @return void
     */
    public function serveStaticFile(ServerRequest $request, RequestContext $context): void
    {
        $swooleResponse = $context->data['swooleResponse'] ?? null;

        if (
            !is_object($swooleResponse) ||
            !method_exists($swooleResponse, 'status') ||
            !method_exists($swooleResponse, 'header') ||
            !method_exists($swooleResponse, 'sendfile')
        ) {
            return;
        }

        $publicPath = (string)($context->data['publicPath'] ?? '');
        $stimulusConfig = $context->data['stimulusConfig'] ?? [];

        if (!empty($stimulusConfig['static_file_headers'])) {
            $formatHeaders = $this->formatHeaders();

            foreach ($stimulusConfig['static_file_headers'] as $pattern => $headers) {
                if (!fnmatch((string)$pattern, ltrim($this->requestPath($request), '/'))) {
                    continue;
                }

                foreach ($headers as $name => $value) {
                    $swooleResponse->header($name, $value, $formatHeaders);
                }
            }
        }

        $path = realpath($publicPath . '/' . ltrim($this->requestPath($request), '/'));

        $swooleResponse->status(200);
        $swooleResponse->header('Content-Type', MimeType::get(pathinfo($this->requestPath($request), PATHINFO_EXTENSION)));
        $swooleResponse->sendfile(is_string($path) ? $path : '');
    }

    /**
     * Sends the response to the server.
     *
     * @param \Crustum\Stimulus\Http\RequestContext $context Raw request context from the server.
     * @param \Crustum\Stimulus\Http\StimulusResponse $stimulusResponse Worker response envelope.
     * @return void
     */
    public function respond(RequestContext $context, StimulusResponse $stimulusResponse): void
    {
        $swooleResponse = $context->data['swooleResponse'] ?? null;

        $this->sendResponseHeaders($stimulusResponse->response, $swooleResponse);
        $this->sendResponseContent($stimulusResponse, $swooleResponse);
    }

    /**
     * Sends the headers from the worker response to the Swoole response.
     *
     * Adds the date header when missing and translates `Set-Cookie` headers
     * into Swoole cookie calls without using Symfony cookie objects. Responses carry cookies in
     * their cookie collection instead of `Set-Cookie` headers, so pending
     * collection cookies travel through the same Swoole cookie calls.
     *
     * @param \Psr\Http\Message\ResponseInterface $response Worker response.
     * @param mixed $swooleResponse Swoole HTTP response receiving the headers.
     * @return void
     */
    public function sendResponseHeaders(ResponseInterface $response, mixed $swooleResponse): void
    {
        if (
            !is_object($swooleResponse) ||
            !method_exists($swooleResponse, 'status') ||
            !method_exists($swooleResponse, 'header') ||
            !method_exists($swooleResponse, 'cookie')
        ) {
            return;
        }

        $headers = $response->getHeaders();
        $formatHeaders = $this->formatHeaders();

        $hasDate = (new Collection(array_keys($headers)))
            ->map(fn(string $name): string => strtolower($name))
            ->contains('date');

        if (!$hasDate) {
            $headers['Date'] = [gmdate('D, d M Y H:i:s') . ' GMT'];
        }

        (new Collection($headers))
            ->each(function (mixed $values, string $name) use ($swooleResponse, $formatHeaders): void {
                if (strtolower($name) === 'set-cookie') {
                    return;
                }

                foreach ((array)$values as $value) {
                    $swooleResponse->header($name, $value, $formatHeaders);
                }
            });

        $statusCode = $response->getStatusCode();
        $reason = $this->getReasonFromStatusCode($statusCode);

        if ($reason !== null) {
            $swooleResponse->status($statusCode, $reason);
        } else {
            $swooleResponse->status($statusCode);
        }

        (new Collection($this->setCookieHeaders($headers)))
            ->each(function (string $header) use ($swooleResponse): void {
                $swooleResponse->cookie(...$this->cookieParams($header));
            });

        (new Collection($this->cakeCookies($response)))
            ->each(function (CookieInterface $cookie) use ($swooleResponse): void {
                $swooleResponse->cookie(...$this->cakeCookieParams($cookie));
            });
    }

    /**
     * Sends the content from the worker response to the Swoole response.
     *
     * Chunk producers stream through writes while buffered output prepends;
     * large bodies chunk imperatively, offsetting through the content instead
     * of collecting it.
     *
     * @param \Crustum\Stimulus\Http\StimulusResponse $stimulusResponse Worker response envelope.
     * @param mixed $swooleResponse Swoole HTTP response receiving the content.
     * @return void
     */
    protected function sendResponseContent(StimulusResponse $stimulusResponse, mixed $swooleResponse): void
    {
        if (
            !is_object($swooleResponse) ||
            !method_exists($swooleResponse, 'write') ||
            !method_exists($swooleResponse, 'end')
        ) {
            return;
        }

        if ($stimulusResponse->outputBuffer !== null && $stimulusResponse->outputBuffer !== '') {
            $swooleResponse->write($stimulusResponse->outputBuffer);
        }

        if ($stimulusResponse->streamer instanceof Closure) {
            $producer = $stimulusResponse->streamer;

            (new Collection($producer()))
                ->each(fn(mixed $chunk): mixed => $swooleResponse->write((string)$chunk));

            $swooleResponse->end();

            return;
        }

        $content = (string)$stimulusResponse->response->getBody();

        if ($content === '') {
            $swooleResponse->end();

            return;
        }

        if (strlen($content) <= $this->chunkSize || $this->http2Enabled()) {
            $swooleResponse->end($content);

            return;
        }

        $length = strlen($content);

        for ($offset = 0; $offset < $length; $offset += $this->chunkSize) {
            $swooleResponse->write(substr($content, $offset, $this->chunkSize));
        }

        $swooleResponse->end();
    }

    /**
     * Sends an error message to the server.
     *
     * @param \Throwable $e Throwable raised while handling the operation.
     * @param object $app Base application instance.
     * @param \Cake\Http\ServerRequest $request Request that was being handled.
     * @param \Crustum\Stimulus\Http\RequestContext $context Raw request context from the server.
     * @return void
     */
    public function error(Throwable $e, object $app, ServerRequest $request, RequestContext $context): void
    {
        $swooleResponse = $context->data['swooleResponse'] ?? null;

        if (
            !is_object($swooleResponse) ||
            !method_exists($swooleResponse, 'header') ||
            !method_exists($swooleResponse, 'end')
        ) {
            return;
        }

        $swooleResponse->header('Status', '500 Internal Server Error');
        $swooleResponse->header('Content-Type', 'text/plain');

        $swooleResponse->end(
            StimulusManager::formatExceptionForClient($e, (bool)Configure::read('debug')),
        );
    }

    /**
     * Gets the HTTP reason clause for non-standard status codes.
     *
     * @param int $code Response status code.
     * @return string|null Reason phrase for the status code, or null for standard codes.
     */
    protected function getReasonFromStatusCode(int $code): ?string
    {
        if (array_key_exists($code, self::STATUS_CODE_REASONS)) {
            return self::STATUS_CODE_REASONS[$code];
        }

        return null;
    }

    /**
     * Gets the decoded request path for static file resolution.
     *
     * @param \Cake\Http\ServerRequest $request Incoming request.
     * @return string Decoded request path.
     */
    protected function requestPath(ServerRequest $request): string
    {
        $path = rawurldecode($request->getUri()->getPath());

        return $path === '' ? '/' : $path;
    }

    /**
     * Collects the `Set-Cookie` header values from the response headers.
     *
     * Matches header names case-insensitively since PSR-7 implementations
     * vary the stored case.
     *
     * @param array<string, mixed> $headers Response headers.
     * @return list<string> `Set-Cookie` header values.
     */
    protected function setCookieHeaders(array $headers): array
    {
        return (new Collection($headers))
            ->reduce(function (array $cookies, mixed $values, string $name): array {
                if (strtolower($name) !== 'set-cookie') {
                    return $cookies;
                }

                foreach ((array)$values as $value) {
                    $cookies[] = (string)$value;
                }

                return $cookies;
            }, []);
    }

    /**
     * Builds the Swoole cookie call parameters for a `Set-Cookie` value.
     *
     * Empty values send the `deleted` marker. Newer Swoole versions
     * receive the two extra partitioning arguments:
     * `extension_loaded('swoole')` and `SWOOLE_VERSION_ID` guard; OpenSwoole
     * responses take at most 9 arguments, so they stay at the base shape.
     *
     * @param string $header `Set-Cookie` header value.
     * @return list<mixed> Parameters for the Swoole cookie call.
     */
    protected function cookieParams(string $header): array
    {
        $segments = explode(';', $header);
        $pair = explode('=', trim(array_shift($segments)), 2);
        $attributes = [
            'expires' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => false,
            'httponly' => false,
            'samesite' => '',
            'partitioned' => false,
        ];

        (new Collection($segments))
            ->each(function (string $segment) use (&$attributes): void {
                $parts = explode('=', trim($segment), 2);
                $name = strtolower(trim($parts[0]));
                $value = trim($parts[1] ?? '');

                match ($name) {
                    'expires' => $attributes['expires'] = (int)strtotime($value),
                    'max-age' => $attributes['expires'] = time() + (int)$value,
                    'path' => $attributes['path'] = $value,
                    'domain' => $attributes['domain'] = $value,
                    'secure' => $attributes['secure'] = true,
                    'httponly' => $attributes['httponly'] = true,
                    'samesite' => $attributes['samesite'] = $value,
                    'partitioned' => $attributes['partitioned'] = true,
                    default => null,
                };
            });

        $value = trim($pair[1] ?? '');

        $params = [
            trim($pair[0]),
            $value === '' ? 'deleted' : $value,
            $attributes['expires'],
            $attributes['path'] !== '' ? $attributes['path'] : '/',
            $attributes['domain'],
            $attributes['secure'],
            $attributes['httponly'],
            $attributes['samesite'],
        ];

        if (extension_loaded('swoole') && $this->swooleVersionId() >= 60000) {
            $params[] = '';
            $params[] = $attributes['partitioned'];
        }

        return $params;
    }

    /**
     * Collects the response cookies pending on the worker response.
     *
     * Responses carry cookies in their cookie collection instead of
     * `Set-Cookie` headers, so generic header parsing finds none; this
     * traversal exposes them for the Swoole cookie calls. Other responses
     * carry no collection and contribute no cookies here. The traversal stays
     * imperative because the vendor collection discards the list shape the
     * contract promises.
     *
     * @param \Psr\Http\Message\ResponseInterface $response Worker response.
     * @return list<\Cake\Http\Cookie\CookieInterface> Pending cookies.
     */
    protected function cakeCookies(ResponseInterface $response): array
    {
        if (!$response instanceof Response) {
            return [];
        }

        $cookies = [];

        foreach ($response->getCookieCollection() as $cookie) {
            $cookies[] = $cookie;
        }

        return $cookies;
    }

    /**
     * Builds the Swoole cookie call parameters for a cookie.
     *
     * Maps the cookie object onto the Swoole parameter shape: empty values
     * send the `deleted` marker, and the Swoole version guard
     * appends the partitioning arguments like the header-parsed path.
     * Extension hosts always send
     * collection cookies unpartitioned.
     *
     * @param \Cake\Http\Cookie\CookieInterface $cookie Cookie to convert.
     * @return list<mixed> Parameters for the Swoole cookie call.
     */
    protected function cakeCookieParams(CookieInterface $cookie): array
    {
        $value = $cookie->getValue();

        $value = is_array($value) ? $cookie->getScalarValue() : $value;

        $sameSite = $cookie->getSameSite();

        $params = [
            $cookie->getName(),
            $value === '' ? 'deleted' : $value,
            $cookie->getExpiresTimestamp() ?? 0,
            $cookie->getPath() !== '' ? $cookie->getPath() : '/',
            $cookie->getDomain(),
            $cookie->isSecure(),
            $cookie->isHttpOnly(),
            $sameSite instanceof SameSiteEnum ? $sameSite->value : '',
        ];

        if (extension_loaded('swoole') && $this->swooleVersionId() >= 60000) {
            $params[] = '';
            $params[] = false;
        }

        return $params;
    }

    /**
     * Gets the Swoole extension version identifier.
     *
     * Resolves the version constant by name with a zero fallback like the
     * Swoole server command, so the lookup never fatals where the extension
     * is absent.
     *
     * @return int Swoole version identifier, or zero without the extension.
     */
    protected function swooleVersionId(): int
    {
        return defined('SWOOLE_VERSION_ID') ? constant('SWOOLE_VERSION_ID') : 0;
    }

    /**
     * Determines whether Swoole response headers keep their case.
     *
     * @return bool Whether header names pass through unformatted.
     */
    protected function formatHeaders(): bool
    {
        return (bool)Configure::read('Stimulus.swoole.format_headers', true);
    }

    /**
     * Determines whether the HTTP/2 protocol option is enabled.
     *
     * Bypasses chunking for HTTP/2 connections.
     *
     * @return bool Whether the HTTP/2 protocol option is enabled.
     */
    protected function http2Enabled(): bool
    {
        return (bool)Configure::read('Stimulus.swoole.options.open_http2_protocol', false);
    }
}
