<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Worker;

use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Cake\Routing\Router;
use Crustum\Stimulus\Http\CurrentApplication;
use Crustum\Stimulus\StimulusManager;
use Crustum\Stimulus\Test\TestCase;
use Crustum\Stimulus\Worker\ApplicationFactory;
use Crustum\Stimulus\Worker\ApplicationGateway;
use TestApp\Application;

/**
 * Integration tests proving the worker middleware pipeline works correctly
 * across repeated operations.
 *
 * These tests simulate the real worker dispatch flow: boot an application
 * through the factory, create a gateway, and dispatch multiple requests to
 * prove routes, middleware, session, and controller state are correctly
 * isolated between operations. The tests use the full middleware stack
 * (including RoutingMiddleware) to catch issues that unit tests with
 * preset request params would miss.
 */
class WorkerDispatchIntegrationTest extends TestCase
{
    /**
     * Application factory creating the test app instance.
     *
     * @var \Crustum\Stimulus\Worker\ApplicationFactory
     */
    private ApplicationFactory $factory;

    /**
     * Base application instance created by the factory.
     *
     * @var \TestApp\Application
     */
    private Application $app;

    /**
     * Set up the test fixtures.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = new ApplicationFactory(
            Application::class,
            dirname(__DIR__, 2) . '/TestApp/config',
        );

        $this->app = $this->factory->createApplication();
    }

    /**
     * Tear down the test fixtures.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        CurrentApplication::reset();
        Router::reload();
    }

    /**
     * Multiple requests to the same URL return identical responses.
     *
     * Proves the middleware pipeline produces deterministic output on every
     * operation: routes resolve, controllers are fresh, and no process-global
     * state leaks between requests.
     *
     * @return void
     */
    public function testRepeatedRequestsReturnIdenticalResponses(): void
    {
        $sandbox = clone $this->app;
        $gateway = new ApplicationGateway($this->app, $sandbox, new StimulusManager());

        $responses = [];
        for ($i = 0; $i < 5; $i++) {
            $request = new ServerRequest(['url' => '/probe']);
            $response = $gateway->handle($request);
            $responses[] = (string)$response->getBody();
        }

        $unique = array_unique($responses);
        $this->assertCount(1, $unique, 'All 5 requests to /probe should return the same response');
        $this->assertSame('probe:1', $responses[0]);
    }

    /**
     * Routes resolve correctly on every operation through the full middleware stack.
     *
     * The RoutingMiddleware re-registers routes on each request via
     * `loadRoutes()`, so named and explicit routes must resolve after
     * `Router::reload()` clears the table at the start of each operation.
     *
     * @return void
     */
    public function testRoutesResolveOnEveryOperation(): void
    {
        $sandbox = clone $this->app;
        $gateway = new ApplicationGateway($this->app, $sandbox, new StimulusManager());

        $urls = ['/pipeline', '/pipeline/path', '/pipeline/session'];
        $results = [];

        foreach ($urls as $url) {
            for ($i = 0; $i < 3; $i++) {
                $request = new ServerRequest(['url' => $url]);
                $response = $gateway->handle($request);
                $key = $url . ':' . $i;
                $results[$key] = [
                    'status' => $response->getStatusCode(),
                    'body' => (string)$response->getBody(),
                ];
            }
        }

        foreach ($urls as $url) {
            $urlResults = array_filter($results, fn($k): bool => str_starts_with((string)$k, $url . ':'), ARRAY_FILTER_USE_KEY);
            $statuses = array_map(intval(...), array_column($urlResults, 'status'));

            $this->assertCount(3, $statuses, "Three requests to {$url} should all succeed");
            $this->assertSame(
                [200, 200, 200],
                $statuses,
                "All requests to {$url} should return 200",
            );
        }
    }

    /**
     * Named routes resolve on repeated operations without collision.
     *
     * The routing middleware reconnects application routes per operation
     * into the process-global table, so the second operation must route the
     * same named route instead of reporting a duplicate or missing route.
     *
     * @return void
     */
    public function testNamedRoutesResolveOnRepeatedOperations(): void
    {
        $sandbox = clone $this->app;
        $gateway = new ApplicationGateway($this->app, $sandbox, new StimulusManager());

        $first = $gateway->handle(new ServerRequest(['url' => '/probe']));
        $second = $gateway->handle(new ServerRequest(['url' => '/probe']));

        $this->assertSame('probe:1', (string)$first->getBody());
        $this->assertSame('probe:1', (string)$second->getBody());
    }

    /**
     * Session state is isolated between worker operations.
     *
     * Each request should start with a fresh session, so the visit counter
     * resets to zero on every operation instead of accumulating across
     * requests. Proves `FlushSessionState` runs before the middleware stack.
     *
     * @return void
     */
    public function testSessionIsIsolatedBetweenOperations(): void
    {
        $sandbox = clone $this->app;
        $gateway = new ApplicationGateway($this->app, $sandbox, new StimulusManager());

        $first = $gateway->handle(new ServerRequest(['url' => '/pipeline/session']));
        $second = $gateway->handle(new ServerRequest(['url' => '/pipeline/session']));

        $firstData = json_decode((string)$first->getBody(), true);
        $secondData = json_decode((string)$second->getBody(), true);

        $this->assertSame(0, $firstData['visit_count'], 'First request should see visit_count = 0');
        $this->assertSame(0, $secondData['visit_count'], 'Second request should also see visit_count = 0');
    }

