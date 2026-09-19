<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Listener;

use Cake\Event\EventListenerInterface;
use Crustum\Stimulus\Event\RequestReceivedEvent;

/**
 * Aligns the request server port with its scheme.
 *
 * Records the conventional port in the request environment when
 * the PSR-7 URI carries no explicit port, so port-aware code sees matching
 * values for plain and secure requests alike.
 *
 * Listened on the request received event.
 */
class EnsureRequestServerPortMatchesScheme implements EventListenerInterface
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
     * Records the conventional port when the request URI carries none.
     *
     * @param \Crustum\Stimulus\Event\RequestReceivedEvent $event Received request operation.
     * @return void
     */
    public function handleRequestReceived(RequestReceivedEvent $event): void
    {
        if ($event->getRequest()->getUri()->getPort() !== null) {
            return;
        }

        $scheme = $event->getRequest()->getUri()->getScheme();
        $event->setRequest($event->getRequest()->withEnv('SERVER_PORT', $scheme === 'https' ? '443' : '80'));
    }
}
