<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Event;

use Throwable;

/**
 * Event dispatched when the worker catches an operation exception.
 */
class WorkerErrorOccurredEvent extends StimulusEvent
{
    /**
     * Creates a new worker error occurred event.
     *
     * @param \Throwable $exception Throwable caught by the worker.
     * @param object $sandbox Sandbox application instance that was active.
     */
    public function __construct(
        public Throwable $exception,
        public object $sandbox,
    ) {
        parent::__construct([
            'exception' => $exception,
            'sandbox' => $sandbox,
        ]);
    }

    /**
     * Gets the throwable caught by the worker.
     *
     * @return \Throwable Throwable caught by the worker.
     */
    public function getException(): Throwable
    {
        return $this->exception;
    }

    /**
     * Gets the sandbox application instance that was active.
     *
     * @return object Sandbox application instance that was active.
     */
    public function getSandbox(): object
    {
        return $this->sandbox;
    }
}
