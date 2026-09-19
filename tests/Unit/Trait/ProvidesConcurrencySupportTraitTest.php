<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Trait;

use Crustum\Stimulus\Dispatch\SequentialTaskDispatcher;
use Crustum\Stimulus\Exception\TaskException;
use Crustum\Stimulus\Test\Support\ConcurrencySupportTraitHost;
use Crustum\Stimulus\Test\TestCase;
use Exception;

/**
 * Tests for the concurrency support trait.
 *
 * The sequential dispatcher scenarios live in the dispatcher suite; this
 * suite asserts the concurrent resolution, dispatcher selection, and Swoole
 * fallback behavior.
 */
class ProvidesConcurrencySupportTraitTest extends TestCase
{
    /**
     * Empty task lists resolve without involving a dispatcher.
     *
     * @return void
     */
    public function testConcurrentlyResolvesEmptyTasks(): void
    {
        $this->assertSame([], (new ConcurrencySupportTraitHost())->concurrently([]));
    }

    /**
     * Tasks resolve through the sequential dispatcher by default.
     *
     * @return void
     */
    public function testConcurrentlyResolvesTasks(): void
    {
        $results = (new ConcurrencySupportTraitHost())->concurrently([
            'first' => fn(): string => 'foo',
            'second' => fn(): string => 'bar',
        ]);

        $this->assertSame(['first' => 'foo', 'second' => 'bar'], $results);
    }

    /**
     * Task failures rethrow through the concurrent resolution.
     *
     * @return void
     */
    public function testConcurrentlyRethrowsTaskFailures(): void
    {
        $this->expectException(TaskException::class);

        (new ConcurrencySupportTraitHost())->concurrently([
            'first' => function (): void {
                throw new Exception('Something went wrong.');
            },
        ]);
    }

    /**
     * The default dispatcher is sequential without an injected dispatcher.
     *
     * Swoole dispatchers activate only through explicit injection by worker
     * entries holding the server, so the default stays sequential.
     *
     * @return void
     */
    public function testTasksFallsBackToSequentialDispatcher(): void
    {
        $this->assertInstanceOf(SequentialTaskDispatcher::class, (new ConcurrencySupportTraitHost())->tasks());
    }
}
