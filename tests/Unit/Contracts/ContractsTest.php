<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Contracts;

use Cake\Http\ServerRequest;
use Crustum\Stimulus\Contracts\Client;
use Crustum\Stimulus\Contracts\DispatchesCoroutines;
use Crustum\Stimulus\Contracts\DispatchesTasks;
use Crustum\Stimulus\Contracts\OperationTerminated;
use Crustum\Stimulus\Contracts\ServerProcessInspector;
use Crustum\Stimulus\Contracts\ServesStaticFiles;
use Crustum\Stimulus\Contracts\StoppableClient;
use Crustum\Stimulus\Contracts\Worker;
use Crustum\Stimulus\Event\RequestTerminatedEvent;
use Crustum\Stimulus\Http\RequestContext;
use Crustum\Stimulus\Http\StimulusResponse;
use Crustum\Stimulus\Test\Support\FakeClient;
use Crustum\Stimulus\Test\TestCase;
use Laminas\Diactoros\Response as PsrResponse;
use RuntimeException;
use stdClass;
use Throwable;

/**
 * Tests proving every P1 contract loads and accepts an implementation.
 */
class ContractsTest extends TestCase
{
    /**
     * Client contract accepts an implementation.
     *
     * @return void
     */
    public function testClientContract(): void
    {
        $client = new FakeClient([]);
        $context = new RequestContext(['request' => new ServerRequest()]);

        $this->assertInstanceOf(Client::class, $client);
        $this->assertSame([$context->request, $context], $client->marshalRequest($context));

        $client->respond($context, new StimulusResponse(new PsrResponse()));

        $this->assertCount(1, $client->responses);
    }

    /**
     * Stoppable client extends the base client contract.
     *
     * @return void
     */
    public function testStoppableClientContract(): void
    {
        $client = new class ([]) extends FakeClient implements StoppableClient {
            /**
             * Records stop calls.
             *
             * @var bool
             */
            public bool $stopped = false;

            /**
             * Stops the underlying server.
             *
             * @return void
             */
            public function stop(): void
            {
                $this->stopped = true;
            }
        };

        $this->assertInstanceOf(Client::class, $client);

        $client->stop();

        $this->assertTrue($client->stopped);
    }

    /**
     * Coroutine dispatcher contract resolves callbacks.
     *
     * @return void
     */
    public function testDispatchesCoroutinesContract(): void
    {
        $dispatcher = new class implements DispatchesCoroutines {
            /**
             * Resolves callbacks sequentially.
             *
             * @param array<int|string, callable> $coroutines Callbacks to resolve.
             * @param int $waitSeconds Seconds to wait.
             * @return array<int|string, mixed> Results keyed by their given keys.
             */
            public function resolve(array $coroutines, int $waitSeconds = -1): array
            {
                $results = [];
                foreach ($coroutines as $key => $coroutine) {
                    $results[$key] = $coroutine();
                }

                return $results;
            }
        };

        $this->assertSame(['first' => 1], $dispatcher->resolve(['first' => fn(): int => 1], 5));
    }

    /**
     * Task dispatcher contract resolves and dispatches callbacks.
     *
     * @return void
     */
    public function testDispatchesTasksContract(): void
    {
        $dispatcher = new class implements DispatchesTasks {
            /**
             * Resolves callbacks sequentially.
             *
             * @param array<int|string, callable> $tasks Callbacks to resolve.
             * @param int $waitMilliseconds Milliseconds to wait.
             * @return array<int|string, mixed> Results keyed by their given keys.
             */
            public function resolve(array $tasks, int $waitMilliseconds = 3000): array
            {
                $results = [];
                foreach ($tasks as $key => $task) {
                    $results[$key] = $task();
                }

                return $results;
            }

            /**
             * Dispatches callbacks sequentially.
             *
             * @param array<int|string, callable> $tasks Callbacks to dispatch.
             * @return void
             */
            public function dispatch(array $tasks): void
            {
                foreach ($tasks as $task) {
                    $task();
                }
            }
        };

        $this->assertSame(['first' => 1], $dispatcher->resolve(['first' => fn(): int => 1]));

        $ran = false;
        $dispatcher->dispatch([function () use (&$ran): void {
            $ran = true;
        }]);

        $this->assertTrue($ran);
    }

