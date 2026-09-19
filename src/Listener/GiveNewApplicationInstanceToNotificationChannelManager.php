<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Listener;

use Cake\Collection\Collection;
use Cake\Event\EventListenerInterface;
use Crustum\Notification\NotificationManager;
use Crustum\Stimulus\Event\RequestReceivedEvent;

/**
 * Gives the notification channel manager fresh channels per worker operation.
 *
 * Unloads the resolved channels from the sibling notification registry through a collection
 * pipeline, so they rebuild against the sandbox configuration on next use
 * while their registrations survive. Hosts without the sibling plugin keep
 * their state untouched.
 *
 * Listened on the request received event. Worker proof of the sandbox swap
 * lands with the worker in a later phase.
 */
class GiveNewApplicationInstanceToNotificationChannelManager implements EventListenerInterface
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
     * Unloads every resolved channel so the next send rebuilds it.
     *
     * @param \Crustum\Stimulus\Event\RequestReceivedEvent $event Received request operation.
     * @return void
     */
    public function handleRequestReceived(RequestReceivedEvent $event): void
    {
        if (!class_exists(NotificationManager::class)) {
            return;
        }

        $registry = NotificationManager::getRegistry();

        (new Collection($registry->loaded()))
            ->each(function (string $name) use ($registry): void {
                $registry->unload($name);
            });
    }
}
