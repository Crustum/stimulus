<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test;

use Cake\Http\BaseApplication;
use Cake\TestSuite\TestCase as CakeTestCase;
use Crustum\Stimulus\Test\Support\FakeClient;
use Crustum\Stimulus\Test\Support\FakeWorker;
use Crustum\Stimulus\Worker\ApplicationFactory;
use Mockery;
use TestApp\Application;

/**
 * Default test case for Stimulus plugin tests.
 *
 * Boots the test application through the worker application factory and
 * returns the booted fake worker. Test classes must extend this base
 * instead of the raw PHPUnit case so worker construction stays in one place.
 */
abstract class TestCase extends CakeTestCase
{
    /**
     * Builds a booted worker context for the given requests.
     *
     * Returns an `[$app, $worker, $client]` triple. The factory boots the test
     * application, the fake worker extends the real worker, and the client
     * records responses and errors in memory.
     *
     * @param array<int, mixed> $requests Requests or task payloads to serve.
     * @return array{0: \Cake\Http\BaseApplication, 1: \Crustum\Stimulus\Test\Support\FakeWorker, 2: \Crustum\Stimulus\Test\Support\FakeClient} Booted application, worker, and client triple.
     */
    protected function createStimulusContext(array $requests): array
    {
        $factory = new ApplicationFactory(Application::class, CONFIG);

        $client = new FakeClient($requests);

        $worker = new FakeWorker($factory, $client);

        $worker->boot();

        return [$worker->application(), $worker, $client];
    }

    /**
     * Builds the application factory booting the test application.
     *
     * Hands tests the factory used for the application under test.
     *
     * @return \Crustum\Stimulus\Worker\ApplicationFactory Factory booting the test application.
     */
    protected function appFactory(): ApplicationFactory
    {
        return new ApplicationFactory(Application::class, CONFIG);
    }

    /**
     * Boots the test application through the application factory.
     *
     * Applies the default warm services, so factory tests share the worker boot path.
     *
     * @return \Cake\Http\BaseApplication Booted test application.
     */
    protected function createApplication(): BaseApplication
    {
        return $this->appFactory()->createApplication();
    }

    /**
     * Loads the plugin configuration array.
     *
     * Returns the plugin configuration file contents for Swoole table sizing.
     *
     * @return array<string, mixed> Plugin configuration file contents.
     */
    protected function config(): array
    {
        return require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'stimulus.php';
    }

    /**
     * Closes Mockery expectations after each test.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }
}
