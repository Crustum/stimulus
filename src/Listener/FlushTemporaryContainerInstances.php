<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Listener;

use Cake\Container\Container;
use Cake\Container\Definition\DefinitionAggregateInterface;
use Cake\Core\Configure;
use Cake\Event\EventListenerInterface;
use Cake\Http\BaseApplication;
use Crustum\Stimulus\Contracts\OperationTerminated;
use ReflectionClass;
use Throwable;

/**
 * Flushes temporary container instances between worker operations.
 *
 * Listened on operation terminated operations.
 */
class FlushTemporaryContainerInstances implements EventListenerInterface
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
            'Stimulus.taskTerminated' => 'handleOperationTerminated',
            'Stimulus.tickTerminated' => 'handleOperationTerminated',
        ];
    }

    /**
     * Forgets cached instances of the configured flush bindings.
     *
     * Iterates over the configured flush bindings.
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

        $container = $app->getContainer();

        if (!$container instanceof Container) {
            return;
        }

        $flush = Configure::read('Stimulus.flush');

        if (!is_array($flush)) {
            return;
        }

        $aggregate = $this->definitionAggregate($container);

        if (!$aggregate instanceof DefinitionAggregateInterface) {
            return;
        }

        foreach ($flush as $binding) {
            if (!is_string($binding)) {
                continue;
            }

            if ($binding === '') {
                continue;
            }

            $this->forgetResolved($aggregate, $binding);
        }
    }

    /**
     * Reads the definition aggregate from the given container.
     *
     * @param \Cake\Container\Container $container Container holding the aggregate.
     * @return \Cake\Container\Definition\DefinitionAggregateInterface|null Definition aggregate, or null when unreachable.
     */
    protected function definitionAggregate(Container $container): ?DefinitionAggregateInterface
    {
        try {
            $aggregate = (new ReflectionClass($container))->getProperty('definitions')->getValue($container);
        } catch (Throwable) {
            return null;
        }

        return $aggregate instanceof DefinitionAggregateInterface ? $aggregate : null;
    }

    /**
     * Forgets the cached resolved instance of the given binding.
     *
     * Unknown bindings and definitions without resolvable state pass through
     * untouched, so flush lists stay safe against renames.
     *
     * @param \Cake\Container\Definition\DefinitionAggregateInterface $aggregate Definition aggregate holding the binding.
     * @param string $binding Binding identifier to forget.
     * @return void
     */
    protected function forgetResolved(DefinitionAggregateInterface $aggregate, string $binding): void
    {
        try {
            if (!$aggregate->has($binding)) {
                return;
            }

            $definition = $aggregate->getDefinition($binding);
        } catch (Throwable) {
            return;
        }

        try {
            $ref = new ReflectionClass($definition);

            if (!$ref->hasProperty('resolved')) {
                return;
            }

            $ref->getProperty('resolved')->setValue($definition, null);
        } catch (Throwable) {
            return;
        }
    }
}
