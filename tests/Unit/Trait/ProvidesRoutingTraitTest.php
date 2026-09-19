<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Trait;

use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Crustum\Stimulus\Test\Support\RoutingTraitHost;
use Crustum\Stimulus\Test\TestCase;

/**
 * Tests for the worker routing trait.
 *
 * Asserts the registration, lookup, invocation, and listing behavior.
 */
class ProvidesRoutingTraitTest extends TestCase
{
    /**
     * Registered routes are reported for their method and URI.
     *
     * @return void
     */
    public function testHasRouteFor(): void
    {
        $host = new RoutingTraitHost();
        $host->route('GET', '/ping', fn(ServerRequest $request): Response => new Response());

        $this->assertTrue($host->hasRouteFor('GET', '/ping'));
        $this->assertFalse($host->hasRouteFor('POST', '/ping'));
        $this->assertFalse($host->hasRouteFor('GET', '/pong'));
    }

    /**
     * Route callbacks receive the request and return the response.
     *
     * @return void
     */
    public function testInvokeRoute(): void
    {
        $host = new RoutingTraitHost();
        $host->route('GET', '/ping', fn(ServerRequest $request): Response => (new Response())->withStringBody((string)$request->getQuery('name')));

        $response = $host->invokeRoute(new ServerRequest(['url' => '/ping?name=taylor']), 'GET', '/ping');

        $this->assertSame('taylor', (string)$response->getBody());
    }

    /**
     * Registered routes are listed keyed by method and URI.
     *
     * @return void
     */
    public function testGetRoutes(): void
    {
        $host = new RoutingTraitHost();
        $ping = fn(ServerRequest $request): Response => new Response();
        $host->route('GET', '/ping', $ping);

        $this->assertSame(['GET/ping' => $ping], $host->getRoutes());
    }
}
