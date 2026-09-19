<?php
declare(strict_types=1);

namespace Swoole\Coroutine;

/**
 * Analysis stub for the Swoole coroutine scheduler function.
 *
 * This declaration exists solely for PHPStan (wired through `stubFiles` in
 * `phpstan.neon`) so the ext-gated Swoole bridge analyzes without the
 * extensions installed. It is never autoloaded at runtime: Composer maps
 * only `Crustum\Stimulus\` to `src/`, and the coroutine dispatcher returns
 * the sequential fallback on hosts without the extension. Upstream enters
 * the scheduler through this namespaced function, not a static method.
 */

/**
 * Runs the given callback inside a coroutine context.
 *
 * @param callable $callback Callback running inside the coroutine.
 * @param mixed ...$arguments Arguments passed to the callback.
 * @return mixed Callback result.
 */
function run(callable $callback, mixed ...$arguments): mixed
{
    return null;
}
