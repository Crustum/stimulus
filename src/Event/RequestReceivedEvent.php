<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Event;

use Cake\Http\ServerRequest;

/**
 * Event dispatched when the worker receives a request.
 */
class RequestReceivedEvent extends StimulusEvent
{
    /**
     * Creates a new request received event.
     *
     * @param object $app Base application instance.
     * @param object $sandbox Sandbox application instance handling the request.
     * @param \Cake\Http\ServerRequest $request Request that was received.
     */
    public function __construct(
        public object $app,
        public object $sandbox,
        public ServerRequest $request,
    ) {
        parent::__construct([
            'app' => $app,
            'sandbox' => $sandbox,
            'request' => $request,
        ]);
    }

    /**
     * Gets the base application instance.
     *
     * @return object Base application instance.
     */
    public function getApp(): object
    {
        return $this->app;
    }

    /**
     * Gets the sandbox application instance handling the request.
     *
     * @return object Sandbox application instance handling the request.
     */
    public function getSandbox(): object
    {
        return $this->sandbox;
    }

    /**
     * Gets the request that was received.
     *
     * @return \Cake\Http\ServerRequest Request that was received.
     */
    public function getRequest(): ServerRequest
    {
        return $this->request;
    }

    /**
     * Sets the request carried by the event.
     *
     * Keeps the promoted property and the event data in sync so
     * listeners rewriting the request keep both reads coherent.
     *
     * @param \Cake\Http\ServerRequest $request Request replacing the received one.
     * @return void
     */
    public function setRequest(ServerRequest $request): void
    {
        $this->request = $request;
        $this->setData('request', $request);
    }
}
