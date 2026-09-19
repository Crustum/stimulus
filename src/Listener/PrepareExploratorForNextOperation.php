<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Listener;

use Cake\Event\EventListenerInterface;
use Crustum\Explorator\EngineManager;
use Crustum\Stimulus\Event\RequestReceivedEvent;

/**
 * Prepares the search engines for the next worker operation.
 *
 * Forgets the resolved engines of the injected sibling search engine
 * manager so they rebuild against the sandbox on next use, while its custom driver
 * registrations stay registered. Hosts without the sibling plugin, or
 * without a wired manager, keep their state untouched.
 *
 * Listened on the request received event. Worker proof of the sandbox swap
 * lands with the worker in a later phase.
 */
class PrepareExploratorForNextOperation implements EventListenerInterface
{
    /**
     * Search engine manager holding the resolved engines.
     *
     * @var \Crustum\Explorator\EngineManager|null
     */
    protected ?EngineManager $engines;

    /**
     * Creates a new search preparation listener.
     *
     * @param \Crustum\Explorator\EngineManager|null $engines Search engine manager holding the resolved engines, null leaves every engine untouched.
     */
    public function __construct(?EngineManager $engines = null)
    {
        $this->engines = $engines;
    }

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
     * Forgets every resolved search engine so the next use rebuilds it.
     *
     * @param \Crustum\Stimulus\Event\RequestReceivedEvent $event Received request operation.
     * @return void
     */
    public function handleRequestReceived(RequestReceivedEvent $event): void
    {
        if (!class_exists(EngineManager::class)) {
            return;
        }

        if (!$this->engines instanceof EngineManager) {
            return;
        }

        $this->engines->forgetEngines();
    }
}
