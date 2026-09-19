<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Listener;

use Cake\Event\EventListenerInterface;
use Crustum\Stimulus\Contracts\StoppableClient;
use Crustum\Stimulus\Event\WorkerErrorOccurredEvent;

/**
 * Stops the worker once it served its share of failing operations.
 *
 * Counts the observed operation errors and stops the sandbox once the configured maximum is reached, so a repeatedly failing
 * worker terminates instead of looping forever. Sandboxes outside the
 * stoppable client contract are counted but never stopped. True
 * request-count based termination is wired by the worker in a later phase.
 *
 * Listened on the worker error occurred event.
 */
class StopWorkerIfNecessary implements EventListenerInterface
{
    /**
     * Maximum operation errors tolerated before the worker stops, zero disables stopping.
     *
     * @var int
     */
    protected int $maxRequests;

    /**
     * Operation errors observed so far.
     *
     * @var int
     */
    protected int $handledErrors = 0;

    /**
     * Creates a new worker stopping listener.
     *
     * @param int $maxRequests Maximum operation errors tolerated before the worker stops, zero disables stopping.
     */
    public function __construct(int $maxRequests = 500)
    {
        $this->maxRequests = $maxRequests;
    }

    /**
     * Returns the events this listener handles.
     *
     * @return array<string, mixed> Event names mapped to handler methods.
     */
    public function implementedEvents(): array
    {
        return [
            'Stimulus.workerErrorOccurred' => 'handleWorkerErrorOccurred',
        ];
    }

    /**
     * Counts the failed operation and stops the sandbox once the maximum is reached.
     *
     * @param \Crustum\Stimulus\Event\WorkerErrorOccurredEvent $event Failed worker operation.
     * @return void
     */
    public function handleWorkerErrorOccurred(WorkerErrorOccurredEvent $event): void
    {
        $this->handledErrors++;

        if ($this->maxRequests <= 0 || $this->handledErrors < $this->maxRequests) {
            return;
        }

        $sandbox = $event->getSandbox();

        if ($sandbox instanceof StoppableClient) {
            $sandbox->stop();
        }
    }
}
