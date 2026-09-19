<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Listener;

use Cake\Event\EventListenerInterface;
use Crustum\Stimulus\Event\RequestReceivedEvent;

/**
 * Gives the filesystem manager a fresh instance per worker operation.
 *
 * This listener intentionally performs no reset. The class is kept so the
 * operation pipeline keeps its shape and host code can subclass it once a
 * file-storage service exists.
 *
 * Listened on the request received event.
 */
class GiveNewApplicationInstanceToFilesystemManager implements EventListenerInterface
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
     * Handles the received operation without resetting any state.
     *
     * @param \Crustum\Stimulus\Event\RequestReceivedEvent $event Received request operation.
     * @return void
     */
    public function handleRequestReceived(RequestReceivedEvent $event): void
    {
    }
}
