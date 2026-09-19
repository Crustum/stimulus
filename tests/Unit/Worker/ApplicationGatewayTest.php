<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Worker;

use ArrayObject;
use Cake\Event\EventInterface;
use Cake\Event\EventManager;
use Cake\Http\BaseApplication;
use Cake\Http\MiddlewareQueue;
use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Cake\Routing\Exception\MissingRouteException;
use Cake\Routing\RouteBuilder;
use Crustum\Stimulus\Http\CurrentApplication;
use Crustum\Stimulus\Http\RequestContext;
use Crustum\Stimulus\StimulusManager;
use Crustum\Stimulus\Test\Support\FakeClient;
use Crustum\Stimulus\Test\Support\FakeWorker;
use Crustum\Stimulus\Test\TestCase;
use Crustum\Stimulus\Worker\ApplicationGateway;
use Crustum\Stimulus\Worker\Worker;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionProperty;
use TestApp\Application;

/**
 * Tests for the worker application gateway.
 *
 * The gateway must dispatch through the current sandbox application handle
 * every operation, never the stale boot-time instance. The sandbox-swap test
 * below is the GAP-06 acceptance proof: it swaps the worker base application
 * mid-run and asserts each operation went through a fresh clone of the
 * current base.
 */
class ApplicationGatewayTest extends TestCase
{
    /**
     * In-worker manager routes answer without reaching the sandbox handle.
     *
     * @return void
     */
    public function testManagerRoutesAnswerDirectly(): void
    {
        [$app, ,] = $this->createStimulusContext([]);

        $manager = new StimulusManager();
        $manager->route(
            'GET',
            '/ping',
            fn(): Response => (new Response())->withStringBody('pong'),
        );

        $gateway = new ApplicationGateway($app, clone $app, $manager);

        $response = $gateway->handle(new ServerRequest(['url' => '/ping']));

        $this->assertSame('pong', (string)$response->getBody());
    }

    /**
     * Unprefixed requests route through the middleware pipeline.
     *
     * The probe URL carries no preset routing params, so reaching the probe
     * controller proves host routing resolves inside the routing middleware
     * instead of gateway route-table handling.
     *
     * @return void
     */
    public function testUnprefixedRequestsRouteThroughMiddlewarePipeline(): void
    {
        [$app, ,] = $this->createStimulusContext([]);

        $gateway = new ApplicationGateway($app, clone $app, new StimulusManager());

        $response = $gateway->handle(new ServerRequest(['url' => '/probe']));

        $this->assertSame('probe:1', (string)$response->getBody());
    }

    /**
     * Sandbox middleware short-circuits before the controller.
     *
     * A marker middleware prepended by the sandbox answers directly, proving
     * the gateway runs the per-operation queue built from the sandbox rather
     * than calling the application handle.
     *
     * @return void
     */
    public function testSandboxMiddlewareRunsInsideWorkers(): void
    {
        [$app, ,] = $this->createStimulusContext([]);

        $sandbox = new class (CONFIG) extends Application {
            /**
             * Prepends the marker middleware to the framework queue.
             *
             * @param \Cake\Http\MiddlewareQueue $middlewareQueue Framework middleware queue.
             * @return \Cake\Http\MiddlewareQueue Queue answering with the marker body.
             */
            public function middleware(MiddlewareQueue $middlewareQueue): MiddlewareQueue
            {
                return parent::middleware($middlewareQueue)->prepend(
                    fn($request, $handler): ResponseInterface => (new Response())->withStringBody('middleware-marker'),
                );
            }
        };

        $gateway = new ApplicationGateway($app, $sandbox, new StimulusManager());

        $response = $gateway->handle(new ServerRequest(['url' => '/probe']));

        $this->assertSame('middleware-marker', (string)$response->getBody());
    }

    /**
     * The build-middleware event fires around the worker queue.
     *
     * Host applications and plugins hook short-lived requests through
     * `Server.buildMiddleware`, so the gateway must fire it per operation
     * with the queue under construction.
     *
     * @return void
     */
    public function testBuildMiddlewareEventFiresPerOperation(): void
    {
        [$app, ,] = $this->createStimulusContext([]);

        $seen = new ArrayObject();

        $listener = function (EventInterface $event) use ($seen): void {
            $seen->append($event->getData('middleware'));
        };

        EventManager::instance()->on('Server.buildMiddleware', $listener);

        try {
            $gateway = new ApplicationGateway($app, clone $app, new StimulusManager());

            $gateway->handle(new ServerRequest(['url' => '/probe']));
        } finally {
            EventManager::instance()->off('Server.buildMiddleware', $listener);
        }

        $this->assertCount(1, $seen);
        $this->assertInstanceOf(MiddlewareQueue::class, $seen[0]);
    }

    /**
     * Unmatched URLs reach the routing layer instead of a controller.
     *
     * Routing owns unmatched URLs now: the routing middleware reports the
     * missing route, which host error middleware turns into the framework
     * missing-route answer. The test application carries no error
     * middleware, so the routing miss surfaces here directly.
     *
     * @return void
     */
    public function testUnmatchedUrlsAnswerMissingRoute(): void
    {
        [$app, ,] = $this->createStimulusContext([]);

        $gateway = new ApplicationGateway($app, clone $app, new StimulusManager());

        $this->expectException(MissingRouteException::class);

        $gateway->handle(new ServerRequest(['url' => '/missing']));
    }

