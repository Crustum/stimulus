<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Bridge\Swoole;

use Crustum\Stimulus\Bridge\Swoole\InvokeTickCallable;
use Crustum\Stimulus\Cache\StimulusArrayStore;
use Crustum\Stimulus\Test\Support\FakeTickExceptionHandler;
use Crustum\Stimulus\Test\TestCase;
use Exception;
use Throwable;

/**
 * Tests for the Swoole tick invoker.
 *
 * Covers the cache and handler shapes: due ticks run, pending ticks
 * wait, first runs honor the immediate flag, and failures report through the
 * handler. The array store stands in for the Swoole-backed store; every test
 * skips cleanly where the extension is absent.
 */
class InvokeTickCallableTest extends TestCase
{
    /**
     * Requires the Swoole extension before each test.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        if (!extension_loaded('swoole') && !extension_loaded('openswoole')) {
            $this->markTestSkipped('The Swoole or OpenSwoole extension is not installed on this host.');
        }
    }

    /**
     * Due callbacks run and refresh the timestamp.
     *
     * @return void
     */
    public function testCallableIsInvokedWhenDue(): void
    {
        $calls = 0;

        $cache = new StimulusArrayStore();

        $cache->forever('tick-key', time() - 100);

        $instance = new InvokeTickCallable(
            'key',
            static function () use (&$calls): void {
                $calls++;
            },
            1,
            true,
            $cache,
            static function (): void {
            },
        );

        $instance();

        $this->assertSame(1, $calls);
        $this->assertGreaterThanOrEqual($before = time() - 1, $cache->get('tick-key'));
        $this->assertLessThanOrEqual(time(), $cache->get('tick-key'));
    }

    /**
     * Pending callbacks wait without refreshing the timestamp.
     *
     * @return void
     */
    public function testCallableIsNotInvokedWhenNotDue(): void
    {
        $calls = 0;

        $cache = new StimulusArrayStore();

        $cache->forever('tick-key', $stamped = time() - 10);

        $instance = new InvokeTickCallable(
            'key',
            static function () use (&$calls): void {
                $calls++;
            },
            30,
            true,
            $cache,
            static function (): void {
            },
        );

        $instance();

        $this->assertSame(0, $calls);
        $this->assertSame($stamped, $cache->get('tick-key'));
    }

    /**
     * First runs invoke immediate callbacks.
     *
     * @return void
     */
    public function testCallableIsInvokedWhenFirstRunAndImmediate(): void
    {
        $calls = 0;

        $cache = new StimulusArrayStore();

        $instance = new InvokeTickCallable(
            'key',
            static function () use (&$calls): void {
                $calls++;
            },
            1,
            true,
            $cache,
            static function (): void {
            },
        );

        $instance();

        $this->assertSame(1, $calls);
        $this->assertGreaterThanOrEqual(time() - 1, $cache->get('tick-key'));
        $this->assertLessThanOrEqual(time(), $cache->get('tick-key'));
    }

    /**
     * First runs skip deferred callbacks while stamping the timestamp.
     *
     * @return void
     */
    public function testCallableIsNotInvokedWhenFirstRunAndNotImmediate(): void
    {
        $calls = 0;

        $cache = new StimulusArrayStore();

        $instance = new InvokeTickCallable(
            'key',
            static function () use (&$calls): void {
                $calls++;
            },
            1,
            false,
            $cache,
            static function (): void {
            },
        );

        $instance();

        $this->assertSame(0, $calls);
        $this->assertGreaterThanOrEqual(time() - 1, $cache->get('tick-key'));
        $this->assertLessThanOrEqual(time(), $cache->get('tick-key'));
    }

    /**
     * Listener failures report through the handler callback.
     *
     * @return void
     */
    public function testFailuresReportThroughExceptionHandler(): void
    {
        $reported = [];

        $instance = new InvokeTickCallable(
            'key',
            static function (): never {
                throw new Exception('Tick failure.');
            },
            1,
            true,
            new StimulusArrayStore(),
            static function (Throwable $throwable) use (&$reported): void {
                $reported[] = $throwable;
            },
        );

        $instance();

        $this->assertCount(1, $reported);
        $this->assertSame('Tick failure.', $reported[0]->getMessage());
    }

    /**
     * Listener failures report through handler objects.
     *
     * @return void
     */
    public function testFailuresReportThroughHandlerObjects(): void
    {
        $handler = new FakeTickExceptionHandler();

        $instance = new InvokeTickCallable(
            'key',
            static function (): never {
                throw new Exception('Tick failure.');
            },
            1,
            true,
            new StimulusArrayStore(),
            $handler,
        );

        $instance();

        $this->assertCount(1, $handler->reported);
    }

    /**
     * Interval and immediacy stay fluent.
     *
     * @return void
     */
    public function testFluentConfiguration(): void
    {
        $instance = new InvokeTickCallable(
            'key',
            static function (): void {
            },
            1,
            false,
            new StimulusArrayStore(),
            static function (): void {
            },
        );

        $this->assertSame($instance, $instance->seconds(60));
        $this->assertSame($instance, $instance->immediate());
    }
}