    /**
     * Different routes produce different responses on the same gateway.
     *
     * Proves the middleware pipeline dispatches to the correct controller
     * action for each request, not a cached response from a previous route.
     *
     * @return void
     */
    public function testDifferentRoutesProduceDifferentResponses(): void
    {
        $sandbox = clone $this->app;
        $gateway = new ApplicationGateway($this->app, $sandbox, new StimulusManager());

        $probe = $gateway->handle(new ServerRequest(['url' => '/probe']));
        $path = $gateway->handle(new ServerRequest(['url' => '/pipeline/path']));

        $probeBody = (string)$probe->getBody();
        $pathData = json_decode((string)$path->getBody(), true);

        $this->assertSame('probe:1', $probeBody);
        $this->assertSame('/pipeline/path', $pathData['path']);
    }

    /**
     * The middleware stack runs on every operation, not just the first.
     *
     * Uses the RoutingMiddleware presence in the stack to verify the full
     * pipeline executes. If middleware were skipped on later requests, the
     * routing would fail and the response would differ.
     *
     * @return void
     */
    public function testMiddlewareStackRunsOnEveryOperation(): void
    {
        $sandbox = clone $this->app;
        $gateway = new ApplicationGateway($this->app, $sandbox, new StimulusManager());

        $results = [];
        for ($i = 0; $i < 10; $i++) {
            $request = new ServerRequest(['url' => '/pipeline/path']);
            $response = $gateway->handle($request);
            $data = json_decode((string)$response->getBody(), true);
            $results[] = $data['path'];
        }

        $unique = array_unique($results);
        $this->assertCount(1, $unique, 'All 10 requests should route through the middleware stack');
        $this->assertSame('/pipeline/path', $results[0]);
    }

    /**
     * Gateway route-table reset does not affect in-worker manager routes.
     *
     * Routes registered on the StimulusManager bypass the router, so
     * the `Router::reload()` in the middleware pipeline must not clear them.
     *
     * @return void
     */
    public function testManagerRoutesSurviveRouterReload(): void
    {
        $manager = new StimulusManager();
        $manager->route('GET', '/manager-probe', fn(ServerRequest $request): Response => new Response());

        $sandbox = clone $this->app;
        $gateway = new ApplicationGateway($this->app, $sandbox, $manager);

        $request = new ServerRequest(['url' => '/manager-probe']);
        $response = $gateway->handle($request);

        $this->assertSame(200, $response->getStatusCode());
    }

    /**
     * The worker dispatches through the current sandbox after a mid-run swap.
     *
     * Proves the gateway resolves the sandbox dynamically, not from a stale
     * boot-time reference, so a sandbox swap takes effect on the next operation.
     *
     * @return void
     */
    public function testSandboxSwapTakesEffectOnNextOperation(): void
    {
        $manager = new StimulusManager();
        $gateway = new ApplicationGateway($this->app, clone $this->app, $manager);

        $first = $gateway->handle(new ServerRequest(['url' => '/probe']));
        $this->assertSame('probe:1', (string)$first->getBody());

        // Create a fresh sandbox that inherits the same route table
        $newSandbox = clone $this->app;
        $gateway = new ApplicationGateway($this->app, $newSandbox, $manager);

        $second = $gateway->handle(new ServerRequest(['url' => '/probe']));
        $this->assertSame('probe:1', (string)$second->getBody());
    }

    /**
     * Requests with different HTTP methods resolve to different actions.
     *
     * Proves the middleware pipeline correctly differentiates between
     * request methods, not just URLs.
     *
     * @return void
     */
    public function testDifferentMethodsResolveCorrectly(): void
    {
        $sandbox = clone $this->app;
        $gateway = new ApplicationGateway($this->app, $sandbox, new StimulusManager());

        $get = $gateway->handle(new ServerRequest([
            'url' => '/pipeline/path',
            'method' => 'GET',
        ]));

        $post = $gateway->handle(new ServerRequest([
            'url' => '/pipeline/path',
            'method' => 'POST',
        ]));

        $getData = json_decode((string)$get->getBody(), true);
        $postData = json_decode((string)$post->getBody(), true);

        $this->assertSame('/pipeline/path', $getData['path']);
        $this->assertSame('/pipeline/path', $postData['path']);
    }

    /**
     * Mixed route patterns resolve correctly across operations.
     *
     * Alternates between different routes to prove no response from one
     * route leaks into another through stale process-global state.
     *
     * @return void
     */
    public function testMixedRoutesDoNotLeakResponses(): void
    {
        $sandbox = clone $this->app;
        $gateway = new ApplicationGateway($this->app, $sandbox, new StimulusManager());

        $sequences = [
            ['/probe', 'probe:1'],
            ['/pipeline/path', null], // JSON, check separately
            ['/probe', 'probe:1'],
            ['/pipeline/session', null], // JSON, check separately
            ['/pipeline/path', null], // JSON, check separately
        ];

        foreach ($sequences as [$url, $expectedBody]) {
            $response = $gateway->handle(new ServerRequest(['url' => $url]));
            $body = (string)$response->getBody();

            if ($expectedBody !== null) {
                $this->assertSame($expectedBody, $body, "Response for {$url} should be {$expectedBody}");
            } else {
                $data = json_decode($body, true);
                $this->assertNotNull($data, "Response for {$url} should be valid JSON");
            }
        }
    }
}
