<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Worker;

use Cake\Collection\Collection;
use Cake\Container\Container as LeagueContainer;
use Cake\Container\Definition\DefinitionAggregateInterface;
use Cake\Container\Inflector\InflectorAggregateInterface;
use Cake\Container\ServiceProvider\ServiceProviderAggregateInterface;
use Cake\Container\ServiceProvider\ServiceProviderInterface;
use Cake\Core\Configure;
use Cake\Http\BaseApplication;
use Crustum\Stimulus\StimulusManager;
use ReflectionClass;
use Throwable;

/**
 * Creates the host application serving as the worker base instance.
 *
 * The factory instantiates the
 * configured application class with its config directory and runs the
 * bootstrap sequence. The initial instances have no container to seed,
 * so they are retained on the factory for collaborators that need them. Warm
 * pre-resolves the configured services through the application container.
 */
class ApplicationFactory
{
    /**
     * Initial instances passed to the last created application.
     *
     * The map is retained here for collaborators that need it.
     *
     * @var array<string, mixed>
     */
    protected array $initialInstances = [];

    /**
     * Creates a new application factory.
     *
     * @param class-string<\Cake\Http\BaseApplication> $appClass Host application class to boot.
     * @param string $configDir Directory holding the application bootstrap configuration.
     */
    public function __construct(
        protected string $appClass,
        protected string $configDir,
    ) {
    }

    /**
     * Creates a new application instance.
     *
     * Instantiates the configured application class with its config directory
     * and runs the bootstrap sequence, which loads the application
     * bootstrap file, registers configured plugins, and bootstraps plugins
     * with bootstrapping enabled so worker listeners attach.
     *
     * @param array<string, mixed> $initialInstances Instances retained for collaborators needing them.
     * @return \Cake\Http\BaseApplication Booted application serving as the worker base instance.
     */
    public function createApplication(array $initialInstances = []): BaseApplication
    {
        $this->initialInstances = $initialInstances;

        $appClass = $this->appClass;

        $app = new $appClass($this->configDir);

        $app->bootstrap();

        $app->pluginBootstrap();

        return $this->warm($app);
    }

    /**
     * Gets the initial instances passed to the last created application.
     *
     * The worker boot passes the bridge client here, and collaborators read
     * it back without a container binding.
     *
     * @return array<string, mixed> Initial instances keyed by their identifiers.
     */
    public function getInitialInstances(): array
    {
        return $this->initialInstances;
    }

    /**
     * Warms the application with pre-resolved services that persist across requests.
     *
     * Resolves every configured service that the container knows: the
     * collection filters the warm list down to non-empty string identifiers, then each identifier known to the
     * application container is resolved once so later operations reuse it.
     * An empty list falls back to the `Stimulus.warm` configuration, which
     * defaults to the manager warm list.
     *
     * @param \Cake\Http\BaseApplication $app Application to warm.
     * @param array<int, mixed> $services Service identifiers to pre-resolve.
     * @return \Cake\Http\BaseApplication Warmed application instance.
     */
    public function warm(BaseApplication $app, array $services = []): BaseApplication
    {
        if ($services === []) {
            $configured = Configure::read('Stimulus.warm');

            $services = is_array($configured) ? $configured : StimulusManager::defaultServicesToWarm();
        }

        $container = $app->getContainer();

        (new Collection($services))
            ->filter(fn($service): bool => is_string($service) && $service !== '')
            ->each(function (string $service) use ($container): void {
                if ($container->has($service)) {
                    $container->get($service);
                }
            });

        return $app;
    }

    /**
     * Creates a sandbox application sharing the preloaded container state.
     *
     * Clones the base application and gives the clone an isolated container
     * that shares the preloaded definitions: warmed instances stay shared
     * through the same definition objects, while the definition and provider
     * aggregates themselves are cloned so per-request registrations land in
     * the sandbox and never in the base application. Containers of another
     * type fall back to a rebuilt container through the application path.
     *
     * @param \Cake\Http\BaseApplication $app Base application to sandbox from.
     * @return \Cake\Http\BaseApplication Sandbox application with an isolated container.
     */
    public function createSandbox(BaseApplication $app): BaseApplication
    {
        $sandbox = clone $app;

        $container = $app->getContainer();

        if ($container instanceof LeagueContainer) {
            $this->injectContainer($sandbox, $this->cloneContainer($container));

            return $sandbox;
        }

        $this->rebuildContainer($sandbox);

        return $sandbox;
    }

