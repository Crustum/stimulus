<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Listener;

use Cake\Event\EventListenerInterface;
use Cake\Log\Log;
use Crustum\Stimulus\Event\WorkerErrorOccurredEvent;
use Crustum\Stimulus\Exception\DdException;

/**
 * Reports worker operation exceptions.
 *
 * Writes the exception to the log and forwards it to the sandbox when it
 * exposes the worker exception hook, skipping dump-and-die exceptions, which the worker
 * exception inspector of a later phase implements.
 *
 * Listened on the worker error occurred event.
 */
class ReportException implements EventListenerInterface
{
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
     * Logs the operation exception and forwards it to the sandbox hook.
     *
     * @param \Crustum\Stimulus\Event\WorkerErrorOccurredEvent $event Failed worker operation.
     * @return void
     */
    public function handleWorkerErrorOccurred(WorkerErrorOccurredEvent $event): void
    {
        $exception = $event->getException();

        if ($exception instanceof DdException) {
            return;
        }

        Log::error($exception::class . ': ' . $exception->getMessage(), ['code' => $exception->getCode()]);

        $sandbox = $event->getSandbox();

        if (method_exists($sandbox, 'reportException')) {
            $sandbox->reportException($exception);
        }
    }
}
