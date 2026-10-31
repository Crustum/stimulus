<?php
declare(strict_types=1);

namespace Crustum\Stimulus;

use Cake\Collection\Collection;
use Cake\Console\CommandCollection;
use Cake\Core\BasePlugin;
use Cake\Core\Configure;
use Cake\Core\ContainerInterface;
use Cake\Core\PluginApplicationInterface;
use Cake\Event\EventListenerInterface;
use Cake\Event\EventManagerInterface;
use Cake\Http\BaseApplication;
use Cake\Http\MiddlewareQueue;
use Cake\Routing\RouteBuilder;
use Crustum\Stimulus\Bridge\FrankenPhp\ServerStateFile as FrankenPhpServerStateFile;
use Crustum\Stimulus\Bridge\RoadRunner\ServerStateFile as RoadRunnerServerStateFile;
use Crustum\Stimulus\Bridge\Swoole\ServerStateFile as SwooleServerStateFile;
use Crustum\Stimulus\Command\InstallCommand;
use Crustum\Stimulus\Command\ReloadCommand;
use Crustum\Stimulus\Command\StartCommand;
use Crustum\Stimulus\Command\StartFrankenPhpCommand;
use Crustum\Stimulus\Command\StartRoadRunnerCommand;
use Crustum\Stimulus\Command\StartSwooleCommand;
use Crustum\Stimulus\Command\StatusCommand;
use Crustum\Stimulus\Command\StopCommand;
use Crustum\Stimulus\Contracts\DispatchesCoroutines;
use Crustum\Stimulus\Contracts\DispatchesTasks;
use Crustum\Stimulus\Dispatch\SequentialCoroutineDispatcher;
use Crustum\Stimulus\Dispatch\SequentialTaskDispatcher;
use Crustum\Stimulus\Worker\ApplicationFactory;
use Override;
use RuntimeException;

/**
 * Plugin for Crustum/Stimulus
 */
class StimulusPlugin extends BasePlugin
{
    /**
     * Event managers already wired with the worker listeners.
     *
     * Holding the instances keeps their identifiers stable, so repeated
     * application boots never stack duplicate handlers on one manager.
     *
     * @var list<\Cake\Event\EventManagerInterface>
     */
    protected static array $wiredManagers = [];

    /**
     * @inheritDoc
     */
    #[Override]
    public function bootstrap(PluginApplicationInterface $app): void
    {
        parent::bootstrap($app);

        if (!Configure::check('Stimulus')) {
            if (file_exists(CONFIG . 'stimulus.php')) {
                Configure::load('stimulus', 'default');
            } elseif (file_exists($this->getConfigPath() . 'stimulus.php')) {
                Configure::load('Crustum/Stimulus.stimulus', 'default', false);
            }
        }

        $this->wireListeners($app->getEventManager());
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function routes(RouteBuilder $routes): void
    {
        parent::routes($routes);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function middleware(MiddlewareQueue $middlewareQueue): MiddlewareQueue
    {
        return $middlewareQueue;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function console(CommandCollection $commands): CommandCollection
    {
        $commands->add('stimulus install', InstallCommand::class);
        $commands->add('stimulus reload', ReloadCommand::class);
        $commands->add('stimulus start', StartCommand::class);
        $commands->add('stimulus frankenphp', StartFrankenPhpCommand::class);
        $commands->add('stimulus roadrunner', StartRoadRunnerCommand::class);
        $commands->add('stimulus swoole', StartSwooleCommand::class);
        $commands->add('stimulus status', StatusCommand::class);
        $commands->add('stimulus stop', StopCommand::class);

        return $commands;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function services(ContainerInterface $container): void
    {
        $container->addShared(
            DispatchesTasks::class,
            fn(): SequentialTaskDispatcher => new SequentialTaskDispatcher(),
        );

        $container->addShared(
            DispatchesCoroutines::class,
            fn(): SequentialCoroutineDispatcher => new SequentialCoroutineDispatcher(),
        );

        $container->addShared(
            StimulusManager::class,
            function () use ($container): StimulusManager {
                $dispatcher = $container->get(DispatchesTasks::class);

                assert($dispatcher instanceof DispatchesTasks);

                return new StimulusManager($dispatcher);
            },
        );

        $container->add(
            ApplicationFactory::class,
            function (): ApplicationFactory {
                $namespace = (string)Configure::read('App.namespace', 'App');

                $appClass = $namespace . '\\Application';

                if (!is_subclass_of($appClass, BaseApplication::class, true)) {
                    throw new RuntimeException(
                        'Stimulus worker requires an application class extending Cake\Http\BaseApplication, "' .
                        $appClass . '" given.',
                    );
                }

                return new ApplicationFactory(
                    $appClass,
                    defined('CONFIG') ? CONFIG : getcwd() . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR,
                );
            },
        );

        $container->addShared(
            RoadRunnerServerStateFile::class,
            fn(): RoadRunnerServerStateFile => new RoadRunnerServerStateFile(
                (string)Configure::read(
                    'Stimulus.stateFile.roadrunner',
                    sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'stimulus-roadrunner.json',
                ),
            ),
        );

        $container->addShared(
            FrankenPhpServerStateFile::class,
            fn(): FrankenPhpServerStateFile => new FrankenPhpServerStateFile(
                (string)Configure::read(
                    'Stimulus.stateFile.frankenphp',
                    sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'stimulus-frankenphp.json',
                ),
            ),
        );

        $container->addShared(
            SwooleServerStateFile::class,
            fn(): SwooleServerStateFile => new SwooleServerStateFile(
                (string)Configure::read(
                    'Stimulus.stateFile.swoole',
                    sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'stimulus-swoole.json',
                ),
            ),
        );
    }

    /**
     * Wires the configured worker listeners through the given event manager.
     *
     * The listeners are stateless and subscribe through
     * `implementedEvents()`, so the plugin instantiates them directly and
     * attaches them to the event manager. Each manager wires once per
     * process, which keeps repeated application boots in worker tests from
     * stacking duplicate handlers.
     *
     * @param \Cake\Event\EventManagerInterface $manager Event manager receiving the listeners.
     * @return void
     */
    protected function wireListeners(EventManagerInterface $manager): void
    {
        if (in_array($manager, static::$wiredManagers, true)) {
            return;
        }

        static::$wiredManagers[] = $manager;

        $listeners = Configure::read('Stimulus.listeners');

        if (!is_array($listeners)) {
            return;
        }

        (new Collection($listeners))->each(function ($eventListeners) use ($manager): void {
            if (!is_array($eventListeners)) {
                return;
            }

            (new Collection($eventListeners))
                ->filter(fn($listener): bool => is_string($listener) && class_exists($listener))
                ->unique()
                ->each(function (string $listener) use ($manager): void {
                    $instance = new $listener();

                    if ($instance instanceof EventListenerInterface) {
                        $manager->on($instance);
                    }
                });
        });
    }
}
