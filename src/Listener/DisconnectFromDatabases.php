<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Listener;

use Cake\Core\Configure;
use Cake\Database\Connection;
use Cake\Datasource\ConnectionManager;
use Cake\Datasource\Exception\MissingDatasourceConfigException;
use Cake\Event\EventListenerInterface;
use Crustum\Stimulus\Contracts\OperationTerminated;

/**
 * Disconnects databases between worker operations.
 *
 * Disconnects the driver of every known connection, which drops
 * the underlying PDO handle while keeping the configuration so the next
 * operation reconnects lazily. Names that are not configured are skipped.
 *
 * The connection names are injected. A null list falls back to the
 * `Stimulus.listeners.connections` configuration and finally to the primary
 * connection.
 *
 * Listened on operation terminated operations.
 */
class DisconnectFromDatabases implements EventListenerInterface
{
    /**
     * Connection names that are disconnected.
     *
     * @var list<string>
     */
    protected array $connections;

    /**
     * Creates a new database disconnecting listener.
     *
     * @param list<string>|null $connections Connection names that are disconnected, null reads the `Stimulus.listeners.connections` configuration.
     */
    public function __construct(?array $connections = null)
    {
        $this->connections = $connections ?? $this->readConfiguredConnections();
    }

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
     * Disconnects the driver of every known connection.
     *
     * Iterates over the configured connection names.
     *
     * @param \Crustum\Stimulus\Contracts\OperationTerminated $event Terminated worker operation.
     * @return void
     */
    public function handleOperationTerminated(OperationTerminated $event): void
    {
        foreach ($this->connections as $name) {
            try {
                $connection = ConnectionManager::get($name);
            } catch (MissingDatasourceConfigException) {
                continue;
            }

            if (!$connection instanceof Connection) {
                continue;
            }

            $connection->getDriver()->disconnect();
        }
    }

    /**
     * Reads the configured worker connection names.
     *
     * Falls back to the primary connection when the host application
     * configures no explicit list.
     *
     * @return list<string> Configured worker connection names.
     */
    protected function readConfiguredConnections(): array
    {
        $configured = Configure::read('Stimulus.listeners.connections', ['default']);

        if (!is_array($configured)) {
            return ['default'];
        }

        return array_values(array_filter($configured, is_string(...)));
    }
}
