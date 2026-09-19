<?php
declare(strict_types=1);

namespace OpenSwoole\Coroutine;

/**
 * Analysis stub for the OpenSwoole coroutine wait group.
 *
 * This declaration exists solely for PHPStan (wired through `stubFiles` in
 * `phpstan.neon`) so the dual-runtime coroutine dispatcher analyzes without
 * the extensions installed. It is never autoloaded at runtime: Composer maps
 * only `Crustum\Stimulus\` to `src/`, and the dispatcher probes the runtime
 * through `class_exists` before touching either scheduler.
 */
class WaitGroup
{
    /**
     * Adds tasks to the wait group counter.
     *
     * @param int $delta Number of tasks to add.
     * @return void
     */
    public function add(int $delta = 1): void
    {
    }

    /**
     * Marks one task as done.
     *
     * @return void
     */
    public function done(): void
    {
    }

    /**
     * Waits until the counter drains or the timeout elapses.
     *
     * @param float $timeout Seconds to wait, negative waiting indefinitely.
     * @return bool Whether the counter drained in time.
     */
    public function wait(float $timeout = -1): bool
    {
        return true;
    }
}
