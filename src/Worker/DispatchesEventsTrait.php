<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Worker;

use Cake\Event\EventManager;
use Crustum\Stimulus\Event\StimulusEvent;

/**
 * Dispatches worker events through the event manager.
 *
 * Worker events dispatch through the event manager carried by
 * the application, with the global manager as the fallback for application
 * doubles without one. The global manager is intentionally never replaced
 * per operation: host listeners subscribe once per process and a per-sandbox
 * replacement would drop them. Per-operation isolation comes from dispatching
 * through the sandbox application handle, never a stale boot-time instance.
 */
trait DispatchesEventsTrait
{
    /**
     * Dispatches the given event via the given application.
     *
     * Resolves the manager from the application handle so sandbox clones
     * dispatch through the same manager lineage as the base application.
     *
     * @param object $app Application carrying the event manager.
     * @param \Crustum\Stimulus\Event\StimulusEvent $event Event to dispatch.
     * @return void
     */
    public function dispatchEvent(object $app, StimulusEvent $event): void
    {
        if (method_exists($app, 'getEventManager')) {
            $app->getEventManager()->dispatch($event);

            return;
        }

        EventManager::instance()->dispatch($event);
    }
}
