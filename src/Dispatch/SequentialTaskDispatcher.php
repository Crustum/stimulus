<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Dispatch;

use Cake\Collection\Collection;
use Crustum\Stimulus\Contracts\DispatchesTasks;
use Crustum\Stimulus\Exception\TaskExceptionResult;
use Throwable;

/**
 * Sequential task dispatcher used when no task worker driver is available.
 *
 * Runs each callback eagerly in the current process, capturing failures
 * as exception snapshots so every task runs before the first failure
 * is rethrown to the caller.
 *
 * No shared eager-resolve base is extracted with the sequential coroutine
 * dispatcher: tasks capture throwables into exception snapshots and rethrow
 * the first failure only after every task ran, while coroutines propagate
 * throwables immediately and expose no dispatch method. The two failure
 * contracts and the `DispatchesTasks` / `DispatchesCoroutines` interfaces
 * stay intact by keeping the classes separate.
 */
class SequentialTaskDispatcher implements DispatchesTasks
{
    /**
     * Concurrently resolves the given callbacks via background tasks, returning the results.
     *
     * Results will be keyed by their given keys - if a task did not finish, the tasks value will be "false".
     *
     * Runs in two stages: the first stage materializes every task result into an
     * array so eager tasks run exactly once, and the second stage rethrows
     * the first captured failure. Splitting the terminal passes avoids
     * re-iterating the lazy mapping, which would execute tasks twice.
     *
     * @param array<int|string, callable> $tasks Callbacks to resolve concurrently.
     * @param int $waitMilliseconds Milliseconds to wait for the tasks to finish.
     * @return array<int|string, mixed> Resolved task results keyed by their given keys.
     * @throws \Crustum\Stimulus\Exception\TaskException When a background task raises an exception.
     * @throws \Crustum\Stimulus\Exception\TaskTimeoutException When a background task exceeds its time limit.
     */
    public function resolve(array $tasks, int $waitMilliseconds = 1): array
    {
        $results = (new Collection($tasks))
            ->map(function ($task) {
                try {
                    return $task();
                } catch (Throwable $throwable) {
                    return TaskExceptionResult::from($throwable);
                }
            })
            ->toArray();

        (new Collection($results))
            ->each(function ($result): void {
                if ($result instanceof TaskExceptionResult) {
                    throw $result->getOriginal();
                }
            });

        return $results;
    }

    /**
     * Concurrently dispatches the given callbacks via background tasks.
     *
     * @param array<int|string, callable> $tasks Callbacks to dispatch concurrently.
     * @return void
     */
    public function dispatch(array $tasks): void
    {
        try {
            $this->resolve($tasks);
        } catch (Throwable) {
        }
    }
}
