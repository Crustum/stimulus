<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Listener;

use Cake\Event\EventListenerInterface;
use Crustum\Stimulus\Contracts\OperationTerminated;

/**
 * Flushes call-site memoization between worker operations.
 *
 * Flushes the `once` helper memoization so callables remembered during one
 * operation are recomputed in the next. A long-lived worker holds no such
 * memoized state, so this listener intentionally performs no reset. The class is kept
 * so the operation-terminated pipeline keeps its shape and host code can
 * subclass it when needed.
 *
 * Listened on operation terminated operations.
 */
class FlushOnce implements EventListenerInterface
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
     * Handles the terminated operation without resetting any state.
     *
     * @param \Crustum\Stimulus\Contracts\OperationTerminated $event Terminated worker operation.
     * @return void
     */
    public function handleOperationTerminated(OperationTerminated $event): void
    {
    }
}
