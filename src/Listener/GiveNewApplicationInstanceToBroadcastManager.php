<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Listener;

use Cake\Collection\Collection;
use Cake\Event\EventListenerInterface;
use Crustum\Broadcasting\Broadcasting;
use Crustum\Stimulus\Event\RequestReceivedEvent;

/**
 * Gives the broadcast manager fresh instances per worker operation.
 *
 * Unloads the resolved broadcasters from the sibling broadcasting registry
 * through a collection pipeline, so they rebuild against the sandbox
 * configuration on next use while keeping channel routes. Hosts without the sibling
 * plugin keep their state untouched. Channel authorization callbacks are
 * re-registered by the host routes file once the worker reloads them, which
 * the worker phase covers.
 *
 * Listened on the request received event. Worker proof of the sandbox swap
 * lands with the worker in a later phase.
 */
class GiveNewApplicationInstanceToBroadcastManager implements EventListenerInterface
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
     * Unloads every resolved broadcaster so the next use rebuilds it.
     *
     * @param \Crustum\Stimulus\Event\RequestReceivedEvent $event Received request operation.
     * @return void
     */
    public function handleRequestReceived(RequestReceivedEvent $event): void
    {
        if (!class_exists(Broadcasting::class)) {
            return;
        }

        $registry = Broadcasting::getRegistry();

        (new Collection($registry->loaded()))
            ->each(function (string $name) use ($registry): void {
                $registry->unload($name);
            });
    }
}
