<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Listener;

use Cake\Core\Configure;
use Cake\Event\EventListenerInterface;
use Crustum\Stimulus\Contracts\OperationTerminated;

/**
 * Collects garbage between worker operations.
 *
 * Reads the `Stimulus.garbage` configuration key and collects cycles past
 * it when memory usage passes the megabyte threshold, which keeps
 * long-lived workers from growing without bound.
 *
 * Listened on operation terminated operations.
 */
class CollectGarbage implements EventListenerInterface
{
    /**
     * Returns the events this listener handles.
     *
     * @return array<string, mixed> Event names mapped to handler methods.
     */
    public function implementedEvents(): array
    {
        return [
            'Stimulus.requestTerminated' => 'handleOperationTerminated',
            'Stimulus.taskTerminated' => 'handleOperationTerminated',
            'Stimulus.tickTerminated' => 'handleOperationTerminated',
        ];
    }

    /**
     * Collects cycles when memory usage passes the configured threshold.
     *
     * @param \Crustum\Stimulus\Contracts\OperationTerminated $event Terminated worker operation.
     * @return void
     */
    public function handleOperationTerminated(OperationTerminated $event): void
    {
        $threshold = Configure::read('Stimulus.garbage');
        $garbage = is_numeric($threshold) ? (int)$threshold : 0;

        if ($garbage > 0 && (memory_get_usage() / 1024 / 1024) > $garbage) {
            gc_collect_cycles();
        }
    }
}
