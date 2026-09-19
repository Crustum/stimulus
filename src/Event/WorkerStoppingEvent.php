<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Event;

/**
 * Event dispatched when the worker stops.
 */
class WorkerStoppingEvent extends StimulusEvent
{
    /**
     * Creates a new worker stopping event.
     *
     * @param object $app Base application instance.
     */
    public function __construct(public object $app)
    {
        parent::__construct([
            'app' => $app,
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
}
