<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Listener;

use Cake\Event\EventListenerInterface;
use Cake\Utility\Inflector;
use Crustum\Stimulus\Event\RequestReceivedEvent;

/**
 * Flushes cached string manipulations between worker requests.
 *
 * Resets the inflector, which memoizes pluralization, singularization,
 * transliteration and related string rules in static state.
 *
 * Listened on the request received event.
 */
class FlushStrCache implements EventListenerInterface
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
     * Resets the inflector caches.
     *
     * @param \Crustum\Stimulus\Event\RequestReceivedEvent $event Received request operation.
     * @return void
     */
    public function handleRequestReceived(RequestReceivedEvent $event): void
    {
        Inflector::reset();
    }
}
