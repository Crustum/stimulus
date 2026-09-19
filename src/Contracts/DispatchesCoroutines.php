<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Contracts;

/**
 * Contract for dispatchers resolving callbacks via coroutines.
 */
interface DispatchesCoroutines
{
    /**
     * Concurrently resolve the given callbacks via coroutines, returning the results.
     *
     * @param array<int|string, callable> $coroutines Callbacks to resolve concurrently.
     * @param int $waitSeconds Seconds to wait for the coroutines to finish.
     * @return array<int|string, mixed> Resolved coroutine results keyed by their given keys.
     */
    public function resolve(array $coroutines, int $waitSeconds = -1): array;
}
