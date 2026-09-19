<?php
declare(strict_types=1);

use Crustum\Stimulus\Contracts\OperationTerminated;
use Crustum\Stimulus\Event\RequestHandledEvent;
use Crustum\Stimulus\Event\RequestReceivedEvent;
use Crustum\Stimulus\Event\RequestTerminatedEvent;
use Crustum\Stimulus\Event\TaskReceivedEvent;
use Crustum\Stimulus\Event\TaskTerminatedEvent;
use Crustum\Stimulus\Event\TickReceivedEvent;
use Crustum\Stimulus\Event\TickTerminatedEvent;
use Crustum\Stimulus\Event\WorkerErrorOccurredEvent;
use Crustum\Stimulus\Event\WorkerStartingEvent;
use Crustum\Stimulus\Event\WorkerStoppingEvent;
use Crustum\Stimulus\Listener\CloseMonologHandlers;
use Crustum\Stimulus\Listener\CreateConfigurationSandbox;
use Crustum\Stimulus\Listener\CreateUrlGeneratorSandbox;
use Crustum\Stimulus\Listener\EnforceRequestScheme;
use Crustum\Stimulus\Listener\EnsureRequestServerPortMatchesScheme;
use Crustum\Stimulus\Listener\FlushArrayCache;
use Crustum\Stimulus\Listener\FlushAuthenticationState;
use Crustum\Stimulus\Listener\FlushDatabaseQueryLog;
use Crustum\Stimulus\Listener\FlushDatabaseRecordModificationState;
use Crustum\Stimulus\Listener\FlushLocaleState;
use Crustum\Stimulus\Listener\FlushLogContext;
use Crustum\Stimulus\Listener\FlushMonologState;
use Crustum\Stimulus\Listener\FlushOnce;
use Crustum\Stimulus\Listener\FlushQueuedCookies;
use Crustum\Stimulus\Listener\FlushSessionState;
use Crustum\Stimulus\Listener\FlushStrCache;
use Crustum\Stimulus\Listener\FlushTableLocatorState;
use Crustum\Stimulus\Listener\FlushTemporaryContainerInstances;
use Crustum\Stimulus\Listener\FlushTranslatorCache;
use Crustum\Stimulus\Listener\GiveNewApplicationInstanceToAuthorizationGate;
use Crustum\Stimulus\Listener\GiveNewApplicationInstanceToBroadcastManager;
use Crustum\Stimulus\Listener\GiveNewApplicationInstanceToCacheManager;
use Crustum\Stimulus\Listener\GiveNewApplicationInstanceToDatabaseManager;
use Crustum\Stimulus\Listener\GiveNewApplicationInstanceToDatabaseSessionHandler;
use Crustum\Stimulus\Listener\GiveNewApplicationInstanceToFilesystemManager;
use Crustum\Stimulus\Listener\GiveNewApplicationInstanceToLogManager;
use Crustum\Stimulus\Listener\GiveNewApplicationInstanceToMailManager;
use Crustum\Stimulus\Listener\GiveNewApplicationInstanceToNotificationChannelManager;
use Crustum\Stimulus\Listener\GiveNewApplicationInstanceToQueueManager;
use Crustum\Stimulus\Listener\GiveNewApplicationInstanceToRouter;
use Crustum\Stimulus\Listener\GiveNewApplicationInstanceToSessionManager;
use Crustum\Stimulus\Listener\GiveNewApplicationInstanceToValidationFactory;
use Crustum\Stimulus\Listener\GiveNewApplicationInstanceToViewFactory;
use Crustum\Stimulus\Listener\GiveNewRequestInstanceToApplication;
use Crustum\Stimulus\Listener\PrepareExploratorForNextOperation;
use Crustum\Stimulus\Listener\RefreshQueryDurationHandling;
use Crustum\Stimulus\Listener\ReportException;
use Crustum\Stimulus\Listener\StopWorkerIfNecessary;
use Crustum\Stimulus\StimulusManager;

/**
 * Stimulus Plugin Configuration
 */
