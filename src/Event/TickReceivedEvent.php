<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Event;

/**
 * Event dispatched when the worker receives a tick.
 */
class TickReceivedEvent extends StimulusEvent
{
    /**
     * Creates a new tick received event.
     *
     * @param object $app Base application instance.
     * @param object $sandbox Sandbox application instance handling the tick.
     */
    public function __construct(
        public object $app,
        public object $sandbox,
    ) {
        parent::__construct([
            'app' => $app,
            'sandbox' => $sandbox,
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
     * Gets the sandbox application instance handling the tick.
     *
     * @return object Sandbox application instance handling the tick.
     */
    public function getSandbox(): object
    {
        return $this->sandbox;
    }
}
