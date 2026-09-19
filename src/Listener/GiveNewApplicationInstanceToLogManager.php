<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Listener;

use Cake\Collection\Collection;
use Cake\Event\EventListenerInterface;
use Cake\Log\Log;
use Crustum\Stimulus\Event\RequestReceivedEvent;
use Crustum\Stimulus\Trait\RefreshesStaticConfigTrait;

/**
 * Gives the log manager fresh engines per worker operation.
 *
 * Drops every resolved log engine and re-registers its configuration, so engines rebuild lazily against the sandbox on next
 * write while their configuration survives. The refresh runs through the
 * shared static-configuration contract, which validates rebuildability
 * before the drop. `Log::reset()` was rejected: it also wipes the host
 * logging configuration.
 *
 * Listened on the request received event. Worker proof of the sandbox swap
 * lands with the worker in a later phase.
 */
class GiveNewApplicationInstanceToLogManager implements EventListenerInterface
{
    use RefreshesStaticConfigTrait;

    /**
     * Log engine names whose instances are refreshed, null refreshes all configured engines.
     *
     * @var list<string>|null
     */
    protected ?array $engines;

    /**
     * Creates a new log manager refreshing listener.
     *
     * @param list<string>|null $engines Log engine names whose instances are refreshed, null refreshes all configured engines.
     */
    public function __construct(?array $engines = null)
    {
        $this->engines = $engines;
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
     * Refreshes the instance of every known log engine.
     *
     * Engines without rebuildable configuration are skipped. The engines
     * iterate through a collection pipeline.
     *
     * @param \Crustum\Stimulus\Event\RequestReceivedEvent $event Received request operation.
     * @return void
     */
    public function handleRequestReceived(RequestReceivedEvent $event): void
    {
        (new Collection($this->engines ?? Log::configured()))
            ->filter(fn(string $name): bool => Log::getConfig($name) !== null)
            ->each(fn(string $name): bool => $this->refreshStaticConfig(Log::class, $name));
    }
}