    /**
     * Clones the given container with isolated registration aggregates.
     *
     * The cloned definition aggregate carries the same definition objects,
     * keeping warmed instances shared, but additions during the request stay
     * in the sandbox copy. The cloned provider aggregate is re-pointed at
     * the sandbox container together with cloned providers it holds, so lazy
     * provider registrations land in the sandbox instead of the base
     * application. Delegates are cloned and re-pointed at the sandbox
     * container together with their cached resolutions, so autowiring never
     * resolves through the base application. Inflectors are cloned and
     * re-pointed at the sandbox container.
     *
     * @param \Cake\Container\Container $container Base container to clone from.
     * @return \Cake\Container\Container Isolated container sharing preloaded definitions.
     */
    protected function cloneContainer(LeagueContainer $container): LeagueContainer
    {
        $sandboxContainer = clone $container;

        $ref = new ReflectionClass($sandboxContainer);

        $definitions = $ref->getProperty('definitions')->getValue($container);

        if ($definitions instanceof DefinitionAggregateInterface) {
            $sandboxDefinitions = clone $definitions;
            $sandboxDefinitions->setContainer($sandboxContainer);
            $ref->getProperty('definitions')->setValue($sandboxContainer, $sandboxDefinitions);
        }

        $providers = $ref->getProperty('providers')->getValue($container);

        if ($providers instanceof ServiceProviderAggregateInterface) {
            $sandboxProviders = clone $providers;
            $sandboxProviders->setContainer($sandboxContainer);
            $this->cloneProviders($sandboxProviders, $sandboxContainer);
            $ref->getProperty('providers')->setValue($sandboxContainer, $sandboxProviders);
        }

        $this->cloneInflectors($container, $sandboxContainer, $ref);
        $this->cloneDelegates($container, $sandboxContainer, $ref);
        $this->cloneEventDispatcher($container, $sandboxContainer);

        return $sandboxContainer;
    }

    /**
     * Clones the container event dispatcher so sandbox listeners never reach the base application.
     *
     * @param \Cake\Container\Container $baseContainer Base container holding the source dispatcher.
     * @param \Cake\Container\Container $sandboxContainer Sandbox container receiving the clone.
     * @return void
     */
    protected function cloneEventDispatcher(LeagueContainer $baseContainer, LeagueContainer $sandboxContainer): void
    {
        $dispatcher = $this->readProperty($baseContainer, 'eventDispatcher');

        if (!is_object($dispatcher)) {
            return;
        }

        $this->writeProperty($sandboxContainer, 'eventDispatcher', clone $dispatcher);
    }

    /**
     * Clones the providers held by the given aggregate and points them at the sandbox container.
     *
     * Cloning the aggregate alone keeps the same provider objects, so mutating
     * their container reference would also mutate the base application. Each
     * provider is cloned first, then pointed at the sandbox container.
     *
     * @param \Cake\Container\ServiceProvider\ServiceProviderAggregateInterface $aggregate Sandbox provider aggregate.
     * @param \Cake\Container\Container $sandboxContainer Sandbox container receiving registrations.
     * @return void
     */
    protected function cloneProviders(ServiceProviderAggregateInterface $aggregate, LeagueContainer $sandboxContainer): void
    {
        $providers = $this->readProperty($aggregate, 'providers');

        if (!is_array($providers)) {
            return;
        }

        $cloned = [];

        foreach ($providers as $provider) {
            if (!is_object($provider)) {
                $cloned[] = $provider;

                continue;
            }

            $sandboxProvider = clone $provider;

            try {
                if ($sandboxProvider instanceof ServiceProviderInterface) {
                    $sandboxProvider->setContainer($sandboxContainer);
                }
            } catch (Throwable) {
                $this->writeProperty($sandboxProvider, 'container', $sandboxContainer);
            }

            $cloned[] = $sandboxProvider;
        }

        $this->writeProperty($aggregate, 'providers', $cloned);
    }

