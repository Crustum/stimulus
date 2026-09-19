<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Listener;

use Cake\Collection\Collection;
use Cake\Core\Configure;
use Cake\Event\EventListenerInterface;
use Crustum\Stimulus\Contracts\OperationTerminated;
use Crustum\Stimulus\Event\RequestReceivedEvent;
use Crustum\Stimulus\Event\TaskReceivedEvent;
use Crustum\Stimulus\Event\TickReceivedEvent;
use Crustum\Stimulus\Event\WorkerErrorOccurredEvent;

/**
 * Sandboxes configuration between worker requests.
 *
 * Configuration lives in process-global static state shared across operations,
 * so this listener snapshots the whole
 * configuration when the operation starts and restores the snapshot once
 * the operation terminates or fails, so writes during one operation never
 * leak into the next. This gives the sandbox private configuration
 * semantics through the worker event flow.
 *
 * Listened on the request, task, and tick received events for snapshots and
 * on the terminated plus worker error events for restores.
 */
class CreateConfigurationSandbox implements EventListenerInterface
{
    /**
     * Configuration snapshots keyed by sandbox object identifier.
     *
     * @var array<int, mixed>
     */
    protected array $snapshots = [];

    /**
     * Returns the events this listener handles.
     *
     * @return array<string, mixed> Event names mapped to handler methods.
     */
    public function implementedEvents(): array
    {
        return [
            'Stimulus.requestReceived' => 'handleRequestReceived',
            'Stimulus.taskReceived' => 'handleTaskReceived',
            'Stimulus.tickReceived' => 'handleTickReceived',
            'Stimulus.requestTerminated' => 'handleOperationTerminated',
            'Stimulus.taskTerminated' => 'handleOperationTerminated',
            'Stimulus.tickTerminated' => 'handleOperationTerminated',
            'Stimulus.workerErrorOccurred' => 'handleWorkerErrorOccurred',
        ];
    }

    /**
     * Snapshots the current configuration for the sandbox.
     *
     * @param \Crustum\Stimulus\Event\RequestReceivedEvent $event Received request operation.
     * @return void
     */
    public function handleRequestReceived(RequestReceivedEvent $event): void
    {
        $this->snapshots[spl_object_id($event->getSandbox())] = Configure::read();
    }

    /**
     * Snapshots the current configuration for the task sandbox.
     *
     * @param \Crustum\Stimulus\Event\TaskReceivedEvent $event Received task operation.
     * @return void
     */
    public function handleTaskReceived(TaskReceivedEvent $event): void
    {
        $this->snapshots[spl_object_id($event->getSandbox())] = Configure::read();
    }

    /**
     * Snapshots the current configuration for the tick sandbox.
     *
     * @param \Crustum\Stimulus\Event\TickReceivedEvent $event Received tick operation.
     * @return void
     */
    public function handleTickReceived(TickReceivedEvent $event): void
    {
        $this->snapshots[spl_object_id($event->getSandbox())] = Configure::read();
    }

    /**
     * Restores the snapshotted configuration once the operation terminates.
     *
     * @param \Crustum\Stimulus\Contracts\OperationTerminated $event Terminated worker operation.
     * @return void
     */
    public function handleOperationTerminated(OperationTerminated $event): void
    {
        $this->restoreSandboxConfiguration($event->sandbox());
    }

    /**
     * Restores the snapshotted configuration once the operation fails.
     *
     * The worker error path never reaches the terminated events, so failed
     * operations restore here instead of leaking writes into the next
     * operation.
     *
     * @param \Crustum\Stimulus\Event\WorkerErrorOccurredEvent $event Failed worker operation.
     * @return void
     */
    public function handleWorkerErrorOccurred(WorkerErrorOccurredEvent $event): void
    {
        $this->restoreSandboxConfiguration($event->getSandbox());
    }

    /**
     * Restores the snapshotted configuration for the sandbox.
     *
     * Clears the process-global keys and rewrites the snapshot; clearing
     * iterates through a collection pipeline.
     *
     * @param object $sandbox Sandbox application instance whose configuration is restored.
     * @return void
     */
    public function restoreSandboxConfiguration(object $sandbox): void
    {
        $identifier = spl_object_id($sandbox);

        if (!array_key_exists($identifier, $this->snapshots)) {
            return;
        }

        $snapshot = $this->snapshots[$identifier];
        unset($this->snapshots[$identifier]);

        if (!is_array($snapshot)) {
            return;
        }

        $current = Configure::read();

        if (is_array($current)) {
            (new Collection(array_keys($current)))->each(function ($key): void {
                Configure::delete((string)$key);
            });
        }

        Configure::write($snapshot);
    }
}
