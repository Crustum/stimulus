<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Worker;

use Cake\Http\BaseApplication;
use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Closure;
use Crustum\Stimulus\Contracts\Client;
use Crustum\Stimulus\Contracts\ServesStaticFiles;
use Crustum\Stimulus\Contracts\Worker as WorkerContract;
use Crustum\Stimulus\Event\TaskReceivedEvent;
use Crustum\Stimulus\Event\TaskTerminatedEvent;
use Crustum\Stimulus\Event\TickReceivedEvent;
use Crustum\Stimulus\Event\TickTerminatedEvent;
use Crustum\Stimulus\Event\WorkerErrorOccurredEvent;
use Crustum\Stimulus\Event\WorkerStartingEvent;
use Crustum\Stimulus\Event\WorkerStoppingEvent;
use Crustum\Stimulus\Exception\DdException;
use Crustum\Stimulus\Exception\TaskException;
use Crustum\Stimulus\Exception\TaskExceptionResult;
use Crustum\Stimulus\Exception\TaskTimeoutException;
use Crustum\Stimulus\Http\CurrentApplication;
use Crustum\Stimulus\Http\RequestContext;
use Crustum\Stimulus\StimulusManager;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Throwable;

/**
 * Long-lived worker booting the application once and serving many operations.
 *
 * Boot creates the base application through the factory and registers the
 * base application through the factory and registers the in-worker task
 * routes on the manager; every request, task, and tick then clones the base
 * application into a sandbox and dispatches through the current sandbox
 * handle, never the stale boot-time instance. The graceful server loop
 * driving these methods lives in the `bin/` worker entries, which follow the
 * signal-handler termination pattern wired by the server commands.
 *
 * @see \Crustum\Stimulus\Contracts\Worker
 */
class Worker implements WorkerContract
{
    use DispatchesEventsTrait;
    use MarshalsPsr7RequestsAndResponsesTrait;

    /**
     * Callbacks invoked after the worker handled a request.
     *
     * @var list<\Closure>
     */
    protected array $requestHandledCallbacks = [];

    /**
     * Base application instance booted once per worker.
     *
     * @var \Cake\Http\BaseApplication|null
     */
    protected ?BaseApplication $app = null;

    /**
     * Manager holding the in-worker routes and the task dispatcher.
     *
     * @var \Crustum\Stimulus\StimulusManager|null
     */
    protected ?StimulusManager $manager;

    /**
     * Creates a new worker.
     *
     * @param \Crustum\Stimulus\Worker\ApplicationFactory $appFactory Factory creating the base application.
     * @param \Crustum\Stimulus\Contracts\Client $client Bridge client talking to the server.
     * @param \Crustum\Stimulus\StimulusManager|null $manager Manager holding routes and dispatchers, or null for a fresh manager.
     */
    public function __construct(
        protected ApplicationFactory $appFactory,
        protected Client $client,
        ?StimulusManager $manager = null,
    ) {
        $this->manager = $manager;
    }

    /**
     * Boots the worker.
     *
     * Creates the base application through the factory, ensures the manager,
     * registers the in-worker task routes on it, and dispatches the starting
     * event.
     *
     * @param array<string, mixed> $initialInstances Instances retained by the factory for collaborators needing them.
     * @return void
     */
    public function boot(array $initialInstances = []): void
    {
        $this->app = $this->appFactory->createApplication(
            array_merge(
                $initialInstances,
                [Client::class => $this->client],
            ),
        );
        $app = $this->app;
        $this->getManager();

        $this->registerTaskRoutes();

        $this->dispatchEvent($app, new WorkerStartingEvent($app));
    }

    /**
     * Gets the manager holding the in-worker routes and the task dispatcher.
     *
     * @return \Crustum\Stimulus\StimulusManager Worker-owned manager instance.
     */
    public function getManager(): StimulusManager
    {
        return $this->manager ??= new StimulusManager();
    }

