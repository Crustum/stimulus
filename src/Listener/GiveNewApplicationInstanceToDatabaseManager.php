<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Listener;

use Cake\Collection\Collection;
use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;
use Cake\Event\EventListenerInterface;
use Crustum\Stimulus\Event\RequestReceivedEvent;
use Crustum\Stimulus\Trait\RefreshesStaticConfigTrait;

/**
 * Gives the database manager fresh connections per worker operation.
 *
 * Drops every known resolved connection and re-registers its configuration,
 * so connections rebuild lazily against the sandbox on next use while their
 * configuration survives. The refresh runs through the shared
 * static-configuration contract, which validates rebuildability before the
 * drop: object and closure configurations round-trip untouched while names
 * without rebuildable configuration are skipped.
 *
 * The connection names are injected. A null list falls back to the `Stimulus.listeners.connections`
 * configuration and finally to the primary connection.
 *
 * Listened on the request received event. Worker proof of the sandbox swap
 * lands with the worker in a later phase.
 */
class GiveNewApplicationInstanceToDatabaseManager implements EventListenerInterface
{
    use RefreshesStaticConfigTrait;

    /**
     * Connection names whose resolved instances are refreshed.
     *
     * @var list<string>
     */
    protected array $connections;

    /**
     * Creates a new database manager refreshing listener.
     *
     * @param list<string>|null $connections Connection names whose resolved instances are refreshed, null reads the `Stimulus.listeners.connections` configuration.
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
     * Refreshes the resolved instance of every known connection.
     *
     * Connections without rebuildable configuration are skipped. The
     * connections iterate through a collection pipeline.
     *
     * @param \Crustum\Stimulus\Event\RequestReceivedEvent $event Received request operation.
     * @return void
     */
    public function handleRequestReceived(RequestReceivedEvent $event): void
    {
        (new Collection($this->connections))
            ->filter(fn(string $name): bool => ConnectionManager::getConfig($name) !== null)
            ->each(fn(string $name): bool => $this->refreshStaticConfig(ConnectionManager::class, $name));
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
