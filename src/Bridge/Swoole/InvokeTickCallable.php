<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Bridge\Swoole;

use Crustum\Stimulus\Contracts\CacheStore;
use Throwable;

/**
 * Invokes a tick listener once it is due.
 *
 * Completing the P8 tick registration on Swoole. The clock reads `time()` and
 * the exception handler contract is any callable or an object
 * exposing `report()`; the tick manager supplies a log-reporting callback. The cache is
 * any store exposing `get` and `forever`, which is the Swoole-backed store on
 * extension hosts.
 */
class InvokeTickCallable
{
    /**
     * Creates a new tick invoker.
     *
     * @param string $key Tick callback identifier.
     * @param mixed $callback Callback invoked once the tick is due.
     * @param int $seconds Interval in seconds between invocations.
     * @param bool $immediate Whether the callback runs on the first tick after the server starts.
     * @param \Crustum\Stimulus\Contracts\CacheStore $cache Cache tracking the last invocation timestamps.
     * @param mixed $exceptionHandler Callback or `report()` object receiving listener failures.
     */
    public function __construct(
        protected string $key,
        protected mixed $callback,
        protected int $seconds,
        protected bool $immediate,
        protected CacheStore $cache,
        protected mixed $exceptionHandler,
    ) {
    }

    /**
     * Invokes the tick listener.
     *
     * Skips the callback while the interval has not elapsed and on the first
     * tick unless the listener is immediate.
     *
     * @return void
     */
    public function __invoke(): void
    {
        $lastInvokedAt = $this->cache->get('tick-' . $this->key);

        if ($lastInvokedAt !== null && (time() - $lastInvokedAt) < $this->seconds) {
            return;
        }

        $this->cache->forever('tick-' . $this->key, time());

        if ($lastInvokedAt === null && !$this->immediate) {
            return;
        }

        try {
            call_user_func($this->callback);
        } catch (Throwable $throwable) {
            $this->report($throwable);
        }
    }

    /**
     * Indicates how often the listener should be invoked.
     *
     * @param int $seconds Interval in seconds between invocations.
     * @return $this
     */
    public function seconds(int $seconds): static
    {
        $this->seconds = $seconds;

        return $this;
    }

    /**
     * Indicates that the listener should be invoked on the first tick after the server starts.
     *
     * @return $this
     */
    public function immediate(): static
    {
        $this->immediate = true;

        return $this;
    }

    /**
     * Reports a listener failure to the exception handler.
     *
     * Calls the handler directly when it is a callback and delegates to its
     * `report` method when it exposes one, without the framework handler contract.
     *
     * @param \Throwable $throwable Throwable raised by the tick listener.
     * @return void
     */
    protected function report(Throwable $throwable): void
    {
        if (is_callable($this->exceptionHandler)) {
            ($this->exceptionHandler)($throwable);

            return;
        }

        if (is_object($this->exceptionHandler) && method_exists($this->exceptionHandler, 'report')) {
            $this->exceptionHandler->report($throwable);
        }
    }
}
