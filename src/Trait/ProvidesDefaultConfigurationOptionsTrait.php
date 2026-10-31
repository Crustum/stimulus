<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Trait;

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

/**
 * Provides the default worker sandbox configuration options.
 */
trait ProvidesDefaultConfigurationOptionsTrait
{
    /**
     * Gets the listeners that will prepare the application for a new request.
     *
     * @return list<class-string> Listener class names run before each request.
     */
    public static function prepareApplicationForNextRequest(): array
    {
        return [
            FlushLocaleState::class,
            FlushQueuedCookies::class,
            FlushSessionState::class,
            FlushAuthenticationState::class,
            EnforceRequestScheme::class,
            EnsureRequestServerPortMatchesScheme::class,
            GiveNewRequestInstanceToApplication::class,
        ];
    }

    /**
     * Gets the listeners that will prepare the application for a new operation.
     *
     * @return list<class-string> Listener class names run before each operation.
     */
    public static function prepareApplicationForNextOperation(): array
    {
        return [
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
            FlushDatabaseQueryLog::class,
            RefreshQueryDurationHandling::class,
            FlushArrayCache::class,
            FlushLogContext::class,
            FlushMonologState::class,
            FlushOnce::class,
            FlushStrCache::class,
            FlushTranslatorCache::class,
            PrepareExploratorForNextOperation::class,
        ];
    }

    /**
     * Gets the container bindings and services that should be pre-resolved by default.
     *
     * Binding identifiers stay verbatim until the worker dependency-injection
     * wiring maps them onto services in the worker core phase.
     *
     * @return list<string> Service identifiers to warm.
     */
    public static function defaultServicesToWarm(): array
    {
        return [
            'auth',
            'cache',
            'cache.store',
            'config',
            'cookie',
            'db',
            'db.factory',
            'db.transactions',
            'files',
            'hash',
            'log',
            'router',
            'routes',
            'session',
            'session.store',
            'translator',
            'url',
            'view',
        ];
    }
}
