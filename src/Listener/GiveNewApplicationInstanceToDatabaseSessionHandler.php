<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Listener;

use Cake\Event\EventListenerInterface;
use Cake\Http\Session\CacheSession;
use Cake\Http\Session\DatabaseSession;
use Crustum\Stimulus\Event\RequestReceivedEvent;

/**
 * Gives the session engine handler a fresh instance per worker operation.
 *
 * Refreshes the handler behind the request session for every engine type: the ORM-backed database handler rebuilds against
 * the fresh sandbox connection, while the cache handler is cloned so the
 * sandbox owns its handler object and keeps resolving its cache pool
 * lazily. Native PHP handlers and unknown engines need nothing and pass
 * through, since they hold no sandbox-bound state. Session engine
 * configuration stays process-global; hosts with a custom database session
 * model re-apply it after the rebuild, which the worker phase covers.
 *
 * Listened on the request received event. Worker proof of the sandbox swap
 * lands with the worker in a later phase.
 */
class GiveNewApplicationInstanceToDatabaseSessionHandler implements EventListenerInterface
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
     * Refreshes the session engine handler behind the request session.
     *
     * @param \Crustum\Stimulus\Event\RequestReceivedEvent $event Received request operation.
     * @return void
     */
    public function handleRequestReceived(RequestReceivedEvent $event): void
    {
        $session = $event->getRequest()->getSession();
        $handler = $session->engine();

        if ($handler instanceof DatabaseSession) {
            $session->engine(new DatabaseSession());

            return;
        }

        if ($handler instanceof CacheSession) {
            $session->engine(clone $handler);
        }
    }
}
