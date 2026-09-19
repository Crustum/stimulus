<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Dispatch;

use Cake\Collection\Collection;
use Crustum\Stimulus\Contracts\DispatchesCoroutines;

/**
 * Sequential coroutine dispatcher used when no coroutine driver is available.
 *
 * Resolves each callback eagerly in the current process, preserving
 * result keys, instead of scheduling them on an event loop.
 *
 * No shared eager-resolve base is extracted with the sequential task
 * dispatcher: coroutines propagate throwables immediately and expose no
 * dispatch method, while tasks capture failures into exception snapshots
 * and rethrow the first failure only after every task ran. The two failure
 * contracts and the `DispatchesCoroutines` / `DispatchesTasks` interfaces
 * stay intact by keeping the classes separate.
 */
class SequentialCoroutineDispatcher implements DispatchesCoroutines
{
    /**
     * Concurrently resolves the given callbacks via coroutines, returning the results.
     *
     * @param array<int|string, callable> $coroutines Callbacks to resolve concurrently.
     * @param int $waitSeconds Seconds to wait for the coroutines to finish.
     * @return array<int|string, mixed> Resolved coroutine results keyed by their given keys.
     */
    public function resolve(array $coroutines, int $waitSeconds = -1): array
    {
        return (new Collection($coroutines))
            ->map(fn($coroutine) => $coroutine())
            ->toArray();
    }
}
