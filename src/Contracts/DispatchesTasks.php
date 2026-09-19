<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Contracts;

/**
 * Contract for dispatchers resolving callbacks via background tasks.
 */
interface DispatchesTasks
{
    /**
     * Concurrently resolve the given callbacks via background tasks, returning the results.
     *
     * Results will be keyed by their given keys - if a task did not finish, the tasks value will be "false".
     *
     * @param array<int|string, callable> $tasks Callbacks to resolve concurrently.
     * @param int $waitMilliseconds Milliseconds to wait for the tasks to finish.
     * @return array<int|string, mixed> Resolved task results keyed by their given keys.
     * @throws \Crustum\Stimulus\Exception\TaskException When a background task raises an exception.
     * @throws \Crustum\Stimulus\Exception\TaskTimeoutException When a background task exceeds its time limit.
     */
    public function resolve(array $tasks, int $waitMilliseconds = 3000): array;

    /**
     * Concurrently dispatch the given callbacks via background tasks.
     *
     * @param array<int|string, callable> $tasks Callbacks to dispatch concurrently.
     * @return void
     */
    public function dispatch(array $tasks): void;
}
