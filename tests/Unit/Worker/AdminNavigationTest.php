<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Worker;

use Cake\Http\ServerRequest;
use Cake\Routing\Exception\MissingRouteException;
use Cake\Routing\Router;
use Crustum\Stimulus\Http\CurrentApplication;
use Crustum\Stimulus\StimulusManager;
use Crustum\Stimulus\Test\TestCase;
use Crustum\Stimulus\Worker\ApplicationFactory;
use Crustum\Stimulus\Worker\ApplicationGateway;
use TestApp\Application;

/**
 * Reproduces the real-world worker failure pattern: navigating between
 * different admin controllers in sequence within a single worker process.
 *
 * On the host app this caused:
 * - `CakeException: AdminExt alias already loaded` (ComponentRegistry leak)
 * - `302 → /login` on authenticated pages (session/auth state leak)
 * - `Cannot describe crustum` (TableLocator auto-table leak)
 * - Different response bodies for the same URL across requests
 *
 * Each test navigates between controllers that load components under
 * different aliases, then verifies the next request gets fresh state.
 */
class AdminNavigationTest extends TestCase
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
     * Navigating between two different admin controllers returns correct responses.
     *
     * Reproduces the host app pattern: user visits /admin/test-scopes then
     * /admin/projects. Each controller must get its own ComponentRegistry.
     *
     * Without `FlushContainerResolutions`, the second controller would fail
     * with "ComponentRegistry alias already loaded".
     *
     * @return void
     */
    public function testNavigatingBetweenAdminControllersReturnsCorrectResponses(): void
    {
        $sandbox = clone $this->app;
        $gateway = new ApplicationGateway($this->app, $sandbox, new StimulusManager());

        // Request 1: Alpha controller
        $response1 = $gateway->handle(new ServerRequest(['url' => '/admin/alpha']));
        $data1 = json_decode((string)$response1->getBody(), true);

        // Request 2: Beta controller (different alias on same component)
        $response2 = $gateway->handle(new ServerRequest(['url' => '/admin/beta']));
        $data2 = json_decode((string)$response2->getBody(), true);

        // Request 3: Back to Alpha (must not collide with Beta's alias)
        $response3 = $gateway->handle(new ServerRequest(['url' => '/admin/alpha']));
        $data3 = json_decode((string)$response3->getBody(), true);

        $this->assertSame(200, $response1->getStatusCode(), 'Alpha should return 200');
        $this->assertSame('Alpha', $data1['controller'], 'First request should route to Alpha');

        $this->assertSame(200, $response2->getStatusCode(), 'Beta should return 200');
        $this->assertSame('Beta', $data2['controller'], 'Second request should route to Beta');

        $this->assertSame(200, $response3->getStatusCode(), 'Alpha again should return 200');
        $this->assertSame('Alpha', $data3['controller'], 'Third request should route back to Alpha');
    }

    /**
     * Session data is isolated between requests.
     *
     * Reproduces the host app pattern: a controller writes to the session,
     * the next request must start with a fresh session (visit_count = 0).
     *
     * Without the session flush, visit_count would accumulate across requests.
     *
     * @return void
     */
    public function testSessionIsFreshOnEveryRequest(): void
    {
        $sandbox = clone $this->app;
        $gateway = new ApplicationGateway($this->app, $sandbox, new StimulusManager());

        $results = [];
        for ($i = 0; $i < 5; $i++) {
            $response = $gateway->handle(new ServerRequest(['url' => '/pipeline/session']));
            $results[] = json_decode((string)$response->getBody(), true);
        }

        foreach ($results as $i => $data) {
            $this->assertSame(
                0,
                $data['visit_count'],
                "Request {$i} should see visit_count = 0, got {$data['visit_count']}",
            );
        }
    }

    /**
     * A rapid sequence of 20 mixed requests all return 200.
     *
     * Simulates a real browser session clicking through multiple pages quickly.
     * Every request must get a fresh middleware pipeline and correct routing.
     *
     * @return void
     */
    public function testRapidMixedNavigationAllReturns200(): void
    {
        $sandbox = clone $this->app;
        $gateway = new ApplicationGateway($this->app, $sandbox, new StimulusManager());

        $urls = [
            '/admin/alpha',
            '/admin/beta',
            '/probe',
            '/pipeline/path',
            '/admin/alpha',
            '/pipeline/session',
            '/admin/beta',
            '/probe',
            '/pipeline/path',
            '/admin/alpha',
            '/admin/beta',
            '/probe',
            '/pipeline/session',
            '/admin/alpha',
            '/pipeline/path',
            '/admin/beta',
            '/probe',
            '/admin/alpha',
            '/pipeline/session',
            '/admin/beta',
        ];

        foreach ($urls as $i => $url) {
            $response = $gateway->handle(new ServerRequest(['url' => $url]));
            $this->assertSame(
                200,
                $response->getStatusCode(),
                "Request {$i} to {$url} should return 200, got {$response->getStatusCode()}",
            );
        }
    }

    /**
     * The same URL always returns the same response body across 50 requests.
     *
     * Reproduces the host app symptom: "each refresh shows different responses".
     * If any process-global state leaks, the response body would differ.
     *
     * @return void
     */
    public function testSameUrlReturnsSameBody50Times(): void
    {
        $sandbox = clone $this->app;
        $gateway = new ApplicationGateway($this->app, $sandbox, new StimulusManager());

        $bodies = [];
        for ($i = 0; $i < 50; $i++) {
            $response = $gateway->handle(new ServerRequest(['url' => '/pipeline/path']));
            $bodies[] = (string)$response->getBody();
        }

        $unique = array_unique($bodies);
        $this->assertCount(
            1,
            $unique,
            'All 50 requests should return the same body. Unique bodies: ' . implode(' | ', $unique),
        );
    }

    /**
     * Navigating to a non-existent route does not corrupt subsequent routes.
     *
     * A missing route on one request must not cause the next valid request to fail.
     *
     * @return void
     */
    public function test404DoesNotCorruptNextRequest(): void
    {
        $sandbox = clone $this->app;
        $gateway = new ApplicationGateway($this->app, $sandbox, new StimulusManager());

        // Request 1: valid route
        $ok = $gateway->handle(new ServerRequest(['url' => '/probe']));
        $this->assertSame(200, $ok->getStatusCode());

        // Request 2: invalid route — may throw MissingRouteException (500 in worker)
        try {
            $gateway->handle(new ServerRequest(['url' => '/does-not-exist']));
        } catch (MissingRouteException) {
            // Expected in test app without fallback routes
        }

        // Request 3: valid route again (must not be corrupted by the failure)
        $okAgain = $gateway->handle(new ServerRequest(['url' => '/probe']));
        $this->assertSame(200, $okAgain->getStatusCode(), 'Valid route must work after a missing route');
        $this->assertSame('probe:1', (string)$okAgain->getBody());
    }

    /**
     * Mixed admin and non-admin routes resolve correctly in alternating pattern.
     *
     * Simulates the host app where the user navigates between admin pages
     * and public pages (e.g. /admin/projects then /health).
     *
     * @return void
     */
    public function testAlternatingAdminAndPublicRoutes(): void
    {
        $sandbox = clone $this->app;
        $gateway = new ApplicationGateway($this->app, $sandbox, new StimulusManager());

        $pairs = [
            ['/admin/alpha', 'Alpha'],
            ['/probe', 'probe:1'],
            ['/admin/beta', 'Beta'],
            ['/pipeline/path', null],
            ['/admin/alpha', 'Alpha'],
            ['/probe', 'probe:1'],
        ];

        foreach ($pairs as $i => [$url, $expectedBody]) {
            $response = $gateway->handle(new ServerRequest(['url' => $url]));
            $this->assertSame(
                200,
                $response->getStatusCode(),
                "Request {$i} to {$url} should return 200",
            );

            $body = (string)$response->getBody();

            if ($expectedBody !== null) {
                // Check that the body contains the expected controller name
                $this->assertStringContainsString(
                    $expectedBody,
                    $body,
                    "Request {$i} to {$url} should contain {$expectedBody}",
                );
            } else {
                $data = json_decode($body, true);
                $this->assertNotNull($data, "Request {$i} to {$url} should return valid JSON");
            }
        }
    }
}
