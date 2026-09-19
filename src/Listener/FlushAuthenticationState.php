<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Listener;

use Cake\Event\EventListenerInterface;
use Crustum\Stimulus\Event\RequestReceivedEvent;

/**
 * Flushes authentication state between worker requests.
 *
 * The persisted login survives in the session. Identity travels on the request itself and persists in the
 * session under the `Auth` key used by default by the
 * `cakephp/authentication` session authenticator, so this listener removes
 * only the in-memory `identity` request attribute. The persisted session
 * entry must survive: deleting it logs the user out before every request
 * and breaks form protection validation with it.
 *
 * Listened on the request received event.
 */
class FlushAuthenticationState implements EventListenerInterface
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
     * Removes the in-memory request identity.
     *
     * Each request re-authenticates from its own persisted session entry, so
     * only the request attribute is dropped here.
     *
     * @param \Crustum\Stimulus\Event\RequestReceivedEvent $event Received request operation.
     * @return void
     */
    public function handleRequestReceived(RequestReceivedEvent $event): void
    {
        $request = $event->getRequest();

        if ($request->getAttribute('identity') !== null) {
            $event->setRequest($request->withoutAttribute('identity'));
        }
    }
}
