<?php
declare(strict_types=1);

namespace OpenSwoole;

/**
 * Analysis stub for the OpenSwoole timer.
 *
 * This declaration exists solely for PHPStan (wired through `stubFiles` in
 * `phpstan.neon`) so the dual-runtime server-start handler analyzes without
 * the extensions installed. It is never autoloaded at runtime: Composer maps
 * only `Crustum\Stimulus\` to `src/`, and the handler probes the runtime
 * through `class_exists` before scheduling any timer.
 */
class Timer
{
    /**
     * Schedules a recurring timer callback.
     *
     * @param int $milliseconds Interval in milliseconds between invocations.
     * @param callable $callback Callback invoked on every tick.
     * @return int Timer ID clearing the timer.
     */
    public static function tick(int $milliseconds, callable $callback): int
    {
        return 0;
    }

    /**
     * Clears a scheduled timer.
     *
     * @param int $timerId Timer ID to clear.
     * @return bool Whether the timer was cleared.
     */
    public static function clear(int $timerId): bool
    {
        return true;
    }
}
