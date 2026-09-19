<?php
declare(strict_types=1);

namespace OpenSwoole;

/**
 * Analysis stub for the OpenSwoole coroutine scheduler.
 *
 * This declaration exists solely for PHPStan (wired through `stubFiles` in
 * `phpstan.neon`) so the dual-runtime coroutine dispatcher analyzes without
 * the extensions installed. It is never autoloaded at runtime: Composer maps
 * only `Crustum\Stimulus\` to `src/`, and the dispatcher probes the runtime
 * through `class_exists` before touching either scheduler.
 */
class Coroutine
{
    /**
     * Creates a new coroutine running the given callback.
     *
     * @param callable $callback Callback running inside the coroutine.
     * @return int|false Coroutine ID, or false when creation failed.
     */
    public static function create(callable $callback): int|false
    {
        return 0;
    }
}
