<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Listener;

use Cake\Event\EventListenerInterface;
use Cake\Event\EventManager;
use Cake\Http\ServerRequest;
use Crustum\Stimulus\Event\RequestReceivedEvent;
use Crustum\Stimulus\Event\RequestTerminatedEvent;
use Crustum\Stimulus\Listener\CloseMonologHandlers;
use Crustum\Stimulus\Listener\CollectGarbage;
use Crustum\Stimulus\Listener\CreateConfigurationSandbox;
use Crustum\Stimulus\Listener\CreateUrlGeneratorSandbox;
use Crustum\Stimulus\Listener\DisconnectFromDatabases;
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
use Crustum\Stimulus\Listener\FlushUploadedFiles;
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
use Crustum\Stimulus\Test\TestCase;
use Laminas\Diactoros\Response as PsrResponse;
use stdClass;

/**
 * Tests for the listener event subscriptions.
 *
 * Proves every listener implements the listener interface, maps its
 * event to a named handler method, and can be dispatched through the
 * event manager.
 */
class ListenerImplementedEventsTest extends TestCase
{
    /**
     * Every listener maps its event to a named handler method.
     *
     * @return void
     */
    public function testImplementedEvents(): void
    {
        $requestReceived = ['Stimulus.requestReceived' => 'handleRequestReceived'];
        $workerErrorOccurred = ['Stimulus.workerErrorOccurred' => 'handleWorkerErrorOccurred'];
        $operationTerminated = [
            'Stimulus.requestTerminated' => 'handleOperationTerminated',
            'Stimulus.taskTerminated' => 'handleOperationTerminated',
            'Stimulus.tickTerminated' => 'handleOperationTerminated',
        ];

        $expected = [
            FlushArrayCache::class => $requestReceived,
            FlushAuthenticationState::class => $requestReceived,
            FlushDatabaseQueryLog::class => $requestReceived,
            FlushDatabaseRecordModificationState::class => $requestReceived,
            FlushLocaleState::class => $requestReceived,
            FlushLogContext::class => $requestReceived,
            FlushMonologState::class => $requestReceived,
            FlushQueuedCookies::class => $requestReceived,
            FlushSessionState::class => [
                'Stimulus.requestReceived' => 'handleRequestReceived',
                'Stimulus.requestTerminated' => 'handleRequestTerminated',
            ],
            FlushStrCache::class => $requestReceived,
            FlushTableLocatorState::class => $requestReceived,
            FlushTranslatorCache::class => $requestReceived,
            CreateConfigurationSandbox::class => [
                'Stimulus.requestReceived' => 'handleRequestReceived',
                'Stimulus.taskReceived' => 'handleTaskReceived',
                'Stimulus.tickReceived' => 'handleTickReceived',
                'Stimulus.requestTerminated' => 'handleOperationTerminated',
                'Stimulus.taskTerminated' => 'handleOperationTerminated',
                'Stimulus.tickTerminated' => 'handleOperationTerminated',
                'Stimulus.workerErrorOccurred' => 'handleWorkerErrorOccurred',
            ],
            CreateUrlGeneratorSandbox::class => $requestReceived,
            EnforceRequestScheme::class => $requestReceived,
            EnsureRequestServerPortMatchesScheme::class => $requestReceived,
            CloseMonologHandlers::class => ['Stimulus.workerStopping' => 'handleWorkerStopping'],
            FlushUploadedFiles::class => ['Stimulus.requestTerminated' => 'handleRequestTerminated'],
            DisconnectFromDatabases::class => $operationTerminated,
            CollectGarbage::class => $operationTerminated,
            FlushOnce::class => $operationTerminated,
            FlushTemporaryContainerInstances::class => $operationTerminated,
            GiveNewApplicationInstanceToAuthorizationGate::class => $requestReceived,
            GiveNewApplicationInstanceToBroadcastManager::class => $requestReceived,
            GiveNewApplicationInstanceToCacheManager::class => $requestReceived,
            GiveNewApplicationInstanceToDatabaseManager::class => $requestReceived,
            GiveNewApplicationInstanceToDatabaseSessionHandler::class => $requestReceived,
            GiveNewApplicationInstanceToFilesystemManager::class => $requestReceived,
            GiveNewApplicationInstanceToLogManager::class => $requestReceived,
            GiveNewApplicationInstanceToMailManager::class => $requestReceived,
            GiveNewApplicationInstanceToNotificationChannelManager::class => $requestReceived,
            GiveNewApplicationInstanceToQueueManager::class => $requestReceived,
            GiveNewApplicationInstanceToRouter::class => $requestReceived,
            GiveNewApplicationInstanceToSessionManager::class => $requestReceived,
            GiveNewApplicationInstanceToValidationFactory::class => $requestReceived,
            GiveNewApplicationInstanceToViewFactory::class => $requestReceived,
            GiveNewRequestInstanceToApplication::class => $requestReceived,
            RefreshQueryDurationHandling::class => $requestReceived,
            PrepareExploratorForNextOperation::class => $requestReceived,
            ReportException::class => $workerErrorOccurred,
            StopWorkerIfNecessary::class => $workerErrorOccurred,
        ];

        foreach ($expected as $class => $mapping) {
            $listener = new $class();

            $this->assertInstanceOf(EventListenerInterface::class, $listener, $class);
            $this->assertSame($mapping, $listener->implementedEvents(), $class);

            foreach ($mapping as $handler) {
                $this->assertTrue(method_exists($listener, (string)$handler), $class . '::' . $handler);
            }
        }
    }

    /**
     * Request listeners dispatch through the event manager.
     *
     * @return void
     */
    public function testRequestListenerDispatchesThroughEventManager(): void
    {
        $manager = new EventManager();
        $manager->on(new FlushStrCache());

        $event = new RequestReceivedEvent(new stdClass(), new stdClass(), new ServerRequest(['url' => '/']));

        $manager->dispatch($event);

        $this->assertSame('Stimulus.requestReceived', $event->getName());
    }

    /**
     * Terminated operation listeners dispatch through the event manager.
     *
     * @return void
     */
    public function testTerminatedListenerDispatchesThroughEventManager(): void
    {
        $manager = new EventManager();
        $manager->on(new FlushOnce());

        $event = new RequestTerminatedEvent(
            new stdClass(),
            new stdClass(),
            new ServerRequest(['url' => '/']),
            new PsrResponse(),
        );

        $manager->dispatch($event);

        $this->assertSame('Stimulus.requestTerminated', $event->getName());
    }
}
