<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Listener;

use Cake\Cache\Cache;
use Cake\Event\EventListenerInterface;
use Cake\I18n\I18n;
use Crustum\Stimulus\Event\RequestReceivedEvent;

/**
 * Flushes the translator cache between worker requests.
 *
 * Drops the in-memory translator registry and clears the persistent parsed translation cache so the next operation reloads
 * translations for the restored locale.
 *
 * Listened on the request received event.
 */
class FlushTranslatorCache implements EventListenerInterface
{
    /**
     * Cache configuration holding parsed translations.
     *
     * @var string
     */
    protected const TRANSLATIONS_CACHE = '_cake_translations_';

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
     * Drops cached translators and parsed translations.
     *
     * @param \Crustum\Stimulus\Event\RequestReceivedEvent $event Received request operation.
     * @return void
     */
    public function handleRequestReceived(RequestReceivedEvent $event): void
    {
        I18n::clear();

        if (in_array(static::TRANSLATIONS_CACHE, Cache::configured(), true)) {
            Cache::clear(static::TRANSLATIONS_CACHE);
        }
    }
}
