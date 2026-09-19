<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Dispatch;

use Crustum\Stimulus\Dispatch\SequentialTaskDispatcher;
use Crustum\Stimulus\Exception\DdException;
use Crustum\Stimulus\Exception\TaskException;
use Crustum\Stimulus\Test\TestCase;
use Exception;

/**
 * Tests for the sequential task dispatcher.
 *
 * This dispatcher runs eagerly and needs no application instance.
 */
class SequentialTaskDispatcherTest extends TestCase
{
    /**
     * Tasks resolve eagerly with results keyed by their given keys.
     *
     * @return void
     */
    public function testTasksCanBeResolved(): void
    {
        $dispatcher = new SequentialTaskDispatcher();

        $this->assertEquals([
            'first' => 1,
            'second' => 2,
            'third' => null,
        ], $dispatcher->resolve([
            'first' => fn(): int => 1,
            'second' => fn(): int => 2,
            'third' => function (): void {
            },
        ]));
    }

    /**
     * A failing task does not prevent the remaining tasks from running.
     *
     * @return void
     */
    public function testResolvingTasksWithExceptionsDoNotEffectOtherTasks(): void
    {
        $dispatcher = new SequentialTaskDispatcher();

        $secondRan = false;

        try {
            $dispatcher->resolve([
                'first' => fn() => throw new Exception('Something went wrong'),
                'second' => function () use (&$secondRan): void {
                    $secondRan = true;
                },
            ]);
        } catch (TaskException) {
        }

        $this->assertTrue($secondRan);
    }

    /**
     * Dispatching tasks never propagates task exceptions to the caller.
     *
     * @return void
     */
    public function testDispatchingTasksDoNotPropagateExceptions(): void
    {
        $dispatcher = new SequentialTaskDispatcher();

        $dispatcher->dispatch([
            'first' => fn() => throw new Exception('Something went wrong'),
        ]);

        $this->assertTrue(true);
    }

    /**
     * Dispatched tasks run eagerly in the current process.
     *
     * @return void
     */
    public function testTasksCanBeDispatched(): void
    {
        $dispatcher = new SequentialTaskDispatcher();

        $first = null;
        $second = null;

        $dispatcher->dispatch([
            'first' => function () use (&$first): void {
                $first = 'first';
            },
            'second' => function () use (&$second): void {
                $second = 'second';
            },
        ]);

        $this->assertEquals('first', $first);
        $this->assertEquals('second', $second);
    }

    /**
     * Resolving tasks rethrows the first task failure to the caller.
     *
     * @return void
     */
    public function testResolvingTasksPropagateExceptions(): void
    {
        $dispatcher = new SequentialTaskDispatcher();

        $this->expectException(TaskException::class);
        $this->expectExceptionMessage('Something went wrong.');

        $dispatcher->resolve([
            'first' => fn() => throw new Exception('Something went wrong.'),
        ]);
    }

    /**
     * Resolving tasks rethrows debug dump calls as dump exceptions.
     *
     * @return void
     */
    public function testResolvingTasksPropagateDdCalls(): void
    {
        $dispatcher = new SequentialTaskDispatcher();

        $this->expectException(DdException::class);
        $this->expectExceptionMessage((string)json_encode(['foo' => 'bar']));

        $dispatcher->resolve([
            'first' => fn() => throw new DdException(['foo' => 'bar']),
        ]);
    }

    /**
     * Each task runs exactly once with integer keys preserved.
     *
     * @return void
     */
    public function testEachTaskRunsExactlyOnce(): void
    {
        $dispatcher = new SequentialTaskDispatcher();
        $runs = [];

        $results = $dispatcher->resolve([
            0 => function () use (&$runs): int {
                $runs[] = 'first';

                return 1;
            },
            2 => function () use (&$runs): int {
                $runs[] = 'second';

                return 2;
            },
        ]);

        $this->assertSame([0 => 1, 2 => 2], $results);
        $this->assertSame(['first', 'second'], $runs);
    }
}
