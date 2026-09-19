<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Listener;

use Cake\Event\EventListenerInterface;
use Crustum\Stimulus\Event\RequestReceivedEvent;

/**
 * Flushes queued cookies between worker requests.
 *
 * Drops cookies queued on the sandbox cookie jar so responses from one request never leak `Set-Cookie` headers into the next. Cookies live
 * per response object. Sandboxes that expose a shared
 * `flushQueuedCookies` queue, as provided by host applications, have it
 * flushed.
 *
 * Listened on the request received event.
 */
class FlushQueuedCookies implements EventListenerInterface
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
     * Flushes the sandbox cookie queue when the sandbox exposes one.
     *
     * @param \Crustum\Stimulus\Event\RequestReceivedEvent $event Received request operation.
     * @return void
     */
    public function handleRequestReceived(RequestReceivedEvent $event): void
    {
        $flushQueuedCookies = [$event->getSandbox(), 'flushQueuedCookies'];

        if (is_callable($flushQueuedCookies)) {
            $flushQueuedCookies();
        }
    }
}
