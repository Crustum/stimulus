<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Listener;

use Cake\Event\EventListenerInterface;
use Crustum\Stimulus\Event\RequestReceivedEvent;
use Crustum\Stimulus\Http\CurrentApplication;

/**
 * Gives the application handle the current request per worker operation.
 *
 * This listener points the current-application seam at the received request,
 * which the worker core of a later phase reads when it boots the sandbox.
 *
 * Listened on the request received event. Worker proof of the sandbox swap
 * lands with the worker in a later phase.
 */
class GiveNewRequestInstanceToApplication implements EventListenerInterface
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
     * Points the current-application seam at the received request.
     *
     * @param \Crustum\Stimulus\Event\RequestReceivedEvent $event Received request operation.
     * @return void
     */
    public function handleRequestReceived(RequestReceivedEvent $event): void
    {
        CurrentApplication::setRequest($event->getRequest());
    }
}
