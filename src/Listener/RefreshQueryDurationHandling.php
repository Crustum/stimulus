<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Listener;

use Cake\Core\Configure;
use Cake\Database\Connection;
use Cake\Database\Log\QueryLogger;
use Cake\Datasource\ConnectionManager;
use Cake\Datasource\Exception\MissingDatasourceConfigException;
use Cake\Event\EventListenerInterface;
use Crustum\Stimulus\Event\RequestReceivedEvent;
use Psr\Log\LoggerInterface;

/**
 * Refreshes query duration handling per worker operation.
 *
 * Rebinds a fresh query logger on every connection whose driver logs
 * queries, so logged durations measure the current operation only.
 * Connections without a logger or without configuration are skipped.
 *
 * The connection names are injected. A null list falls back to the
 * `Stimulus.listeners.connections` configuration and finally to the primary
 * connection.
 *
 * Listened on the request received event. Worker proof of the sandbox swap
 * lands with the worker in a later phase.
 */
class RefreshQueryDurationHandling implements EventListenerInterface
{
    /**
     * Connection names whose query loggers are rebound.
     *
     * @var list<string>
     */
    protected array $connections;

    /**
     * Creates a new query duration refreshing listener.
     *
     * @param list<string>|null $connections Connection names whose query loggers are rebound, null reads the `Stimulus.listeners.connections` configuration.
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
     * Rebinds a fresh query logger on every logging connection.
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

            $driver = $connection->getDriver();

            if (!$driver->getLogger() instanceof LoggerInterface) {
                continue;
            }

            $driver->setLogger(new QueryLogger());
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
