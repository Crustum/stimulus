<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Bridge\Swoole;

use Crustum\Stimulus\Bridge\Swoole\SwooleCoroutineDispatcher;
use Crustum\Stimulus\Test\TestCase;

/**
 * Tests for the Swoole coroutine dispatcher.
 *
 * Covers coroutine resolution on extension hosts; without the extension the
 * dispatcher delegates to the sequential dispatcher. Every test skips
 * cleanly where the extension is absent.
 */
class SwooleCoroutineDispatcherTest extends TestCase
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
     * Coroutines resolve keyed by their given keys.
     *
     * @return void
     */
    public function testCoroutinesCanBeResolved(): void
    {
        $dispatcher = new SwooleCoroutineDispatcher();

        $this->assertSame(
            ['first' => 1, 'second' => 2],
            $dispatcher->resolve([
                'first' => static fn(): int => 1,
                'second' => static fn(): int => 2,
            ]),
        );
    }

    /**
     * Coroutine contexts reuse the calling context.
     *
     * @return void
     */
    public function testCoroutinesResolveWithinCoroutineContexts(): void
    {
        $dispatcher = new SwooleCoroutineDispatcher(true);

        $this->assertSame(
            ['first' => 1],
            $dispatcher->resolve(['first' => static fn(): int => 1]),
        );
    }

    /**
     * Coroutines resolve through the OpenSwoole runtime where installed.
     *
     * The dispatcher prefers Swoole and falls back to OpenSwoole, so this
     * scenario runs the OpenSwoole path once the Swoole extension is out of
     * the picture; hosts without OpenSwoole skip cleanly.
     *
     * @return void
     */
    public function testCoroutinesResolveThroughOpenSwooleRuntime(): void
    {
        if (!extension_loaded('openswoole') || !class_exists('OpenSwoole\\Coroutine')) {
            $this->markTestSkipped('The OpenSwoole extension is not installed on this host.');
        }

        $dispatcher = new SwooleCoroutineDispatcher();

        $this->assertSame(
            ['first' => 1, 'second' => 2],
            $dispatcher->resolve([
                'first' => static fn(): int => 1,
                'second' => static fn(): int => 2,
            ]),
        );
    }
}
