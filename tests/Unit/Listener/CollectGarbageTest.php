<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Listener;

use Cake\Core\Configure;
use Crustum\Stimulus\Event\TaskTerminatedEvent;
use Crustum\Stimulus\Listener\CollectGarbage;
use Crustum\Stimulus\Test\TestCase;
use stdClass;

/**
 * Tests for the garbage collecting listener.
 */
class CollectGarbageTest extends TestCase
{
    /**
     * Threshold branches execute without disturbing the operation.
     *
     * @return void
     */
    public function testGarbageThresholdBranches(): void
    {
        $event = new TaskTerminatedEvent(new stdClass(), new stdClass(), null, null);
        $listener = new CollectGarbage();

        Configure::write('Stimulus.garbage', 0);
        $listener->handleOperationTerminated($event);

        Configure::write('Stimulus.garbage', PHP_INT_MAX);
        $listener->handleOperationTerminated($event);

        Configure::write('Stimulus.garbage', 1);
        $listener->handleOperationTerminated($event);

        Configure::write('Stimulus.garbage', 'unlimited');
        $listener->handleOperationTerminated($event);

        Configure::delete('Stimulus.garbage');
        $listener->handleOperationTerminated($event);

        $this->assertGreaterThan(0, memory_get_usage());
    }
}
