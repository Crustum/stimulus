<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Bridge\Swoole;

use Crustum\Stimulus\Bridge\Swoole\SwooleTaskDispatcher;
use Crustum\Stimulus\Bridge\Swoole\TaskResult;
use Crustum\Stimulus\Exception\DdException;
use Crustum\Stimulus\Exception\TaskException;
use Crustum\Stimulus\Exception\TaskExceptionResult;
use Crustum\Stimulus\Exception\TaskTimeoutException;
use Crustum\Stimulus\Test\Support\FakeTaskServer;
use Crustum\Stimulus\Test\TestCase;
use Exception;
use InvalidArgumentException;

/**
 * Tests for the Swoole task dispatcher.
 *
 * Covers the injected server: context guards, timeouts, exception and dump
 * propagation, silent dispatch, and result mapping. The Swoole server
 * arrives as an in-memory fake so the suite loads without the extension;
 * every test skips cleanly where the extension is absent.
 */
class SwooleTaskDispatcherTest extends TestCase
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
     * Tasks resolve only within a Swoole server context.
     *
     * @return void
     */
    public function testTasksCanOnlyBeResolvedViaServerContext(): void
    {
        $dispatcher = new SwooleTaskDispatcher();

        $this->expectException(InvalidArgumentException::class);

        $dispatcher->resolve(['first' => static fn(): int => 1]);
    }

    /**
     * Tasks dispatch only within a Swoole server context.
     *
     * @return void
     */
    public function testTasksCanOnlyBeDispatchedViaServerContext(): void
    {
        $dispatcher = new SwooleTaskDispatcher();

        $this->expectException(InvalidArgumentException::class);

        $dispatcher->dispatch(['first' => static fn(): int => 1]);
    }

    /**
     * Unfinished tasks raise a timeout.
     *
     * @return void
     */
    public function testResolvingTasksMayTimeout(): void
    {
        $server = new FakeTaskServer();

        $server->taskResults = false;

        $dispatcher = new SwooleTaskDispatcher($server);

        $this->expectException(TaskTimeoutException::class);
        $this->expectExceptionMessage('Task timed out after 2000 milliseconds.');

        $dispatcher->resolve(['first' => static fn(): int => 1], 2000);
    }

    /**
     * Failed tasks rethrow the original exception.
     *
     * @return void
     */
    public function testResolvingTasksPropagateExceptions(): void
    {
        $server = new FakeTaskServer();

        $server->taskResults = [TaskExceptionResult::from(new Exception('Something went wrong'))];

        $dispatcher = new SwooleTaskDispatcher($server);

        $this->expectException(TaskException::class);
        $this->expectExceptionMessage('Something went wrong');

        $dispatcher->resolve(['first' => static fn(): int => 1]);
    }

    /**
     * Dumped tasks rethrow the dump exception.
     *
     * @return void
     */
    public function testResolvingTasksPropagateDdCalls(): void
    {
        $server = new FakeTaskServer();

        $server->taskResults = [TaskExceptionResult::from(new DdException(['foo' => 'bar']))];

        $dispatcher = new SwooleTaskDispatcher($server);

        $this->expectException(DdException::class);
        $this->expectExceptionMessage((string)json_encode(['foo' => 'bar']));

        $dispatcher->resolve(['first' => static fn(): array => throw new DdException(['foo' => 'bar'])]);
    }

    /**
     * Dispatched tasks never propagate exceptions.
     *
     * @return void
     */
    public function testDispatchingTasksDoNotPropagateExceptions(): void
    {
        $server = new FakeTaskServer();

        $dispatcher = new SwooleTaskDispatcher($server);

        $dispatcher->dispatch(['first' => static function (): never {
            throw new Exception('Something went wrong.');
        }]);

        $this->assertCount(1, $server->dispatched);
    }

    /**
     * Finished tasks map back onto their keys.
     *
     * @return void
     */
    public function testTasksCanBeResolved(): void
    {
        $server = new FakeTaskServer();

        $server->taskResults = [new TaskResult(1), new TaskResult(2)];

        $dispatcher = new SwooleTaskDispatcher($server);

        $this->assertSame(
            ['first' => 1, 'second' => 2],
            $dispatcher->resolve([
                'first' => static fn(): int => 1,
                'second' => static fn(): int => 2,
            ]),
        );
    }

    /**
     * Unfinished tasks resolve to false.
     *
     * @return void
     */
    public function testUnfinishedTasksResolveToFalse(): void
    {
        $server = new FakeTaskServer();

        $server->taskResults = [new TaskResult(1)];

        $dispatcher = new SwooleTaskDispatcher($server);

        $this->assertSame(
            ['first' => 1, 'second' => false],
            $dispatcher->resolve([
                'first' => static fn(): int => 1,
                'second' => static fn(): int => 2,
            ]),
        );
    }

    /**
     * The server callback resolves lazily on dispatch.
     *
     * @return void
     */
    public function testServerCallbackResolvesLazily(): void
    {
        $server = new FakeTaskServer();

        $server->taskResults = [new TaskResult(1)];

        $dispatcher = new SwooleTaskDispatcher(static fn(): FakeTaskServer => $server);

        $this->assertSame(['first' => 1], $dispatcher->resolve(['first' => static fn(): int => 1]));
    }
}
