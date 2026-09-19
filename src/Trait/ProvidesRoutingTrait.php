<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Trait;

use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Closure;

/**
 * Registers and invokes in-worker routes handled by the long-lived process.
 *
 * Request and response types are the request and response so route
 * callbacks operate on framework-native HTTP objects.
 */
trait ProvidesRoutingTrait
{
    /**
     * Registered worker routes keyed by concatenated method and URI.
     *
     * @var array<string, \Closure>
     */
    protected array $routes = [];

    /**
     * Registers a worker route.
     *
     * @param string $method HTTP method the route responds to.
     * @param string $uri URI the route responds to.
     * @param \Closure $callback Callback handling matching requests.
     * @return void
     */
    public function route(string $method, string $uri, Closure $callback): void
    {
        $this->routes[$method . $uri] = $callback;
    }

    /**
     * Determines if a route exists for the given method and URI.
     *
     * @param string $method HTTP method to check.
     * @param string $uri URI to check.
     * @return bool Whether a matching route is registered.
     */
    public function hasRouteFor(string $method, string $uri): bool
    {
        return isset($this->routes[$method . $uri]);
    }

    /**
     * Invokes the route for the given method and URI.
     *
     * @param \Cake\Http\ServerRequest $request Incoming request handed to the route callback.
     * @param string $method HTTP method of the route to invoke.
     * @param string $uri URI of the route to invoke.
     * @return \Cake\Http\Response Route response.
     */
    public function invokeRoute(ServerRequest $request, string $method, string $uri): Response
    {
        return call_user_func($this->routes[$method . $uri], $request);
    }

    /**
     * Gets the registered worker routes.
     *
     * @return array<string, \Closure> Registered routes keyed by concatenated method and URI.
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }
}
