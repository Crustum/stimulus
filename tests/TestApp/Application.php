<?php
declare(strict_types=1);

namespace TestApp;

use Cake\Http\BaseApplication;
use Cake\Http\MiddlewareQueue;
use Cake\Routing\Middleware\RoutingMiddleware;
use Cake\Routing\RouteBuilder;
use Override;

/**
 * Test application for Crustum/Stimulus plugin tests.
 *
 * Exposes the probe controller on `/probe` and `/live-probe` so worker
 * tests and live server spawns dispatch real controller operations; the
 * `/probe` mapping keeps the preset request params of the isolation suite
 * resolving to the same controller.
 */
class Application extends BaseApplication
{
    /**
     * @inheritDoc
     */
    #[Override]
    public function bootstrap(): void
    {
        parent::bootstrap();

        if (!$this->getPlugins()->has('Crustum/Stimulus')) {
            $this->addPlugin('Crustum/Stimulus', [
                'bootstrap' => true,
                'routes' => true,
            ]);
        }
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function routes(RouteBuilder $routes): void
    {
        $routes->connect('/probe', ['controller' => 'Probe', 'action' => 'index']);
        $routes->connect('/live-probe', ['controller' => 'Probe', 'action' => 'index']);
        $routes->connect('/pipeline', ['controller' => 'Pipeline', 'action' => 'index']);
        $routes->connect('/pipeline/path', ['controller' => 'Pipeline', 'action' => 'path']);
        $routes->connect('/pipeline/session', ['controller' => 'Pipeline', 'action' => 'session']);
        $routes->connect('/pipeline/status/:code', ['controller' => 'Pipeline', 'action' => 'status'])
            ->setPass(['code']);

        $routes->prefix('Admin', function (RouteBuilder $builder): void {
            $builder->connect('/alpha', ['controller' => 'Alpha', 'action' => 'index']);
            $builder->connect('/beta', ['controller' => 'Beta', 'action' => 'index']);
        });
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function middleware(MiddlewareQueue $middlewareQueue): MiddlewareQueue
    {
        return $middlewareQueue->add(new RoutingMiddleware($this));
    }
}
