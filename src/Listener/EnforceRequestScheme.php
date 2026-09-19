<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Listener;

use Cake\Core\Configure;
use Cake\Event\EventListenerInterface;
use Crustum\Stimulus\Event\RequestReceivedEvent;

/**
 * Forces the request scheme to HTTPS between worker requests.
 *
 * Rewrites the request URI scheme and marks the request environment secure
 * when the `Stimulus.https` flag is set, so URL generation and secure checks
 * agree for the whole operation.
 *
 * Listened on the request received event.
 */
class EnforceRequestScheme implements EventListenerInterface
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
     * Forces the request URI and environment to HTTPS when configured.
     *
     * @param \Crustum\Stimulus\Event\RequestReceivedEvent $event Received request operation.
     * @return void
     */
    public function handleRequestReceived(RequestReceivedEvent $event): void
    {
        if (!Configure::read('Stimulus.https')) {
            return;
        }

        $uri = $event->getRequest()->getUri()->withScheme('https');
        $event->setRequest($event->getRequest()->withUri($uri)->withEnv('HTTPS', 'on'));
    }
}
