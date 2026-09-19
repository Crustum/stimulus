<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Listener;

use Cake\Event\EventListenerInterface;
use Crustum\Stimulus\Event\RequestReceivedEvent;

/**
 * Gives the validation factory a fresh instance per worker operation.
 *
 * Validators are stateless: rules run against the values handed to
 * each call and custom providers travel with the validator instance, so a
 * long-lived worker accumulates no factory state and this listener
 * intentionally performs no reset. The class is kept so the operation
 * pipeline keeps its shape and host code can subclass it when a
 * shared factory ever appears.
 *
 * Listened on the request received event.
 */
class GiveNewApplicationInstanceToValidationFactory implements EventListenerInterface
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
