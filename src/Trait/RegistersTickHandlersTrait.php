<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Trait;

use Cake\Event\EventManager;
use Cake\Log\Log;
use Crustum\Stimulus\Bridge\Swoole\InvokeTickCallable;
use Crustum\Stimulus\Cache\StimulusArrayStore;
use Crustum\Stimulus\Event\TickReceivedEvent;
use Throwable;

/**
 * Registers callbacks invoked on worker ticks.
 *
 * Schedules an `InvokeTickCallable` per registration over the shared Swoole
 * cache through the event dispatcher. On extension hosts the same invoker schedules the
 * callback with its interval bookkeeping; without the extension the callback
 * runs on every received tick through the event manager instead. The
 * Swoole branch tracks invocations in a per-process array store until worker
 * entries bind the shared table-backed store, and listener failures report
 * through the log.
 */
trait RegistersTickHandlersTrait
{
    /**
     * Registers a callback to be called on worker ticks.
     *
     * Without the Swoole extension there is no interval bookkeeping, so the
     * callback runs on every tick rather than every N seconds. On extension
     * hosts the registration returns the due-checked invoker instead.
     *
     * @param string $key Tick callback identifier scheduling the invoker on extension hosts.
     * @param callable $callback Callback invoked on due ticks.
     * @param int $seconds Interval in seconds between invocations on extension hosts.
     * @param bool $immediate Whether the callback runs on the first tick on extension hosts.
     * @return object Event listener attached to the tick received event.
     */
    public function tick(string $key, callable $callback, int $seconds = 1, bool $immediate = true): object
    {
        if (extension_loaded('swoole') || extension_loaded('openswoole')) {
            $listener = new InvokeTickCallable(
                $key,
                $callback,
                $seconds,
                $immediate,
                new StimulusArrayStore(),
                static function (Throwable $throwable): void {
                    Log::error($throwable::class . ': ' . $throwable->getMessage(), ['code' => $throwable->getCode()]);
                },
            );

            EventManager::instance()->on(TickReceivedEvent::eventName(), $listener);

            return $listener;
        }

        $listener = function () use ($callback): void {
            $callback();
        };

        EventManager::instance()->on(TickReceivedEvent::eventName(), $listener);

        return $listener;
    }
}
