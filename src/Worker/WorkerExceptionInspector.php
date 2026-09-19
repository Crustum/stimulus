<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Worker;

use Throwable;

/**
 * Replays remote task-exception identity for client display.
 *
 * Dumped exceptions render with their original class and trace. The inspector
 * builds a standalone snapshot carrying the throwable plus its original class
 * and trace for the log and error renderer.
 * Pairs with the `ReportException` listener, which logs worker failures.
 */
class WorkerExceptionInspector
{
    /**
     * Creates a new worker exception inspector.
     *
     * @param \Throwable $throwable Throwable caught by the worker.
     * @param string $class Original throwable class name for client display.
     * @param array<int, mixed> $trace Original throwable trace for client display.
     */
    public function __construct(
        protected Throwable $throwable,
        protected string $class,
        protected array $trace,
    ) {
    }

    /**
     * Gets the inspected throwable.
     *
     * @return \Throwable Throwable caught by the worker.
     */
    public function getThrowable(): Throwable
    {
        return $this->throwable;
    }

    /**
     * Gets the worker exception name.
     *
     * Returns the replayed original class instead of the local throwable
     * class so remote task failures display their origin identity.
     *
     * @return string Original throwable class name.
     */
    public function getExceptionName(): string
    {
        return $this->class;
    }

    /**
     * Gets the worker exception trace.
     *
     * Returns the replayed original trace instead of the local throwable
     * trace so remote task failures display their origin frames.
     *
     * @param \Throwable|null $throwable Ignored throwable kept for signature compatibility.
     * @return array<int, mixed> Original throwable trace.
     */
    public function getTrace(?Throwable $throwable = null): array
    {
        return $this->trace;
    }
}
