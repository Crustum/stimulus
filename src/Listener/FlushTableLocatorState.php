<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Listener;

use Cake\Event\EventListenerInterface;
use Cake\ORM\TableRegistry;
use Crustum\Stimulus\Event\RequestReceivedEvent;

/**
 * Flushes cached table objects between worker operations.
 *
 * The global `TableLocator` caches `Table` instances (with their behaviors, associations,
 * and connections). A long-lived worker would otherwise serve stale table
 * state across operations. Clearing the locator drops every cached table
 * so they rebuild lazily on next use; locator configuration survives.
 *
 * Listened on the request received event.
 */
class FlushTableLocatorState implements EventListenerInterface
{
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
     * Drops every cached table object from the global locator.
     *
     * @param \Crustum\Stimulus\Event\RequestReceivedEvent $event Received request operation.
     * @return void
     */
    public function handleRequestReceived(RequestReceivedEvent $event): void
    {
        TableRegistry::getTableLocator()->clear();
    }
}
