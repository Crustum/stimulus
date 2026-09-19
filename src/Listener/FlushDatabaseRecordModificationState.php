<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Listener;

use Cake\Core\Configure;
use Cake\Database\Connection;
use Cake\Datasource\ConnectionManager;
use Cake\Datasource\Exception\MissingDatasourceConfigException;
use Cake\Event\EventListenerInterface;
use Crustum\Stimulus\Event\RequestReceivedEvent;

/**
 * Forgets database record modification state between worker requests.
 *
 * There is no core state to reset here. Connections that expose a
 * `forgetRecordModificationState` method, such as Stimulus-compatible
 * decorators provided by the host application, have it invoked.
 * Names that are not configured are skipped.
 *
 * The connection names are injected. A null list falls back to the
 * `Stimulus.listeners.connections` configuration and finally to the primary
 * connection.
 *
 * Listened on the request received event.
 */
class FlushDatabaseRecordModificationState implements EventListenerInterface
{
    /**
     * Connection names whose modification state is forgotten.
     *
     * @var list<string>
     */
    protected array $connections;

    /**
     * Creates a new record modification state flushing listener.
     *
     * @param list<string>|null $connections Connection names whose modification state is forgotten, null reads the `Stimulus.listeners.connections` configuration.
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
            'Stimulus.requestReceived' => 'handleRequestReceived',
        ];
    }

    /**
     * Forgets the record modification state of every known connection.
     *
     * Iterates over the configured connection names.
     *
     * @param \Crustum\Stimulus\Event\RequestReceivedEvent $event Received request operation.
     * @return void
     */
    public function handleRequestReceived(RequestReceivedEvent $event): void
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

            $forget = [$connection, 'forgetRecordModificationState'];

            if (is_callable($forget)) {
                $forget();
            }
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
