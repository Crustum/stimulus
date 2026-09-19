<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Bridge\Swoole\Actions;

use Cake\Collection\Collection;
use Cake\Core\Configure;
use Cake\Http\ServerRequest;

/**
 * Converts a Swoole request into a request.
 */
class ConvertSwooleRequestToCakeRequest
{
    /**
     * Converts the given Swoole request into a request.
     *
     * Parses url-encoded bodies of methods without native form support back
     * into post data. `REQUEST_URI` is rebuilt
     * from the path-only url option, dropping the query string, so the full
     * Swoole request URI travels back through the environment. Base and
     * webroot resolve explicitly so helper-generated asset URLs stay
     * root-absolute instead of page-relative.
     *
     * @param mixed $swooleRequest Swoole HTTP request carrying server, header, and body payloads.
     * @param string $phpSapi PHP server API identifying the development server quirk path.
     * @return \Cake\Http\ServerRequest Request for the worker sandbox.
     */
    public function __invoke(mixed $swooleRequest, string $phpSapi): ServerRequest
    {
        $serverVariables = $this->prepareServerVariables(
            $this->property($swooleRequest, 'server'),
            $this->property($swooleRequest, 'header'),
            $phpSapi,
        );

        $post = $this->property($swooleRequest, 'post');

        if (
            str_starts_with((string)($serverVariables['CONTENT_TYPE'] ?? ''), 'application/x-www-form-urlencoded') &&
            in_array(strtoupper((string)($serverVariables['REQUEST_METHOD'] ?? 'GET')), ['PUT', 'PATCH', 'DELETE'], true)
        ) {
            parse_str($this->rawContent($swooleRequest), $post);
        }

        $requestUrl = $this->requestUrl($serverVariables);

        [$base, $webroot] = $this->baseAndWebroot();

        $request = new ServerRequest([
            'query' => $this->property($swooleRequest, 'get'),
            'post' => $post,
            'files' => $this->property($swooleRequest, 'files'),
            'cookies' => $this->property($swooleRequest, 'cookie'),
            'environment' => $serverVariables,
            'url' => $requestUrl,
            'base' => $base,
            'webroot' => $webroot,
            'input' => $this->rawContent($swooleRequest),
        ]);

        return $request->withEnv('REQUEST_URI', $requestUrl);
    }

    /**
     * Parses the server variables and headers into `$_SERVER` variables.
     *
     * Iterates imperatively, looping over the server variables instead of
     * collecting them.
     *
     * @param array<string, mixed> $server Swoole server variables.
     * @param array<string, mixed> $headers Swoole request headers.
     * @param string $phpSapi PHP server API identifying the development server quirk path.
     * @return array<string, mixed> Environment variables for the request.
     */
    protected function prepareServerVariables(array $server, array $headers, string $phpSapi): array
    {
        $results = [];

        foreach ($server as $key => $value) {
            $results[strtoupper((string)$key)] = $value;
        }

        $results = array_merge(
            $results,
            $this->formatHttpHeadersIntoServerVariables($headers),
        );

        if (
            isset($results['REQUEST_URI'], $results['QUERY_STRING']) &&
            (string)$results['QUERY_STRING'] !== '' &&
            !str_contains((string)$results['REQUEST_URI'], '?')
        ) {
            $results['REQUEST_URI'] .= '?' . $results['QUERY_STRING'];
        }

        return $phpSapi === 'cli-server'
            ? $this->correctHeadersSetIncorrectlyByPhpDevServer($results)
            : $results;
    }

    /**
     * Formats the given HTTP headers into `$_SERVER` variables.
     *
     * Maps the header entries through a collection pipeline.
     *
     * @param array<string, mixed> $headers Swoole request headers.
     * @return array<string, mixed> Environment variables for the header entries.
     */
    protected function formatHttpHeadersIntoServerVariables(array $headers): array
    {
        return (new Collection($headers))
            ->map(function (mixed $value, string|int $key): array {
                $normalized = strtoupper(str_replace('-', '_', (string)$key));

                if (!in_array($normalized, ['HTTPS', 'REMOTE_ADDR', 'SERVER_PORT'], true)) {
                    $normalized = 'HTTP_' . $normalized;
                }

                return [$normalized, $value];
            })
            ->reduce(function (array $results, array $pair): array {
                [$key, $value] = $pair;

                $results[$key] = $value;

                return $results;
            }, []);
    }

    /**
     * Corrects headers set incorrectly by the built-in PHP development server.
     *
     * @param array<string, mixed> $headers Environment variables to correct.
     * @return array<string, mixed> Corrected environment variables.
     */
    protected function correctHeadersSetIncorrectlyByPhpDevServer(array $headers): array
    {
        if (array_key_exists('HTTP_CONTENT_LENGTH', $headers)) {
            $headers['CONTENT_LENGTH'] = $headers['HTTP_CONTENT_LENGTH'];
        }

        if (array_key_exists('HTTP_CONTENT_TYPE', $headers)) {
            $headers['CONTENT_TYPE'] = $headers['HTTP_CONTENT_TYPE'];
        }

        return $headers;
    }

    /**
     * Resolves the base path and webroot for the converted request.
     *
     * An explicit `App.base` configuration wins; otherwise the Swoole server
     * owns the domain root, so the base stays empty and the webroot is the
     * root slash. Without these the request carries empty attributes and
     * every helper-generated asset URL comes out page-relative.
     *
     * @return array{0: string, 1: string} Base path and webroot pair.
     */
    protected function baseAndWebroot(): array
    {
        $base = Configure::read('App.base');

        if (is_string($base) && $base !== '') {
            return [$base, rtrim($base, '/') . '/'];
        }

        return ['', '/'];
    }

    /**
     * Builds the request URL from the prepared server variables.
     *
     * @param array<string, mixed> $serverVariables Prepared environment variables.
     * @return string Path and query string identifying the requested resource.
     */
    protected function requestUrl(array $serverVariables): string
    {
        $requestUri = (string)($serverVariables['REQUEST_URI'] ?? '/');

        return $requestUri === '' ? '/' : $requestUri;
    }

    /**
     * Reads an array property from the Swoole request.
     *
     * Falls back to an empty map when the payload is missing, so anonymous
     * fakes in extension-gated tests only declare the parts they cover.
     *
     * @param mixed $swooleRequest Swoole HTTP request carrying the payload.
     * @param string $property Payload property name.
     * @return array<string, mixed> Payload entries.
     */
    protected function property(mixed $swooleRequest, string $property): array
    {
        if (!is_object($swooleRequest) || !isset($swooleRequest->{$property})) {
            return [];
        }

        $value = $swooleRequest->{$property};

        return is_array($value) ? $value : [];
    }

    /**
     * Reads the raw request body from the Swoole request.
     *
     * @param mixed $swooleRequest Swoole HTTP request carrying the body.
     * @return string Raw request body.
     */
    protected function rawContent(mixed $swooleRequest): string
    {
        if (!is_object($swooleRequest) || !method_exists($swooleRequest, 'rawContent')) {
            return '';
        }

        return (string)$swooleRequest->rawContent();
    }
}
