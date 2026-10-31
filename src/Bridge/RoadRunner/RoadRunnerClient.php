<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Bridge\RoadRunner;

use Cake\Collection\Collection;
use Cake\Core\Configure;
use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Closure;
use Crustum\Stimulus\Contracts\Client;
use Crustum\Stimulus\Contracts\StoppableClient;
use Crustum\Stimulus\Http\RequestContext;
use Crustum\Stimulus\Http\StimulusResponse;
use Generator;
use InvalidArgumentException;
use Laminas\Diactoros\Stream;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Spiral\RoadRunner\Http\Exception\StreamStoppedException;
use Spiral\RoadRunner\Http\PSR7Worker;
use Throwable;

/**
 * RoadRunner server bridge client.
 *
 * PSR-7 requests from the RoadRunner worker are marshalled into requests, worker responses are already
 * PSR-7 so they are sent back directly, and the debug flag comes from the
 * configuration.
 */
class RoadRunnerClient implements Client, StoppableClient
{
    /**
     * Creates a new RoadRunner client.
     *
     * @param \Spiral\RoadRunner\Http\PSR7Worker $client RoadRunner PSR-7 worker handling the server connection.
     */
    public function __construct(protected PSR7Worker $client)
    {
    }

    /**
     * Marshals the given request context into a request.
     *
     * @param \Crustum\Stimulus\Http\RequestContext $context Raw request context carrying the PSR-7 request.
     * @return array<int, mixed> Marshalled request and context pair.
     * @throws \InvalidArgumentException When the context carries no PSR-7 server request.
     */
    public function marshalRequest(RequestContext $context): array
    {
        $data = $context->data;
        $psrRequest = $data['psr7Request'] ?? null;

        if (! $psrRequest instanceof ServerRequestInterface) {
            throw new InvalidArgumentException('Request context does not contain a PSR-7 server request.');
        }

        return [
            $this->toCakeRequest($psrRequest),
            $context,
        ];
    }

    /**
     * Sends the response to the server.
     *
     * Envelopes carrying a chunk producer stream through the HTTP worker,
     * ending quietly when the worker reports a stopped stream; every other
     * response is sent buffered. Buffered output is prepended to non-streamed
     * responses only. Pending response cookies join every response as `Set-Cookie`
     * headers first, since the cookie collection never reaches the server on its own.
     *
     * @param \Crustum\Stimulus\Http\RequestContext $context Raw request context from the server.
     * @param \Crustum\Stimulus\Http\StimulusResponse $stimulusResponse Worker response envelope.
     * @return void
     */
    public function respond(RequestContext $context, StimulusResponse $stimulusResponse): void
    {
        $response = $this->withWorkerCookies($stimulusResponse->response);

        if ($stimulusResponse->streamer instanceof Closure) {
            $producer = $stimulusResponse->streamer;

            $this->client->getHttpWorker()->respond(
                $response->getStatusCode(),
                static::endStreamWhenStopped($producer()),
                $response->getHeaders(),
            );

            return;
        }

        if ($this->shouldPrependOutputBuffer($stimulusResponse)) {
            $response = $this->withPrependedOutputBuffer($response, (string)$stimulusResponse->outputBuffer);
        }

        $this->client->respond($response);
    }

    /**
     * Ends the given stream quietly once RoadRunner reports that the client has stopped it.
     *
     * @param \Generator|string $body Stream body handed to the HTTP worker.
     * @return \Generator|string Stream body ending quietly on client disconnect.
     */
    protected static function endStreamWhenStopped(Generator|string $body): Generator|string
    {
        if (is_string($body)) {
            return $body;
        }

        return (function () use ($body) {
            try {
                yield from $body;
            } catch (StreamStoppedException) {
                return;
            }
        })();
    }

    /**
     * Determines whether buffered output is prepended to the response.
     *
     * Buffered output is prepended to non-streamed responses only.
     *
     * @param \Crustum\Stimulus\Http\StimulusResponse $stimulusResponse Worker response envelope.
     * @return bool Whether the buffered output is prepended.
     */
    protected function shouldPrependOutputBuffer(StimulusResponse $stimulusResponse): bool
    {
        return $stimulusResponse->outputBuffer !== null
            && $stimulusResponse->outputBuffer !== ''
            && !$stimulusResponse->streamer instanceof Closure;
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
        $debug = (bool)Configure::read('debug');

        $this->client->getWorker()->error($debug ? (string)$e : 'Internal server error.');
    }

