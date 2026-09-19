<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Listener;

use Cake\Event\EventListenerInterface;
use Cake\Routing\Router;
use Crustum\Stimulus\Event\RequestReceivedEvent;

/**
 * Gives the router the current request per worker operation.
 *
 * The per-operation identity is the request itself: pointing the router at the
 * received request keeps URL generation and parsing scoped to the current
 * operation. `Router::reload()` was rejected: it wipes every connected
 * route and only the sandbox application of a later phase could re-register
 * them. The cached scheme, host and port refresh lives in the URL generator
 * sandbox listener.
 *
 * Listened on the request received event. Worker proof of the sandbox swap
 * lands with the worker in a later phase.
 */
class GiveNewApplicationInstanceToRouter implements EventListenerInterface
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
     * Points the router at the received request.
     *
     * @param \Crustum\Stimulus\Event\RequestReceivedEvent $event Received request operation.
     * @return void
     */
    public function handleRequestReceived(RequestReceivedEvent $event): void
    {
        Router::setRequest($event->getRequest());
    }
}
