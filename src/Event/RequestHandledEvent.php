<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Event;

use Cake\Http\ServerRequest;
use Psr\Http\Message\ResponseInterface;

/**
 * Event dispatched after the worker handled a request.
 */
class RequestHandledEvent extends StimulusEvent
{
    /**
     * Creates a new request handled event.
     *
     * @param object $sandbox Sandbox application instance that handled the request.
     * @param \Cake\Http\ServerRequest $request Request that was handled.
     * @param \Psr\Http\Message\ResponseInterface $response Response generated for the request.
     */
    public function __construct(
        public object $sandbox,
        public ServerRequest $request,
        public ResponseInterface $response,
    ) {
        parent::__construct([
            'sandbox' => $sandbox,
            'request' => $request,
            'response' => $response,
        ]);
    }

    /**
     * Gets the sandbox application instance that handled the request.
     *
     * @return object Sandbox application instance that handled the request.
     */
    public function getSandbox(): object
    {
        return $this->sandbox;
    }

    /**
     * Gets the request that was handled.
     *
     * @return \Cake\Http\ServerRequest Request that was handled.
     */
    public function getRequest(): ServerRequest
    {
        return $this->request;
    }

    /**
     * Gets the response generated for the request.
     *
     * @return \Psr\Http\Message\ResponseInterface Response generated for the request.
     */
    public function getResponse(): ResponseInterface
    {
        return $this->response;
    }

    /**
     * Sets the request carried by the event.
     *
     * Keeps the promoted property and the event data in sync so
     * listeners rewriting the request keep both reads coherent.
     *
     * @param \Cake\Http\ServerRequest $request Request replacing the handled one.
     * @return void
     */
    public function setRequest(ServerRequest $request): void
    {
        $this->request = $request;
        $this->setData('request', $request);
    }
}
