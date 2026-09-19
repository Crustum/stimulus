<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Bridge\Swoole;

use Crustum\Stimulus\Bridge\Swoole\SwooleHttpTaskDispatcher;
use Crustum\Stimulus\Dispatch\SequentialTaskDispatcher;
use Crustum\Stimulus\Exception\TaskException;
use Crustum\Stimulus\Exception\TaskTimeoutException;
use Crustum\Stimulus\Test\TestCase;
use Exception;
use RuntimeException;

/**
 * Tests for the Swoole HTTP task dispatcher.
 *
 * Covers the injected HTTP callable: resolution, dispatch, fallback
 * dispatching, exception propagation, and timeouts. The HTTP layer arrives
 * as in-memory callables so the suite loads without the extension; every
 * test skips cleanly where the extension is absent.
 */
class SwooleHttpTaskDispatcherTest extends TestCase
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
     * Tasks resolve through the task server.
     *
     * @return void
     */
    public function testTasksCanBeResolvedViaHttp(): void
    {
        $requests = [];

        $dispatcher = new SwooleHttpTaskDispatcher(
            '127.0.0.1',
            '8000',
            new SequentialTaskDispatcher(),
            static function (string $url, array $data) use (&$requests): array {
                $requests[] = [$url, $data];

                return ['status' => 200, 'body' => serialize(['first' => 1, 'second' => 2, 'third' => null])];
            },
        );

        $this->assertSame(
            ['first' => 1, 'second' => 2, 'third' => null],
            $dispatcher->resolve([
                'first' => static fn(): int => 1,
                'second' => static fn(): int => 2,
                'third' => static function (): void {
                },
            ]),
        );
        $this->assertSame('http://127.0.0.1:8000/stimulus/resolve-tasks', $requests[0][0]);
    }

    /**
     * Tasks dispatch through the task server.
     *
     * @return void
     */
    public function testTasksCanBeDispatchedViaHttp(): void
    {
        $requests = [];

        $dispatcher = new SwooleHttpTaskDispatcher(
            '127.0.0.1',
            '8000',
            new SequentialTaskDispatcher(),
            static function (string $url, array $data) use (&$requests): array {
                $requests[] = [$url, $data];

                return ['status' => 200, 'body' => ''];
            },
        );

        $dispatcher->dispatch([
            'first' => static fn(): int => 1,
            'second' => static fn(): int => 2,
        ]);

        $this->assertSame('http://127.0.0.1:8000/stimulus/dispatch-tasks', $requests[0][0]);
    }

    /**
     * Unreachable task servers fall back to the sequential dispatcher.
     *
     * @return void
     */
    public function testTasksCanBeResolvedViaFallbackDispatcher(): void
    {
        $dispatcher = new SwooleHttpTaskDispatcher(
            '127.0.0.1',
            '8000',
            new SequentialTaskDispatcher(),
            static function (): never {
                throw new RuntimeException('Connection refused.');
            },
        );

        $this->assertSame(
            ['first' => 1, 'second' => 2],
            $dispatcher->resolve([
                'first' => static fn(): int => 1,
                'second' => static fn(): int => 2,
            ]),
        );
    }

    /**
     * Undispatchable tasks run through the fallback dispatcher.
     *
     * @return void
     */
    public function testTasksCanBeDispatchedViaFallbackDispatcher(): void
    {
        $calls = 0;

        $dispatcher = new SwooleHttpTaskDispatcher(
            '127.0.0.1',
            '8000',
            new SequentialTaskDispatcher(),
            static function (): never {
                throw new RuntimeException('Connection refused.');
            },
        );

        $dispatcher->dispatch([
            'first' => static function () use (&$calls): void {
                $calls++;
            },
        ]);

        $this->assertSame(1, $calls);
    }

    /**
     * Invalid task server responses raise task exceptions.
     *
     * @return void
     */
    public function testResolvingTasksPropagateExceptions(): void
    {
        $dispatcher = new SwooleHttpTaskDispatcher(
            '127.0.0.1',
            '8000',
            new SequentialTaskDispatcher(),
            static fn(): array => ['status' => 500, 'body' => ''],
        );

        $this->expectException(TaskException::class);
        $this->expectExceptionMessage('Invalid response from task server.');

        $dispatcher->resolve(['first' => static function (): never {
            throw new Exception('Something went wrong.');
        }]);
    }

    /**
     * Failing dispatches never propagate exceptions.
     *
     * @return void
     */
    public function testDispatchingTasksDoNotPropagateExceptions(): void
    {
        $dispatcher = new SwooleHttpTaskDispatcher(
            '127.0.0.1',
            '8000',
            new SequentialTaskDispatcher(),
            static fn(): array => ['status' => 500, 'body' => ''],
        );

        $dispatcher->dispatch(['first' => static function (): never {
            throw new Exception('Something went wrong.');
        }]);

        $this->assertTrue(true);
    }

    /**
     * Gateway timeouts raise task timeouts.
     *
     * @return void
     */
    public function testResolvingTasksMayTimeout(): void
    {
        $dispatcher = new SwooleHttpTaskDispatcher(
            '127.0.0.1',
            '8000',
            new SequentialTaskDispatcher(),
            static fn(): array => ['status' => 504, 'body' => ''],
        );

        $this->expectException(TaskTimeoutException::class);
        $this->expectExceptionMessage('Task timed out after 2000 milliseconds.');

        $dispatcher->resolve(['first' => static fn(): int => 1], 2000);
    }
}
