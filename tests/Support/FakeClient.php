<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Support;

use Cake\Core\Configure;
use Cake\Http\ServerRequest;
use Crustum\Stimulus\Contracts\Client;
use Crustum\Stimulus\Http\RequestContext;
use Crustum\Stimulus\Http\StimulusResponse;
use Throwable;

/**
 * In-memory test double for server bridge clients.
 *
 * Records worker responses and errors instead of sending them to a server.
 */
class FakeClient implements Client
{
    /**
     * Number of requests marshalled so far.
     *
     * @var int
     */
    public int $index = 0;

    /**
     * Responses sent to the fake server.
     *
     * @var array<int, mixed>
     */
    public array $responses = [];

    /**
     * Error messages sent to the fake server.
     *
     * @var array<int, string>
     */
    public array $errors = [];

    /**
     * Creates a new fake client.
     *
     * @param array<int, mixed> $requests Requests or task payloads to serve.
     */
    public function __construct(public array $requests)
    {
    }

    /**
     * Marshal the given request context into a request.
     *
     * @param \Crustum\Stimulus\Http\RequestContext $context Raw request context from the server.
     * @return array<int, mixed> Marshalled request and context pair.
     */
    public function marshalRequest(RequestContext $context): array
    {
        $this->index++;

        return [$context->request, $context];
    }

    /**
     * Records the response instead of sending it to the server.
     *
     * @param \Crustum\Stimulus\Http\RequestContext $context Raw request context from the server.
     * @param \Crustum\Stimulus\Http\StimulusResponse $stimulusResponse Worker response envelope.
     * @return void
     */
    public function respond(RequestContext $context, StimulusResponse $stimulusResponse): void
    {
        $this->responses[] = $stimulusResponse->response;
    }

    /**
     * Records the error message instead of sending it to the server.
     *
     * @param \Throwable $e Throwable raised while handling the operation.
     * @param object $app Base application instance.
     * @param \Cake\Http\ServerRequest $request Request that was being handled.
     * @param \Crustum\Stimulus\Http\RequestContext $context Raw request context from the server.
     * @return void
     */
    public function error(Throwable $e, object $app, ServerRequest $request, RequestContext $context): void
    {
        $message = Configure::read('debug') ? (string)$e : 'Internal server error.';

        $this->errors[] = $message;
    }
}
