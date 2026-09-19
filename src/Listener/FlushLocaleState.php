<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Listener;

use Cake\Core\Configure;
use Cake\Event\EventListenerInterface;
use Cake\I18n\I18n;
use Crustum\Stimulus\Event\RequestReceivedEvent;

/**
 * Resets the locale between worker requests.
 *
 * Restores the process locale from the `App.defaultLocale`
 * configuration key. Translators opt into fallback handling separately;
 * this listener leaves fallback flags untouched.
 *
 * Listened on the request received event.
 */
class FlushLocaleState implements EventListenerInterface
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
     * Restores the locale configured for the application.
     *
     * @param \Crustum\Stimulus\Event\RequestReceivedEvent $event Received request operation.
     * @return void
     */
    public function handleRequestReceived(RequestReceivedEvent $event): void
    {
        $default = Configure::read('App.defaultLocale');

        if (!is_string($default) || $default === '') {
            $default = I18n::getDefaultLocale();
        }

        I18n::setLocale($default);
    }
}