    /**
     * Handles an incoming request and sends the response to the client.
     *
     * @param \Cake\Http\ServerRequest $request Incoming request.
     * @param \Crustum\Stimulus\Http\RequestContext $context Raw request context from the server.
     * @return void
     */
    public function handle(ServerRequest $request, RequestContext $context): void
    {
        if (
            $this->client instanceof ServesStaticFiles &&
            $this->client->canServeRequestAsStaticFile($request, $context)
        ) {
            $this->client->serveStaticFile($request, $context);

            return;
        }

        CurrentApplication::setRequest($request);

        $sandbox = $this->appFactory->createSandbox($this->application());

        $gateway = new ApplicationGateway($this->application(), $sandbox, $this->getManager());

        $bufferLevel = ob_get_level();

        try {
            $responded = false;

            ob_start();

            $response = $gateway->handle($request);

            $output = ob_get_contents();

            if (ob_get_level() > 0) {
                ob_end_clean();
            }

            $this->client->respond(
                $context,
                $this->toStimulusResponse($response, $output === false ? null : $output),
            );

            $responded = true;

            $this->invokeRequestHandledCallbacks($request, $response, $sandbox);

            $gateway->terminate($request, $response);
        } catch (Throwable $throwable) {
            while (ob_get_level() > $bufferLevel) {
                ob_end_clean();
            }

            $this->handleWorkerError($throwable, $sandbox, $request, $context, $responded);
        } finally {
            CurrentApplication::reset();

            unset($gateway, $sandbox, $context, $request, $response, $output);
        }
    }

    /**
     * Handles an incoming task.
     *
     * @param mixed $data Task payload.
     * @return mixed Task result or exception snapshot.
     */
    public function handleTask(mixed $data): mixed
    {
        $app = $this->application();

        $sandbox = clone $app;

        try {
            $this->dispatchEvent($sandbox, new TaskReceivedEvent($app, $sandbox, $data));

            $result = $data();

            $this->dispatchEvent($sandbox, new TaskTerminatedEvent($app, $sandbox, $data, $result));
        } catch (Throwable $throwable) {
            $this->dispatchEvent($sandbox, new WorkerErrorOccurredEvent($throwable, $sandbox));

            return TaskExceptionResult::from($throwable);
        } finally {
            CurrentApplication::reset();

            unset($sandbox);
        }

        return $result;
    }

    /**
     * Handles an incoming tick.
     *
     * @return void
     */
    public function handleTick(): void
    {
        $app = $this->application();

        $sandbox = clone $app;

        try {
            $this->dispatchEvent($sandbox, new TickReceivedEvent($app, $sandbox));
            $this->dispatchEvent($sandbox, new TickTerminatedEvent($app, $sandbox));
        } catch (Throwable $throwable) {
            $this->dispatchEvent($sandbox, new WorkerErrorOccurredEvent($throwable, $sandbox));
        } finally {
            CurrentApplication::reset();

            unset($sandbox);
        }
    }

    /**
     * Handles an uncaught exception from the worker.
     *
     * @param \Throwable $e Throwable raised while handling the operation.
     * @param \Cake\Http\BaseApplication $sandbox Sandbox application instance that was active.
     * @param \Cake\Http\ServerRequest $request Request that was being handled.
     * @param \Crustum\Stimulus\Http\RequestContext $context Raw request context from the server.
     * @param bool $hasResponded Whether the worker already responded to the client.
     * @return void
     */
    protected function handleWorkerError(
        Throwable $e,
        BaseApplication $sandbox,
        ServerRequest $request,
        RequestContext $context,
        bool $hasResponded,
    ): void {
        if (!$hasResponded) {
            $this->client->error($e, $sandbox, $request, $context);
        }

        $this->dispatchEvent($sandbox, new WorkerErrorOccurredEvent($e, $sandbox));
    }

