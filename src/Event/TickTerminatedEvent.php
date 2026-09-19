<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Event;

use Crustum\Stimulus\Contracts\OperationTerminated;

/**
 * Event dispatched after a tick operation terminates.
 */
class TickTerminatedEvent extends StimulusEvent implements OperationTerminated
{
    use HasApplicationAndSandboxTrait;

    /**
     * Creates a new tick terminated event.
     *
     * @param object $app Base application instance.
     * @param object $sandbox Sandbox application instance that handled the tick.
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
     * Gets the sandbox application instance that handled the tick.
     *
     * @return object Sandbox application instance that handled the tick.
     */
    public function getSandbox(): object
    {
        return $this->sandbox;
    }
}