    /**
     * Clones the inflector aggregate and points it at the sandbox container.
     *
     * @param \Cake\Container\Container $baseContainer Base container holding the source aggregate.
     * @param \Cake\Container\Container $sandboxContainer Sandbox container receiving the clone.
     * @param \ReflectionClass<\Cake\Container\Container> $ref Reflection of the sandbox container.
     * @return void
     */
    protected function cloneInflectors(LeagueContainer $baseContainer, LeagueContainer $sandboxContainer, ReflectionClass $ref): void
    {
        try {
            $inflectors = $ref->getProperty('inflectors')->getValue($baseContainer);
        } catch (Throwable) {
            return;
        }

        if (!$inflectors instanceof InflectorAggregateInterface) {
            return;
        }

        $sandboxInflectors = clone $inflectors;

        try {
            $sandboxInflectors->setContainer($sandboxContainer);
        } catch (Throwable) {
            return;
        }

        try {
            $ref->getProperty('inflectors')->setValue($sandboxContainer, $sandboxInflectors);
        } catch (Throwable) {
            return;
        }
    }

    /**
     * Clones delegate containers and points them at the sandbox container.
     *
     * Delegates carry their own container reference and, for attribute
     * delegates, a root reference plus a resolution cache. Sharing them
     * routes sandbox autowiring through the base application and lets cached
     * instances leak across requests.
     *
     * @param \Cake\Container\Container $baseContainer Base container holding the source delegates.
     * @param \Cake\Container\Container $sandboxContainer Sandbox container receiving the clones.
     * @param \ReflectionClass<\Cake\Container\Container> $ref Reflection of the sandbox container.
     * @return void
     */
    protected function cloneDelegates(LeagueContainer $baseContainer, LeagueContainer $sandboxContainer, ReflectionClass $ref): void
    {
        try {
            $delegates = $ref->getProperty('delegates')->getValue($baseContainer);
        } catch (Throwable) {
            return;
        }

        if (!is_array($delegates)) {
            return;
        }

        $cloned = [];

        foreach ($delegates as $delegate) {
            if (!is_object($delegate)) {
                $cloned[] = $delegate;

                continue;
            }

            $sandboxDelegate = clone $delegate;

            $this->writeProperty($sandboxDelegate, 'root', $sandboxContainer);
            $this->writeProperty($sandboxDelegate, 'container', $sandboxContainer);
            $this->writeProperty($sandboxDelegate, 'cache', []);

            try {
                if ($sandboxDelegate instanceof ServiceProviderInterface) {
                    $sandboxDelegate->setContainer($sandboxContainer);
                }
            } catch (Throwable) {
            }

            if (method_exists($sandboxDelegate, 'setContainer')) {
                try {
                    $sandboxDelegate->setContainer($sandboxContainer);
                } catch (Throwable) {
                }
            }

            $cloned[] = $sandboxDelegate;
        }

        try {
            $ref->getProperty('delegates')->setValue($sandboxContainer, $cloned);
        } catch (Throwable) {
            return;
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

    /**
     * Writes the named property on the given object across its class hierarchy.
     *
     * @param object $target Object receiving the property.
     * @param string $name Property name to write.
     * @param mixed $value Property value to set.
     * @return bool Whether the property was written.
     */
    protected function writeProperty(object $target, string $name, mixed $value): bool
    {
        $class = new ReflectionClass($target);

        while ($class !== false) {
            if ($class->hasProperty($name)) {
                try {
                    $property = $class->getProperty($name);
                    $property->setValue($target, $value);

                    return true;
                } catch (Throwable) {
                    return false;
                }
            }

            $class = $class->getParentClass();
        }

        return false;
    }

    /**
     * Replaces the sandbox container with a newly built one.
     *
     * Fallback for containers without League registration aggregates: the
     * container slot is emptied so the next access rebuilds it through the
     * application build path.
     *
     * @param \Cake\Http\BaseApplication $sandbox Sandbox application receiving the container.
     * @return void
     */
    protected function rebuildContainer(BaseApplication $sandbox): void
    {
        $ref = new ReflectionClass($sandbox);
        $prop = $ref->getProperty('container');
        $prop->setValue($sandbox, null);

        $sandbox->getContainer();
    }

    /**
     * Replaces the application container with the given instance.
     *
     * The container property on `BaseApplication` is protected with no public
     * setter. Reflection is the only way to inject the sandbox container
     * without subclassing the host application.
     *
     * @param \Cake\Http\BaseApplication $app Application receiving the container.
     * @param \Cake\Container\Container $container Container to inject.
     * @return void
     */
    protected function injectContainer(BaseApplication $app, LeagueContainer $container): void
    {
        $ref = new ReflectionClass($app);
        $prop = $ref->getProperty('container');
        $prop->setValue($app, $container);
    }
}
