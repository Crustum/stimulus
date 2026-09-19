<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Bridge\Swoole;

use Crustum\Stimulus\Contracts\DispatchesCoroutines;
use Crustum\Stimulus\Dispatch\SequentialCoroutineDispatcher;
use OpenSwoole\Coroutine as OpenSwooleCoroutine;
use OpenSwoole\Coroutine\WaitGroup as OpenSwooleWaitGroup;
use Swoole\Coroutine;
use Swoole\Coroutine\WaitGroup;

/**
 * Swoole coroutine dispatcher.
 *
 * The file loads where both extensions are absent because it only names the
 * coroutine classes; resolution itself runs behind the runtime lookup. The
 * Swoole runtime wins when present and the OpenSwoole runtime covers
 * OpenSwoole-only hosts, while hosts with neither extension delegate to the
 * sequential coroutine dispatcher, matching the P2 fallback. The wait-group
 * fan-out stays imperative, looping over the coroutines instead of collecting
 * them.
 */
class SwooleCoroutineDispatcher implements DispatchesCoroutines
{
    /**
     * Creates a new Swoole coroutine dispatcher.
     *
     * @param bool $withinCoroutineContext Whether resolution already runs inside a coroutine context.
     */
    public function __construct(protected bool $withinCoroutineContext = false)
    {
    }

    /**
     * Concurrently resolves the given callbacks via coroutines, returning the results.
     *
     * The scheduler entry is the namespaced run function; both
     * runtimes expose it as a function, not a static method.
     *
     * @param array<int|string, callable> $coroutines Callbacks to resolve concurrently.
     * @param int $waitSeconds Seconds to wait for the coroutines to finish.
     * @return array<int|string, mixed> Resolved coroutine results keyed by their given keys.
     */
    public function resolve(array $coroutines, int $waitSeconds = -1): array
    {
        $runtime = $this->coroutineRuntime();

        if ($runtime === null) {
            return (new SequentialCoroutineDispatcher())->resolve($coroutines, $waitSeconds);
        }

        [$coroutineClass, $waitGroupClass, $run] = $runtime;

        $results = [];

        $callback = function () use (&$results, $coroutines, $waitSeconds, $coroutineClass, $waitGroupClass): void {
            $waitGroup = new $waitGroupClass();

            foreach ($coroutines as $key => $coroutine) {
                $coroutineClass::create(function () use ($key, $coroutine, $waitGroup, &$results): void {
                    $waitGroup->add();

                    $results[$key] = $coroutine();

                    $waitGroup->done();
                });
            }

            $waitGroup->wait($waitSeconds);
        };

        if (!$this->withinCoroutineContext) {
            $run($callback);
        } else {
            $callback();
        }

        return $results;
    }

    /**
     * Resolves the coroutine runtime available on the host.
     *
     * Prefers the Swoole runtime and falls back to the OpenSwoole runtime so
     * OpenSwoole-only hosts resolve coroutines instead of failing on the
     * missing Swoole classes; hosts with neither runtime get null for the
     * sequential fallback. Each triple carries the coroutine class, its wait
     * group class, and the scheduler entry function.
     *
     * @return array{0: class-string<\Swoole\Coroutine>|class-string<\OpenSwoole\Coroutine>, 1: class-string<\Swoole\Coroutine\WaitGroup>|class-string<\OpenSwoole\Coroutine\WaitGroup>, 2: callable}|null Coroutine runtime triple, or null without a runtime.
     */
    protected function coroutineRuntime(): ?array
    {
        if (class_exists(Coroutine::class) && function_exists('Swoole\\Coroutine\\run')) {
            return [Coroutine::class, WaitGroup::class, Coroutine\run(...)];
        }

        if (class_exists(OpenSwooleCoroutine::class) && function_exists('OpenSwoole\\Coroutine\\run')) {
            return [OpenSwooleCoroutine::class, OpenSwooleWaitGroup::class, OpenSwooleCoroutine\run(...)];
        }

        return null;
    }
}