    /**
     * Stops the underlying server / worker.
     *
     * @return void
     */
    public function stop(): void
    {
        $this->client->getWorker()->stop();
    }

    /**
     * Converts the given PSR-7 server request into a request.
     *
     * Header mapping iterates through a collection pipeline. The host header
     * and address are derived from the URI when the server omits them, headers transfer
     * singly because headers are stored in the request environment where
     * bulk transfers corrupt multi-value headers into arrays, proxies are
     * trusted so scheme and client address resolve behind the worker server,
     * and basic-auth credentials parse into the environment the same way the
     * native worker bridge does. The body travels as-is: the raw stream
     * stays on the input key and the parsed body stays structured, so no
     * rewind or re-parse is needed.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request PSR-7 request received from RoadRunner.
     * @return \Cake\Http\ServerRequest Request for the worker sandbox.
     */
    protected function toCakeRequest(ServerRequestInterface $request): ServerRequest
    {
        $uri = $request->getUri();
        $environment = $request->getServerParams();
        $environment['REQUEST_METHOD'] = $request->getMethod();
        $environment['REQUEST_URI'] = $this->requestUri($request);
        $environment['QUERY_STRING'] = $uri->getQuery();
        $environment['SERVER_PROTOCOL'] = 'HTTP/' . $request->getProtocolVersion();

        if (!isset($environment['HTTP_HOST']) && $uri->getHost() !== '') {
            $environment['HTTP_HOST'] = $this->hostFromUri($uri);
        }

        (new Collection($request->getHeaders()))->each(function (array $values, string $name) use (&$environment): void {
            $normalized = strtoupper(str_replace('-', '_', $name));

            if ($normalized === 'CONTENT_TYPE' || $normalized === 'CONTENT_LENGTH') {
                $environment[$normalized] ??= implode(', ', $values);

                return;
            }

            $key = 'HTTP_' . $normalized;

            $environment[$key] ??= implode(', ', $values);
        });

        $parsedBody = $request->getParsedBody();

        $cakeRequest = new ServerRequest([
            'query' => $request->getQueryParams(),
            'post' => is_array($parsedBody) || is_object($parsedBody) ? $parsedBody : [],
            'files' => $request->getUploadedFiles(),
            'cookies' => $request->getCookieParams(),
            'environment' => $environment,
            'uri' => $uri,
            'webroot' => '/',
            'base' => '',
            'input' => (string)$request->getBody(),
        ]);

        $cakeRequest->trustProxy = true;

        return $this->parseBasicAuth($this->copyHeadersSingly($cakeRequest, $request));
    }

    /**
     * Copies headers from the PSR-7 request onto the request singly.
     *
     * Headers are stored in the request environment, so bulk transfers
     * corrupt multi-value headers into comma-joined strings; single values
     * transfer as strings and multiples as arrays, which the request
     * `withHeader` maps onto the matching environment keys.
     *
     * @param \Cake\Http\ServerRequest $cakeRequest Converted request receiving the headers.
     * @param \Psr\Http\Message\ServerRequestInterface $request PSR-7 request received from RoadRunner.
     * @return \Cake\Http\ServerRequest Request carrying the singly copied headers.
     */
    protected function copyHeadersSingly(ServerRequest $cakeRequest, ServerRequestInterface $request): ServerRequest
    {
        (new Collection($request->getHeaders()))->each(
            function (array $values, string $name) use (&$cakeRequest): void {
                $cakeRequest = $cakeRequest->withHeader($name, count($values) === 1 ? $values[0] : $values);
            },
        );

        return $cakeRequest;
    }

