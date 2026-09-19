<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Listener;

use Cake\Event\EventListenerInterface;
use Cake\Http\BaseApplication;
use Crustum\Stimulus\Contracts\OperationTerminated;
use ReflectionClass;
use Throwable;

/**
 * Flushes DebugKit toolbar panels between worker operations.
 *
 * DebugKit builds its toolbar service once at plugin bootstrap and reuses
 * it for every request, so the panel registry survives across worker
 * operations. The middleware reloads the panels on each request, and
 * plugin-prefixed loads through `ObjectRegistry` fail the duplicate check
 * from the second operation on with `The ... alias has already been
 * loaded`. Unloading the panels after the response lets the next operation
 * rebuild them fresh. Everything resolves defensively through duck typing
 * so production applications without DebugKit pass through untouched and
 * the plugin keeps no hard dependency on it.
 *
 * Listened on the request terminated event.
 */
class FlushDebugKitState implements EventListenerInterface
{
    /**
     * Returns the events this listener handles.
     *
     * @return array<string, mixed> Event names mapped to handler methods.
     */
    public function implementedEvents(): array
    {
        return [
            'Stimulus.requestTerminated' => 'handleOperationTerminated',
        ];
    }

    /**
     * Unloads every panel held by the DebugKit toolbar service.
     *
     * Applications without the DebugKit plugin, disabled toolbars, and
     * services unreachable through reflection pass through untouched, so
     * the listener stays safe against renames and missing integrations.
     *
     * @param \Crustum\Stimulus\Contracts\OperationTerminated $event Terminated worker operation.
     * @return void
     */
    public function handleOperationTerminated(OperationTerminated $event): void
    {
        $app = $event->app();

        if (!$app instanceof BaseApplication) {
            return;
        }

        $plugins = $app->getPlugins();

        if (!$plugins->has('DebugKit')) {
            return;
        }

        try {
            $plugin = $plugins->get('DebugKit');
        } catch (Throwable) {
            return;
        }

        $service = $this->readProperty($plugin, 'service');

        if (!is_object($service) || !method_exists($service, 'registry')) {
            return;
        }

        try {
            $registry = $service->registry();
        } catch (Throwable) {
            return;
        }

        if (!is_object($registry) || !method_exists($registry, 'loaded') || !method_exists($registry, 'unload')) {
            return;
        }

        try {
            $loaded = $registry->loaded();
        } catch (Throwable) {
            return;
        }

        if (!is_array($loaded)) {
            return;
        }

        foreach ($loaded as $panel) {
            if (!is_string($panel)) {
                continue;
            }

            if ($panel === '') {
                continue;
            }

            try {
                $registry->unload($panel);
            } catch (Throwable) {
                continue;
            }
        }
    }

    /**
     * Reads the named property from the given object across its class hierarchy.
     *
     * @param object $target Object holding the property.
     * @param string $name Property name to read.
     * @return mixed Property value, or null when unreachable.
     */
    protected function readProperty(object $target, string $name): mixed
    {
        $class = new ReflectionClass($target);

        while ($class !== false) {
            if ($class->hasProperty($name)) {
                try {
                    $property = $class->getProperty($name);

                    return $property->getValue($target);
                } catch (Throwable) {
                    return null;
                }
            }

            $class = $class->getParentClass();
        }

        return null;
    }
}