    /**
     * Invokes the request handled callbacks.
     *
     * Loops over the registered callbacks instead of collecting them.
     *
     * @param \Cake\Http\ServerRequest $request Request that was handled.
     * @param \Psr\Http\Message\ResponseInterface $response Response generated for the request.
     * @param \Cake\Http\BaseApplication $sandbox Sandbox application instance that handled the request.
     * @return void
     */
    protected function invokeRequestHandledCallbacks(
        ServerRequest $request,
        ResponseInterface $response,
        BaseApplication $sandbox,
    ): void {
        foreach ($this->requestHandledCallbacks as $callback) {
            $callback($request, $response, $sandbox);
        }
    }

    /**
     * Registers a closure to be invoked when requests are handled.
     *
     * @param \Closure $callback Callback receiving the request, response, and sandbox.
     * @return $this
     */
    public function onRequestHandled(Closure $callback): static
    {
        $this->requestHandledCallbacks[] = $callback;

        return $this;
    }

    /**
     * Gets the application instance being used by the worker.
     *
     * @return \Cake\Http\BaseApplication Base application instance.
     * @throws \RuntimeException When the worker has not booted.
     */
    public function application(): BaseApplication
    {
        if (!$this->app instanceof BaseApplication) {
            throw new RuntimeException('Worker has not booted. Unable to access application.');
        }

        return $this->app;
    }

    /**
     * Terminates the worker.
     *
     * Dispatches the stopping event and resets the request seam so no
     * operation state survives the worker.
     *
     * @return void
     */
    public function terminate(): void
    {
        $this->dispatchEvent($this->application(), new WorkerStoppingEvent($this->application()));

        CurrentApplication::reset();
    }

    /**
     * Registers the in-worker task routes on the manager.
     *
     * @return void
     */
    protected function registerTaskRoutes(): void
    {
        $manager = $this->getManager();

        $manager->route('POST', '/stimulus/resolve-tasks', fn(ServerRequest $request): Response => $this->resolveHttpTasks($manager, $request));

        $manager->route('POST', '/stimulus/dispatch-tasks', fn(ServerRequest $request): Response => $this->dispatchHttpTasks($manager, $request));
    }

    /**
     * Resolves serialized background tasks received over HTTP.
     *
     * @param \Crustum\Stimulus\StimulusManager $manager Manager resolving the tasks.
     * @param \Cake\Http\ServerRequest $request Incoming task request.
     * @return \Cake\Http\Response Task results response.
     */
    protected function resolveHttpTasks(StimulusManager $manager, ServerRequest $request): Response
    {
        $tasks = $this->readHttpTasks($request);

        if ($tasks === null) {
            return (new Response())->withStatus(403);
        }

        $wait = $request->getData('wait');

        try {
            $results = $manager->tasks()->resolve($tasks, is_numeric($wait) ? (int)$wait : 3000);
        } catch (TaskException | DdException) {
            return (new Response())->withStatus(500);
        } catch (TaskTimeoutException) {
            return (new Response())->withStatus(504);
        }

        return (new Response())->withStringBody(serialize($results));
    }

    /**
     * Dispatches serialized background tasks received over HTTP.
     *
     * @param \Crustum\Stimulus\StimulusManager $manager Manager dispatching the tasks.
     * @param \Cake\Http\ServerRequest $request Incoming task request.
     * @return \Cake\Http\Response Dispatch acknowledgement response.
     */
    protected function dispatchHttpTasks(StimulusManager $manager, ServerRequest $request): Response
    {
        $tasks = $this->readHttpTasks($request);

        if ($tasks === null) {
            return (new Response())->withStatus(403);
        }

        $manager->tasks()->dispatch($tasks);

        return (new Response())->withStatus(200);
    }

    /**
     * Reads the serialized task list from the given request.
     *
     * @param \Cake\Http\ServerRequest $request Incoming task request.
     * @return array<int|string, callable>|null Task callbacks keyed by their given keys, or null when invalid.
     */
    protected function readHttpTasks(ServerRequest $request): ?array
    {
        $payload = $request->getData('tasks');

        if (!is_string($payload)) {
            return null;
        }

        $tasks = unserialize($payload, ['allowed_classes' => true]);

        return is_array($tasks) ? $tasks : null;
    }
}
