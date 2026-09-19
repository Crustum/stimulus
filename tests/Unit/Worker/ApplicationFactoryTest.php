<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Worker;

use Cake\Http\BaseApplication;
use Cake\Http\ServerRequest;
use Crustum\Stimulus\Test\TestCase;
use ReflectionClass;
use stdClass;
use TestApp\Application;

/**
 * Tests for the worker application factory.
 *
 * Boots the configured application class with its config directory and
 * applies the warm services through the application container.
 */
class ApplicationFactoryTest extends TestCase
{
    /**
     * The factory creates the configured booted application.
     *
     * @return void
     */
    public function testApplicationCanBeCreated(): void
    {
        $app = $this->createApplication();

        $this->assertInstanceOf(Application::class, $app);
        $this->assertTrue($app->getPlugins()->has('Crustum/Stimulus'));
    }

    /**
     * The factory retains the initial instances for collaborators.
     *
     * The worker boot passes the bridge client here and collaborators read it back.
     *
     * @return void
     */
    public function testInitialInstancesAreRetained(): void
    {
        $factory = $this->appFactory();

        $app = $factory->createApplication(['probe' => 'value']);

        $this->assertInstanceOf(BaseApplication::class, $app);
        $this->assertSame(['probe' => 'value'], $factory->getInitialInstances());
    }

    /**
     * Warm services resolve through the application container.
     *
     * The factory resolves a container binding shared for the test and
     * ignores unknown service names.
     *
     * @return void
     */
    public function testServicesCanBeWarmed(): void
    {
        $app = $this->createApplication();

        $app->getContainer()->addShared('stimulus.probe', new stdClass());

        $this->assertSame($app, $this->appFactory()->warm($app, ['stimulus.probe', 'unknown-service']));
        $this->assertInstanceOf(stdClass::class, $app->getContainer()->get('stimulus.probe'));
    }

    /**
     * Warming with an empty list falls back to the configured warm services.
     *
     * @return void
     */
    public function testWarmFallsBackToConfiguredServices(): void
    {
        $app = $this->createApplication();

        $this->assertSame($app, $this->appFactory()->warm($app, $this->config()['Stimulus']['warm']));
    }

    /**
     * Sandboxes share warmed instances from the base application.
     *
     * Preloaded services resolve to the same instance inside the sandbox,
     * which is what keeps the boot-time preload effective per operation.
     *
     * @return void
     */
    public function testSandboxSharesWarmedInstances(): void
    {
        $app = $this->createApplication();
        $service = new stdClass();

        $app->getContainer()->addShared('stimulus.probe', $service);

        $sandbox = $this->appFactory()->createSandbox($app);

        $this->assertNotSame($app, $sandbox);
        $this->assertNotSame($app->getContainer(), $sandbox->getContainer());
        $this->assertSame($service, $sandbox->getContainer()->get('stimulus.probe'));
    }

    /**
     * Sandbox registrations never reach the base application.
     *
     * Bindings added while handling an operation stay in the sandbox copy
     * of the definition aggregate, so the base definition set is stable
     * across operations.
     *
     * @return void
     */
    public function testSandboxRegistrationsStayOutOfBaseApplication(): void
    {
        $app = $this->createApplication();
        $before = $this->definitionCount($app->getContainer());

        $sandbox = $this->appFactory()->createSandbox($app);
        $sandbox->getContainer()->add('sandbox.only', new stdClass());

        $this->assertFalse($app->getContainer()->has('sandbox.only'));
        $this->assertSame($before, $this->definitionCount($app->getContainer()));
    }

    /**
     * Sandboxes start without request-scoped bindings.
     *
     * A binding registered while handling one operation is gone from the
     * next sandbox, so each operation re-registers request state fresh.
     *
     * @return void
     */
    public function testSandboxStartsWithoutRequestScopedBindings(): void
    {
        $app = $this->createApplication();

        $first = $this->appFactory()->createSandbox($app);
        $first->getContainer()->add(ServerRequest::class, new ServerRequest(['url' => '/first']));

        $second = $this->appFactory()->createSandbox($app);

        $this->assertFalse($second->getContainer()->has(ServerRequest::class));
    }

    /**
     * Counts the definitions registered on the given container.
     *
     * @param object $container Container holding the definition aggregate.
     * @return int Number of registered definitions.
     */
    protected function definitionCount(object $container): int
    {
        $aggregate = (new ReflectionClass($container))->getProperty('definitions')->getValue($container);

        $count = 0;

        foreach ($aggregate as $definition) {
            $count++;
        }

        return $count;
    }
}
