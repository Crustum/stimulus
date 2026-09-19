<?php
declare(strict_types=1);

namespace OpenSwoole\Coroutine;

/**
 * Analysis stub for the OpenSwoole coroutine scheduler function.
 *
 * This declaration exists solely for PHPStan (wired through `stubFiles` in
 * `phpstan.neon`) so the dual-runtime coroutine dispatcher analyzes without
 * the extensions installed. It is never autoloaded at runtime: Composer maps
 * only `Crustum\Stimulus\` to `src/`, and the dispatcher probes the runtime
 * through `function_exists` before entering either scheduler.
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
