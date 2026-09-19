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
 * Flushes collected database query logs between worker requests.
 *
 * Database drivers log every query straight to their PSR logger without keeping an in-memory
 * buffer, so this listener clears loggers that expose a `clear` method
 * instead. Names that are not configured are skipped.
 *
 * The connection names are injected. A null list falls back to the
 * `Stimulus.listeners.connections` configuration and finally to the primary
 * connection.
 *
 * Listened on the request received event.
 */
class FlushDatabaseQueryLog implements EventListenerInterface
{
    /**
     * Connection names whose query logs are flushed.
     *
     * @var list<string>
     */
    protected array $connections;

    /**
     * Creates a new query log flushing listener.
     *
     * @param list<string>|null $connections Connection names whose query logs are flushed, null reads the `Stimulus.listeners.connections` configuration.
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
     * Clears the query logger buffer of every known connection.
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

            $logger = $connection->getDriver()->getLogger();

            if (!is_object($logger)) {
                continue;
            }

            $clear = [$logger, 'clear'];

            if (is_callable($clear)) {
                $clear();
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