    /**
     * Named routes resolve on every operation without colliding.
     *
     * The routing middleware reconnects application routes per operation
     * into the process-global table, so the second operation must route the
     * same named route instead of reporting a duplicate.
     *
     * @return void
     */
    public function testNamedRoutesResolveOnRepeatedOperations(): void
    {
        [$app, ,] = $this->createStimulusContext([]);

        $sandbox = new class (CONFIG) extends Application {
            /**
             * Connects the probe controller under a route name.
             *
             * @param \Cake\Routing\RouteBuilder $routes Route builder carrying the application routes.
             * @return void
             */
            public function routes(RouteBuilder $routes): void
            {
                parent::routes($routes);

                $routes->connect(
                    '/named-probe',
                    ['controller' => 'Probe', 'action' => 'index'],
                    ['_name' => 'stimulus:namedProbe'],
                );
            }
        };

        $gateway = new ApplicationGateway($app, $sandbox, new StimulusManager());

        $first = $gateway->handle(new ServerRequest(['url' => '/named-probe']));
        $second = $gateway->handle(new ServerRequest(['url' => '/named-probe']));

        $this->assertSame('probe:1', (string)$first->getBody());
        $this->assertSame('probe:1', (string)$second->getBody());
    }

    /**
     * The worker dispatches through the current sandbox after a mid-run swap.
     *
     * Boots the worker, serves one operation, swaps the base application,
     * and serves another: each response must carry the marker of the base
     * that was current, and each pipeline must run on a sandbox clone rather
     * than the base instance itself.
     *
     * @return void
     */
    public function testWorkerDispatchesThroughCurrentSandboxAfterSwap(): void
    {
        $seen = new ArrayObject();

        $worker = new FakeWorker($this->appFactory(), new FakeClient([]));

        $worker->boot();

        $first = $this->markingApplication('one', $seen);

        $this->swapWorkerApplication($worker, $first);

        $worker->handle(new ServerRequest(['url' => '/first']), new RequestContext());

        $second = $this->markingApplication('two', $seen);

        $this->swapWorkerApplication($worker, $second);

        $worker->handle(new ServerRequest(['url' => '/second']), new RequestContext());

        $this->assertSame($second, $worker->application());
        $this->assertSame(['one', 'two'], array_column($seen->getArrayCopy(), 0));
        $this->assertNotSame(spl_object_id($first), $seen[0][1]);
        $this->assertNotSame(spl_object_id($second), $seen[1][1]);
    }

    /**
     * Terminate resets the current-application seam.
     *
     * @return void
     */
    public function testTerminateResetsCurrentApplicationSeam(): void
    {
        [$app, ,] = $this->createStimulusContext([]);

        $request = new ServerRequest(['url' => '/ping']);

        CurrentApplication::setRequest($request);

        $gateway = new ApplicationGateway($app, clone $app, new StimulusManager());
        $gateway->terminate($request, new Response());

        $this->assertNull(CurrentApplication::getRequest());
    }

    /**
     * Builds an application recording its pipeline runs with a marker.
     *
     * The pipeline runs on sandbox clones, so the shared collector records
     * the marker plus the queue-owning instance identifier for clone proofs.
     * The marker middleware answers directly, ahead of routing, so collector
     * entries prove the run reached the sandbox pipeline.
     *
     * @param string $marker Marker returned as the response body.
     * @param \ArrayObject $seen Shared collector for markers and instance identifiers.
     * @return \Cake\Http\BaseApplication Application marking pipeline runs.
     */
    protected function markingApplication(string $marker, ArrayObject $seen): BaseApplication
    {
        return new class (CONFIG, $seen, $marker) extends Application {
            /**
             * Creates a marking test application.
             *
             * @param string $configDir Directory holding the application bootstrap configuration.
             * @param \ArrayObject $seen Shared collector for markers and instance identifiers.
             * @param string $marker Marker returned as the response body.
             */
            public function __construct(string $configDir, public ArrayObject $seen, public string $marker)
            {
                parent::__construct($configDir);
            }

            /**
             * Prepends the marking middleware to the framework queue.
             *
             * @param \Cake\Http\MiddlewareQueue $middlewareQueue Framework middleware queue.
             * @return \Cake\Http\MiddlewareQueue Queue recording the run and answering with the marker.
             */
            public function middleware(MiddlewareQueue $middlewareQueue): MiddlewareQueue
            {
                $seen = $this->seen;
                $marker = $this->marker;
                $owner = $this;

                return parent::middleware($middlewareQueue)->prepend(
                    function ($request, $handler) use ($seen, $marker, $owner): ResponseInterface {
                        $seen->append([$marker, spl_object_id($owner)]);

                        return (new Response())->withStringBody($marker);
                    },
                );
            }

            /**
             * Records the handling fallback and answers with the marker.
             *
             * @param \Psr\Http\Message\ServerRequestInterface $request Incoming request.
             * @return \Psr\Http\Message\ResponseInterface Marker response.
             */
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->seen->append([$this->marker, spl_object_id($this)]);

                return (new Response())->withStringBody($this->marker);
            }
        };
    }

    /**
     * Swaps the base application of the given worker.
     *
     * @param \Crustum\Stimulus\Worker\Worker $worker Worker receiving the new base application.
     * @param \Cake\Http\BaseApplication $app Base application handling subsequent operations.
     * @return void
     */
    protected function swapWorkerApplication(Worker $worker, BaseApplication $app): void
    {
        $property = new ReflectionProperty(Worker::class, 'app');

        $property->setValue($worker, $app);
    }
}
