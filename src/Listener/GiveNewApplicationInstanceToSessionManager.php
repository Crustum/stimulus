<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Listener;

use Cake\Event\EventListenerInterface;
use Crustum\Stimulus\Event\RequestReceivedEvent;

/**
 * Gives the session manager a fresh session per worker operation.
 *
 * Every request marshalled by the worker bridges already carries its own fresh session object, so
 * there is nothing to renew here: clearing the session on receive would
 * wipe the persisted login and form protection tokens the incoming request
 * is about to validate against. Process-global leftovers are reset by the
 * session state flushing listener instead.
 *
 * Listened on the request received event.
 */
class GiveNewApplicationInstanceToSessionManager implements EventListenerInterface
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
     * Accepts the fresh session carried by the received request.
     *
     * The session object on the event request is already per-operation, so
     * this handler intentionally leaves it untouched.
     *
     * @param \Crustum\Stimulus\Event\RequestReceivedEvent $event Received request operation.
     * @return void
     */
    public function handleRequestReceived(RequestReceivedEvent $event): void
    {
    }
}
