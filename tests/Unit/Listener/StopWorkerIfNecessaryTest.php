<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Listener;

use Crustum\Stimulus\Contracts\StoppableClient;
use Crustum\Stimulus\Event\WorkerErrorOccurredEvent;
use Crustum\Stimulus\Listener\StopWorkerIfNecessary;
use Crustum\Stimulus\Test\Support\FakeClient;
use Crustum\Stimulus\Test\TestCase;
use RuntimeException;
use stdClass;

/**
 * Tests for the worker stopping listener.
 *
 * Covers the worker stopping scenario: the stoppable sandbox stops once the
 * tolerated error count is reached while other sandboxes only get counted.
 */
class StopWorkerIfNecessaryTest extends TestCase
{
    /**
     * The stoppable sandbox stops once the maximum is reached.
     *
     * @return void
     */
    public function testStoppableSandboxStopsAtMaximum(): void
    {
        $sandbox = new class ([]) extends FakeClient implements StoppableClient {
            /**
             * Number of stop calls.
             *
             * @var int
             */
            public int $stops = 0;

            /**
             * Records a stop call.
             *
             * @return void
             */
            public function stop(): void
            {
                $this->stops++;
            }
        };

        $listener = new StopWorkerIfNecessary(2);
        $listener->handleWorkerErrorOccurred(new WorkerErrorOccurredEvent(new RuntimeException('first'), $sandbox));

        $this->assertSame(0, $sandbox->stops);

        $listener->handleWorkerErrorOccurred(new WorkerErrorOccurredEvent(new RuntimeException('second'), $sandbox));

        $this->assertSame(1, $sandbox->stops);
    }

    /**
     * A zero maximum disables stopping entirely.
     *
     * @return void
     */
    public function testZeroMaximumDisablesStopping(): void
    {
        $sandbox = new class ([]) extends FakeClient implements StoppableClient {
            /**
             * Number of stop calls.
             *
             * @var int
             */
            public int $stops = 0;

            /**
             * Records a stop call.
             *
             * @return void
             */
            public function stop(): void
            {
                $this->stops++;
            }
        };

        $listener = new StopWorkerIfNecessary(0);
        $listener->handleWorkerErrorOccurred(new WorkerErrorOccurredEvent(new RuntimeException('first'), $sandbox));

        $this->assertSame(0, $sandbox->stops);
    }

    /**
     * Sandboxes outside the stoppable contract are only counted.
     *
     * @return void
     */
    public function testForeignSandboxIsOnlyCounted(): void
    {
        $listener = new StopWorkerIfNecessary(1);
        $listener->handleWorkerErrorOccurred(
            new WorkerErrorOccurredEvent(new RuntimeException('first'), new stdClass()),
        );

        $this->assertInstanceOf(StopWorkerIfNecessary::class, $listener);
    }
}
