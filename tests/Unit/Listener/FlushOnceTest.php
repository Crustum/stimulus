<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Listener;

use Crustum\Stimulus\Contracts\OperationTerminated;
use Crustum\Stimulus\Event\TaskTerminatedEvent;
use Crustum\Stimulus\Listener\FlushOnce;
use Crustum\Stimulus\Test\TestCase;
use stdClass;

/**
 * Tests for the call-site memoization flushing listener.
 *
 * The listener keeps its pipeline shape without resetting any state. The worker memoization
 * scenario is recorded as deferred to the worker phase.
 */
class FlushOnceTest extends TestCase
{
    /**
     * Terminated operations pass through with their instances intact.
     *
     * @return void
     */
    public function testTerminatedOperationPassesThrough(): void
    {
        $app = new stdClass();
        $sandbox = new stdClass();
        $event = new TaskTerminatedEvent($app, $sandbox, null, null);

        (new FlushOnce())->handleOperationTerminated($event);

        $this->assertInstanceOf(OperationTerminated::class, $event);
        $this->assertSame($sandbox, $event->sandbox());
    }
}
