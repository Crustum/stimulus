<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Bridge\FrankenPhp;

use Cake\Core\Configure;
use Cake\Http\ResponseEmitter;
use Cake\Http\ServerRequest;
use Cake\Http\ServerRequestFactory;
use Closure;
use Crustum\Stimulus\Contracts\Client;
use Crustum\Stimulus\Http\RequestContext;
use Crustum\Stimulus\Http\StimulusResponse;
use Crustum\Stimulus\Trait\StreamsGeneratorResponsesTrait;
use Laminas\Diactoros\Response\TextResponse;
use Throwable;

/**
 * FrankenPHP server bridge client.
 *
 * Requests are captured from the worker globals through the request factory, and worker responses are
 * emitted through the response emitter instead of Symfony responses. Callback-streamed bodies echo
 * and flush through the shared streaming loop instead of the buffered emitter.
 */
class FrankenPhpClient implements Client
{
    use StreamsGeneratorResponsesTrait;

    /**
     * Emitter sending worker responses back to the FrankenPHP server.
     *
     * @var \Cake\Http\ResponseEmitter
     */
    protected ResponseEmitter $emitter;

    /**
     * Creates a new FrankenPHP client.
     *
     * @param \Cake\Http\ResponseEmitter|null $emitter Response emitter, or null for a default emitter.
     */
    public function __construct(?ResponseEmitter $emitter = null)
    {
        $this->emitter = $emitter ?? new ResponseEmitter();
    }

    /**
     * Marshals the given request context into a request.
     *
     * @param \Crustum\Stimulus\Http\RequestContext $context Raw request context from the server.
     * @return array<int, mixed> Marshalled request and context pair.
     */
    public function marshalRequest(RequestContext $context): array
    {
        return [
            ServerRequestFactory::fromGlobals(),
            $context,
        ];
    }

    /**
     * Sends the response to the server.
     *
     * Envelopes carrying a chunk producer stream through the shared loop,
     * echoing and flushing every chunk; every other response is emitted
     * buffered.
     *
     * @param \Crustum\Stimulus\Http\RequestContext $context Raw request context from the server.
     * @param \Crustum\Stimulus\Http\StimulusResponse $stimulusResponse Worker response envelope.
     * @return void
     */
    public function respond(RequestContext $context, StimulusResponse $stimulusResponse): void
    {
        if ($stimulusResponse->streamer instanceof Closure) {
            $this->streamGeneratorResponse($stimulusResponse->streamer, $this->writeStreamedChunk(...));

            return;
        }

        $this->emitter->emit($stimulusResponse->response);
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

        $this->emitter->emit(new TextResponse(
            $debug ? (string)$e : 'Internal server error.',
            500,
            [
                'Status' => '500 Internal Server Error',
                'Content-Type' => 'text/plain',
            ],
        ));
    }
}
