<?php
declare(strict_types=1);

namespace Swoole;

/**
 * Analysis stub for the Swoole coroutine scheduler.
 *
 * This declaration exists solely for PHPStan (wired through `stubFiles` in
 * `phpstan.neon`) so the ext-gated Swoole bridge analyzes without the
 * extensions installed. It is never autoloaded at runtime: Composer maps
 * only `Crustum\Stimulus\` to `src/`, and the coroutine dispatcher returns
 * the sequential fallback on hosts without the extension. The scheduler
 * entry function lives in `Swoole/Coroutine/functions.php`, mirroring the
 * upstream namespaced call.
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
