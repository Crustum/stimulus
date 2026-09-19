<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Event;

/**
 * Event dispatched when the worker receives a task.
 */
class TaskReceivedEvent extends StimulusEvent
{
    /**
     * Creates a new task received event.
     *
     * @param object $app Base application instance.
     * @param object $sandbox Sandbox application instance handling the task.
     * @param mixed $data Task payload.
     */
    public function __construct(
        public object $app,
        public object $sandbox,
        public mixed $data,
    ) {
        parent::__construct([
            'app' => $app,
            'sandbox' => $sandbox,
            'data' => $data,
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
     * Gets the sandbox application instance handling the task.
     *
     * @return object Sandbox application instance handling the task.
     */
    public function getSandbox(): object
    {
        return $this->sandbox;
    }

    /**
     * Gets the task payload.
     *
     * @return mixed Task payload.
     */
    public function getPayload(): mixed
    {
        return $this->data;
    }
}