    /**
     * Operation terminated contract exposes both application instances.
     *
     * @return void
     */
    public function testOperationTerminatedContract(): void
    {
        $app = new ServerRequest();
        $event = new RequestTerminatedEvent(
            new stdClass(),
            new stdClass(),
            $app,
            new PsrResponse(),
        );

        $this->assertInstanceOf(OperationTerminated::class, $event);
        $this->assertSame($event->app, $event->app());
        $this->assertSame($event->sandbox, $event->sandbox());
    }

    /**
     * Server process inspector contract loads.
     *
     * @return void
     */
    public function testServerProcessInspectorContract(): void
    {
        $inspector = new class implements ServerProcessInspector {
            /**
             * Determines if the server process is running.
             *
             * @return bool Whether the server process is running.
             */
            public function serverIsRunning(): bool
            {
                return true;
            }

            /**
             * Reloads the workers.
             *
             * @return void
             */
            public function reloadServer(): void
            {
            }

            /**
             * Stops the server.
             *
             * @return bool Whether the server was stopped.
             */
            public function stopServer(): bool
            {
                return true;
            }
        };

        $this->assertTrue($inspector->serverIsRunning());

        $inspector->reloadServer();

        $this->assertTrue($inspector->stopServer());
    }

    /**
     * Static file server contract loads.
     *
     * @return void
     */
    public function testServesStaticFilesContract(): void
    {
        $server = new class implements ServesStaticFiles {
            /**
             * Determines if the request can be served as a static file.
             *
             * @param \Cake\Http\ServerRequest $request Incoming request.
             * @param \Crustum\Stimulus\Http\RequestContext $context Raw request context.
             * @return bool Whether the request maps to a static file.
             */
            public function canServeRequestAsStaticFile(
                ServerRequest $request,
                RequestContext $context,
            ): bool {
                return false;
            }

            /**
             * Serves the static file that was requested.
             *
             * @param \Cake\Http\ServerRequest $request Incoming request.
             * @param \Crustum\Stimulus\Http\RequestContext $context Raw request context.
             * @return void
             */
            public function serveStaticFile(ServerRequest $request, RequestContext $context): void
            {
            }
        };

        $this->assertFalse(
            $server->canServeRequestAsStaticFile(new ServerRequest(), new RequestContext()),
        );
    }

    /**
     * Worker contract loads and accepts an implementation.
     *
     * @return void
     */
    public function testWorkerContract(): void
    {
        $worker = new class implements Worker {
            /**
             * Boots the worker.
             *
             * @return void
             */
            public function boot(): void
            {
            }

            /**
             * Handles an incoming request.
             *
             * @param \Cake\Http\ServerRequest $request Incoming request.
             * @param \Crustum\Stimulus\Http\RequestContext $context Raw request context.
             * @return void
             */
            public function handle(ServerRequest $request, RequestContext $context): void
            {
            }

            /**
             * Handles an incoming task.
             *
             * @param mixed $data Task payload.
             * @return mixed Task result.
             */
            public function handleTask(mixed $data): mixed
            {
                return $data;
            }

            /**
             * Terminates the worker.
             *
             * @return void
             */
            public function terminate(): void
            {
            }
        };

        $this->assertSame('payload', $worker->handleTask('payload'));

        $worker->boot();
        $worker->handle(new ServerRequest(), new RequestContext());
        $worker->terminate();

        $this->assertTrue(true);
    }

    /**
     * Client error signature accepts throwables with requests.
     *
     * @return void
     */
    public function testClientErrorAcceptsThrowable(): void
    {
        $client = new FakeClient([]);
        $context = new RequestContext();

        try {
            throw new RuntimeException('Boom.');
        } catch (Throwable $throwable) {
            $client->error($throwable, new stdClass(), new ServerRequest(), $context);
        }

        $this->assertCount(1, $client->errors);
    }
}
