<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Contracts;

use Cake\Http\ServerRequest;
use Crustum\Stimulus\Http\RequestContext;
use Crustum\Stimulus\Http\StimulusResponse;
use Throwable;

/**
 * Contract for server bridge clients.
 *
 * Marshals raw request contexts into requests and sends
 * worker responses and errors back to the underlying server.
 */
interface Client
{
    /**
     * Marshal the given request context into a request.
     *
     * @param \Crustum\Stimulus\Http\RequestContext $context Raw request context from the server.
     * @return array<int, mixed> Marshalled request and context pair.
     */
    public function marshalRequest(RequestContext $context): array;

    /**
     * Send the response to the server.
     *
     * @param \Crustum\Stimulus\Http\RequestContext $context Raw request context from the server.
     * @param \Crustum\Stimulus\Http\StimulusResponse $response Worker response envelope.
     * @return void
     */
    public function respond(RequestContext $context, StimulusResponse $response): void;

    /**
     * Send an error message to the server.
     *
     * @param \Throwable $e Throwable raised while handling the operation.
     * @param object $app Base application instance.
     * @param \Cake\Http\ServerRequest $request Request that was being handled.
     * @param \Crustum\Stimulus\Http\RequestContext $context Raw request context from the server.
     * @return void
     */
    public function error(Throwable $e, object $app, ServerRequest $request, RequestContext $context): void;
}