return [
    'Stimulus' => [
        /**
         * Stimulus Server
         *
         * This value determines the default "server" that will be used by
         * Stimulus when starting, restarting, or stopping your server via the
         * CLI. You are free to change this to the supported server of your
         * choosing.
         *
         * Supported: "roadrunner", "swoole", "frankenphp"
         */
        'server' => env('STIMULUS_SERVER', 'roadrunner'),

        /**
         * Worker HTTP server host and port
         *
         * The address the worker server binds on. Commands resolve these from
         * their options first, then from these values, then from the
         * environment, then from the loopback defaults.
         */
        'host' => env('STIMULUS_HOST', '127.0.0.1'),

        /**
         * Worker HTTP server port
         *
         * The port the worker server binds on. Commands resolve these from
         * their options first, then from these values, then from the
         * environment, then from the loopback defaults.
         */
        'port' => env('STIMULUS_PORT', '8000'),

        /**
         * Worker Application Class
         *
         * The application class booted inside the long-lived worker.
         * The starter commands forward this
         * value to the worker entries, which fall back to it when the
         * environment carries no explicit class.
         */
        'appClass' => env('STIMULUS_APP_CLASS', 'App\\Application'),

        /**
         * Force HTTPS
         *
         * When this configuration value is set to "true", Stimulus will inform
         * the framework that all absolute links must be generated using the
         * HTTPS protocol. Otherwise your links may be generated using plain
         * HTTP.
         */
        'https' => env('STIMULUS_HTTPS', false),

        /**
         * Stimulus Listeners
         *
         * All of the event listeners for Stimulus events are defined below.
         * These listeners are responsible for resetting your application's
         * state for the next request. You may even add your own listeners to
         * the list. The plugin wires them through the event manager
         * on bootstrap instead of container singletons.
         */
        'listeners' => [
            WorkerStartingEvent::class => [
            ],

            RequestReceivedEvent::class => [
                CreateConfigurationSandbox::class,
                CreateUrlGeneratorSandbox::class,
                GiveNewApplicationInstanceToAuthorizationGate::class,
                GiveNewApplicationInstanceToBroadcastManager::class,
                GiveNewApplicationInstanceToDatabaseManager::class,
                GiveNewApplicationInstanceToDatabaseSessionHandler::class,
                GiveNewApplicationInstanceToFilesystemManager::class,
                GiveNewApplicationInstanceToLogManager::class,
                GiveNewApplicationInstanceToMailManager::class,
                GiveNewApplicationInstanceToNotificationChannelManager::class,
                GiveNewApplicationInstanceToCacheManager::class,
                GiveNewApplicationInstanceToSessionManager::class,
                GiveNewApplicationInstanceToQueueManager::class,
                GiveNewApplicationInstanceToRouter::class,
                GiveNewApplicationInstanceToValidationFactory::class,
                GiveNewApplicationInstanceToViewFactory::class,
                FlushDatabaseRecordModificationState::class,
                FlushTableLocatorState::class,
                FlushDatabaseQueryLog::class,
                RefreshQueryDurationHandling::class,
                FlushArrayCache::class,
                FlushLogContext::class,
                FlushMonologState::class,
                FlushStrCache::class,
                FlushTranslatorCache::class,
                PrepareExploratorForNextOperation::class,
                FlushLocaleState::class,
                FlushQueuedCookies::class,
                EnforceRequestScheme::class,
                EnsureRequestServerPortMatchesScheme::class,
                GiveNewRequestInstanceToApplication::class,
            ],

            RequestHandledEvent::class => [
            ],

            RequestTerminatedEvent::class => [
                FlushSessionState::class,
                FlushAuthenticationState::class,
                // FlushUploadedFiles::class,
            ],

            TaskReceivedEvent::class => [
                CreateConfigurationSandbox::class,
                CreateUrlGeneratorSandbox::class,
                GiveNewApplicationInstanceToAuthorizationGate::class,
                GiveNewApplicationInstanceToBroadcastManager::class,
                GiveNewApplicationInstanceToDatabaseManager::class,
                GiveNewApplicationInstanceToDatabaseSessionHandler::class,
                GiveNewApplicationInstanceToFilesystemManager::class,
                GiveNewApplicationInstanceToLogManager::class,
                GiveNewApplicationInstanceToMailManager::class,
                GiveNewApplicationInstanceToNotificationChannelManager::class,
                GiveNewApplicationInstanceToCacheManager::class,
                GiveNewApplicationInstanceToSessionManager::class,
                GiveNewApplicationInstanceToQueueManager::class,
                GiveNewApplicationInstanceToRouter::class,
                GiveNewApplicationInstanceToValidationFactory::class,
                GiveNewApplicationInstanceToViewFactory::class,
                FlushDatabaseRecordModificationState::class,
                FlushTableLocatorState::class,
                FlushDatabaseQueryLog::class,
                RefreshQueryDurationHandling::class,
                FlushArrayCache::class,
                FlushLogContext::class,
                FlushMonologState::class,
                FlushStrCache::class,
                FlushTranslatorCache::class,
                PrepareExploratorForNextOperation::class,
            ],

            TaskTerminatedEvent::class => [
            ],

            TickReceivedEvent::class => [
                CreateConfigurationSandbox::class,
                CreateUrlGeneratorSandbox::class,
                GiveNewApplicationInstanceToAuthorizationGate::class,
                GiveNewApplicationInstanceToBroadcastManager::class,
                GiveNewApplicationInstanceToDatabaseManager::class,
                GiveNewApplicationInstanceToDatabaseSessionHandler::class,
                GiveNewApplicationInstanceToFilesystemManager::class,
                GiveNewApplicationInstanceToLogManager::class,
                GiveNewApplicationInstanceToMailManager::class,
                GiveNewApplicationInstanceToNotificationChannelManager::class,
                GiveNewApplicationInstanceToCacheManager::class,
                GiveNewApplicationInstanceToSessionManager::class,
                GiveNewApplicationInstanceToQueueManager::class,
                GiveNewApplicationInstanceToRouter::class,
                GiveNewApplicationInstanceToValidationFactory::class,
                GiveNewApplicationInstanceToViewFactory::class,
                FlushDatabaseRecordModificationState::class,
                FlushTableLocatorState::class,
                FlushDatabaseQueryLog::class,
                RefreshQueryDurationHandling::class,
                FlushArrayCache::class,
                FlushLogContext::class,
                FlushMonologState::class,
                FlushStrCache::class,
                FlushTranslatorCache::class,
                PrepareExploratorForNextOperation::class,
            ],

            TickTerminatedEvent::class => [
            ],

            OperationTerminated::class => [
                FlushOnce::class,
                FlushTemporaryContainerInstances::class,
            ],

            WorkerErrorOccurredEvent::class => [
                ReportException::class,
                StopWorkerIfNecessary::class,
            ],

            WorkerStoppingEvent::class => [
                CloseMonologHandlers::class,
            ],

            /**
             * Worker connection names
             *
             * Database listeners cannot enumerate configured connections through
             * a public API, so they refresh the connections listed here. Host
             * applications with secondary connections extend this list. The
             * class filter in the plugin wiring ignores this entry when
             * subscribing listeners, since its values are connection names,
             * not classes.
             */
            'connections' => [
                'default',
            ],
        ],

        /**
         * Warm / Flush Bindings
         *
         * The bindings listed below will either be pre-warmed when a worker
         * boots or they will be flushed before every new request. Flushing a
         * binding will force the container to resolve that binding again when
         * asked.
         */
        'warm' => StimulusManager::defaultServicesToWarm(),

        'flush' => [
        ],

        /**
         * Stimulus Swoole Tables
         *
         * While using Swoole, you may define additional tables as required by
         * the application. These tables can be used to store data that needs
         * to be quickly accessed by other workers on the particular Swoole
         * server. Tables activate in P9 with the Swoole server entries.
         */
        'tables' => [
            'example:1000' => [
                'name' => 'string:1000',
                'votes' => 'int',
            ],
        ],

        /**
         * Stimulus Swoole Cache Table
         *
         * While using Swoole, you may leverage the Stimulus cache, which is
         * powered by a Swoole table. You may set the maximum number of rows
         * as well as the number of bytes per row using the configuration
         * options below. The Swoole cache store activates in P9.
         */
        'cache' => [
            'rows' => 1000,
            'bytes' => 10000,
        ],

        /**
         * File Watching
         *
         * The following list of files and directories will be watched when
         * using the --watch option offered by Stimulus. If any of the
         * directories and files are changed, Stimulus will automatically
         * reload your workers.
         */
        'watch' => [
            'src',
            'config/**/*.php',
            'resources/**/*.php',
            'templates/**/*.php',
            'tests/**/*.php',
            'composer.lock',
            '.env',
        ],

        /**
         * Garbage Collection Threshold
         *
         * When executing long-lived PHP scripts such as Stimulus, memory can
         * build up before being cleared by PHP. You can force Stimulus to run
         * garbage collection if your application consumes this amount of
         * megabytes.
         */

        'garbage' => 50,

        /**
         * Maximum Execution Time
         *
         * The following setting configures the maximum execution time for
         * requests being handled by Stimulus. You may set this value to 0 to
         * indicate that there isn't a specific time limit on Stimulus request
         * execution time.
         */

        'max_execution_time' => 30,

        /**
         * Maximum Requests Before Recycle
         *
         * The worker stops once it served this many failing operations, which
         * keeps a repeatedly failing worker from looping forever. Zero
         * disables the limit. Feeds the worker error listener.
         */
        'max_requests' => 500,

        /**
         * Stimulus Server State Files
         *
         * These values determine where Stimulus stores the state files used to
         * track the running servers, which are read by the various Stimulus
         * commands. The commands resolve one file per driver, so the mapping
         * splits the key.
         */
        'stateFile' => [
            'roadrunner' => env(
                'STIMULUS_ROADRUNNER_STATE_FILE',
                sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'stimulus-roadrunner.json',
            ),
            'frankenphp' => env(
                'STIMULUS_FRANKENPHP_STATE_FILE',
                sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'stimulus-frankenphp.json',
            ),
            'swoole' => env(
                'STIMULUS_SWOLE_STATE_FILE',
                sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'stimulus-swoole.json',
            ),
        ],

        /**
         * RoadRunner Options
         *
         * The following options are only used when the RoadRunner server is
         * the server powering your application. You may customize the path to
         * the worker binary here, which is useful for zero-downtime
         * deployments where the application is served from a symlinked
         * "current" path.
         */
        'roadrunner' => [
            'command' => env('STIMULUS_ROADRUNNER_COMMAND', 'vendor/bin/roadrunner-worker'),
            'http_middleware' => env('STIMULUS_ROADRUNNER_HTTP_MIDDLEWARE', 'static'),
        ],

        /**
         * Swoole Options
         *
         * The following options are only used when the Swoole server is the
         * server powering your application. The command defaults to the
         * shipped plugin entry; override it with an absolute path or a path
         * relative to the application base when the entry was published.
         */
        'swoole' => [
            'command' => env('STIMULUS_SWOLE_COMMAND', ''),
            'php_options' => [],
            'ssl' => env('STIMULUS_SWOLE_SSL', false),
            'options' => [],
        ],
    ],
];