    /**
     * Parses basic-auth credentials into the request environment.
     *
     * Reads the authorization header the servers forward and exposes the
     * credentials as the PHP auth environment pair the authentication
     * layer resolves. Requests without a basic challenge travel on
     * untouched.
     *
     * @param \Cake\Http\ServerRequest $cakeRequest Converted request receiving the credentials.
     * @return \Cake\Http\ServerRequest Request carrying the auth environment when challenged.
     */
    protected function parseBasicAuth(ServerRequest $cakeRequest): ServerRequest
    {
        $authorizationHeader = $cakeRequest->getHeader('Authorization')[0] ?? null;

        if ($authorizationHeader === null) {
            return $cakeRequest;
        }

        $matches = [];

        if (!preg_match('/Basic\s+(.*)$/i', $authorizationHeader, $matches)) {
            return $cakeRequest;
        }

        $decoded = base64_decode($matches[1], true);

        if ($decoded === false) {
            return $cakeRequest;
        }

        $parts = explode(':', $decoded, 2);

        return $cakeRequest
            ->withEnv('PHP_AUTH_USER', $parts[0])
            ->withEnv('PHP_AUTH_PW', $parts[1] ?? '');
    }

    /**
     * Builds the host header value from the given request URI.
     *
     * Derives the host the server omits, keeping a non-default port and
     * dropping the default one per scheme.
     *
     * @param \Psr\Http\Message\UriInterface $uri Request URI carrying the host.
     * @return string Host header value for the converted request.
     */
    protected function hostFromUri(UriInterface $uri): string
    {
        $port = $uri->getPort();

        if ($port === null) {
            return $uri->getHost();
        }

        $includesPort = ($uri->getScheme() === 'http' && $port !== 80)
            || ($uri->getScheme() === 'https' && $port !== 443);

        if ($includesPort) {
            return $uri->getHost() . ':' . $port;
        }

        return $uri->getHost();
    }

    /**
     * Exposes pending response cookies as `Set-Cookie` headers.
     *
     * Queued cookies are kept in the response cookie collection instead
     * of headers, which the RoadRunner worker would never send; every
     * pending cookie value joins the response as a `Set-Cookie` header so
     * one request never leaks cookies into the next. Other responses
     * travel on untouched. The traversal stays imperative because the vendor
     * collection discards the list shape the header call promises.
     *
     * @param \Psr\Http\Message\ResponseInterface $response Worker response to extend.
     * @return \Psr\Http\Message\ResponseInterface Response carrying the cookie headers.
     */
    protected function withWorkerCookies(ResponseInterface $response): ResponseInterface
    {
        $cookies = $this->cakeCookieHeaders($response);

        if ($cookies === []) {
            return $response;
        }

        return $response->withAddedHeader('Set-Cookie', $cookies);
    }

    /**
     * Collects the `Set-Cookie` header values pending on the worker response.
     *
     * Responses carry cookies in their cookie collection instead of
     * `Set-Cookie` headers, so generic header parsing finds none; this
     * traversal exposes them for the RoadRunner respond call. Other
     * responses carry no collection and contribute no cookies here. The
     * traversal stays imperative because the vendor collection discards the
     * list shape the contract promises.
     *
     * @param \Psr\Http\Message\ResponseInterface $response Worker response.
     * @return list<string> `Set-Cookie` header values.
     */
    protected function cakeCookieHeaders(ResponseInterface $response): array
    {
        if (!$response instanceof Response) {
            return [];
        }

        $cookies = [];

        foreach ($response->getCookieCollection() as $cookie) {
            $cookies[] = $cookie->toHeaderValue();
        }

        return $cookies;
    }

    /**
     * Builds the request URI from the given PSR-7 request URI parts.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request PSR-7 request received from RoadRunner.
     * @return string Path and query string identifying the requested resource.
     */
    protected function requestUri(ServerRequestInterface $request): string
    {
        $uri = $request->getUri();
        $requestUri = $uri->getPath();

        if ($uri->getQuery() !== '') {
            $requestUri .= '?' . $uri->getQuery();
        }

        return $requestUri === '' ? '/' : $requestUri;
    }

    /**
     * Returns the given response with the buffered output prepended to its body.
     *
     * @param \Psr\Http\Message\ResponseInterface $response Worker response to extend.
     * @param string $outputBuffer Output buffered while handling the request.
     * @return \Psr\Http\Message\ResponseInterface Response with the buffered output prepended.
     */
    protected function withPrependedOutputBuffer(ResponseInterface $response, string $outputBuffer): ResponseInterface
    {
        $body = new Stream('php://memory', 'wb+');
        $body->write($outputBuffer);
        $body->write((string)$response->getBody());

        return $response->withBody($body);
    }
}
