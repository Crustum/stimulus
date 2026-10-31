<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Listener;

use Cake\Container\Container;
use Cake\Container\Definition\DefinitionAggregateInterface;
use Cake\Controller\ComponentRegistry;
use Cake\Event\EventListenerInterface;
use Cake\Http\BaseApplication;
use Crustum\Stimulus\Event\RequestReceivedEvent;
use ReflectionClass;

/**
 * Flushes the application container's cached resolutions between worker operations.
 *
 * The container caches shared instance resolutions in
 * `Definition::$resolved`. The `ControllerFactory` registers `ComponentRegistry`
 * as a shared binding via `addShared()`, but when the alias already exists the
 * second call returns the OLD definition — its cached `$resolved` instance
 * persists across requests. A long-lived worker would serve stale component
 * registries, causing alias collisions when different controllers load the same
 * component under different aliases (e.g. `CakeDC/Admin.AdminExt` with alias
 * `TestScopes` vs `TestIterationCases`).
 *
 * This listener removes the stale `ComponentRegistry` definition from the
 * container before the next request, so `ControllerFactory::create()` registers
 * a fresh definition and resolves a new instance.
 *
 * Listened on the request received event.
 */
class FlushContainerResolutions implements EventListenerInterface
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
     * Removes stale shared definitions from the container.
     *
     * The `ComponentRegistry` definition persists across requests because
     * `addShared()` does not overwrite existing definitions. Removing it
     * forces the `ControllerFactory` to register and resolve a fresh instance.
     *
     * @param \Crustum\Stimulus\Event\RequestReceivedEvent $event Received request operation.
     * @return void
     */
    public function handleRequestReceived(RequestReceivedEvent $event): void
    {
        $app = $event->getSandbox();

        if (!$app instanceof BaseApplication) {
            return;
        }

        $container = $app->getContainer();

        if (!$container instanceof Container) {
            return;
        }

        $this->flushStaleDefinitions($container);
    }

    /**
     * Removes the `ComponentRegistry` definition from the container's aggregate.
     *
     * Reads the aggregate's internal definition map through reflection and
     * drops the stale key. The definition aggregate exposes no public
     * `remove()`, so the key is unset directly. The `ControllerFactory` will
     * re-add the definition on the next `create()` call, yielding a fresh instance.
     *
     * @param \Cake\Container\Container $container Container holding shared resolutions.
     * @return void
     */
    protected function flushStaleDefinitions(Container $container): void
    {
        $aggregate = (new ReflectionClass($container))->getProperty('definitions')->getValue($container);

        if (!$aggregate instanceof DefinitionAggregateInterface) {
            return;
        }

        $aggregateRef = new ReflectionClass($aggregate);
        $definitions = $aggregateRef->getProperty('definitions')->getValue($aggregate);

        if (!is_array($definitions) || !array_key_exists(ComponentRegistry::class, $definitions)) {
            return;
        }

        unset($definitions[ComponentRegistry::class]);
        $aggregateRef->getProperty('definitions')->setValue($aggregate, $definitions);
    }
}
