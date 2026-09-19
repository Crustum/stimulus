<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Bridge\Swoole;

use Cake\Http\BaseApplication;
use Crustum\Stimulus\Bridge\Swoole\Handlers\OnWorkerStart;
use Crustum\Stimulus\Bridge\Swoole\SwooleClient;
use Crustum\Stimulus\Bridge\Swoole\SwooleExtension;
use Crustum\Stimulus\Bridge\Swoole\WorkerState;
use Crustum\Stimulus\Test\Support\FakeSwooleServer;
use Crustum\Stimulus\Test\TestCase;
use Crustum\Stimulus\Worker\Worker;
use Mockery;
use ReflectionMethod;
use TestApp\Application;

/**
 * Tests for the Swoole worker-start handler.
 *
 * Covers the opcode-cache scenarios and proves the worker boot binds the
 * Swoole client plus the server tables onto the worker manager. The server
 * arrives as an in-memory fake while the application boots the real test
 * application; every test skips cleanly where the extension is absent.
 */
class OnWorkerStartTest extends TestCase
{
    /**
     * Requires the Swoole extension before each test.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        if (!extension_loaded('swoole') && !extension_loaded('openswoole')) {
            $this->markTestSkipped('The Swoole or OpenSwoole extension is not installed on this host.');
        }
    }

    /**
     * The opcode cache clears by default.
     *
     * @return void
     */
    public function testShouldClearOpcodeCacheReturnsTrueByDefault(): void
    {
        $handler = new OnWorkerStart(
            new SwooleExtension(),
            '/base',
            [],
            new WorkerState(),
        );

        $method = new ReflectionMethod($handler, 'shouldClearOpcodeCache');

        $this->assertTrue($method->invoke($handler));
    }

    /**
     * The opcode cache honors the configured flag.
     *
     * @return void
     */
    public function testShouldClearOpcodeCacheReturnsConfiguredValue(): void
    {
        $handler = new OnWorkerStart(
            Mockery::mock(SwooleExtension::class),
            '/base',
            ['stimulusConfig' => ['swoole' => ['clear_opcache' => false]]],
            new WorkerState(),
        );

        $method = new ReflectionMethod($handler, 'shouldClearOpcodeCache');

        $this->assertFalse($method->invoke($handler));
    }

    /**
     * Worker starts boot the worker with the Swoole client and tables.
     *
     * @return void
     */
    public function testWorkerStartBootsWorkerWithClientAndTables(): void
    {
        $workerState = new WorkerState();

        $workerState->tables = ['example' => new FakeSwooleServer()];

        $handler = new OnWorkerStart(
            new SwooleExtension(),
            dirname(CONFIG),
            ['appClass' => Application::class, 'appName' => 'Stimulus'],
            $workerState,
            false,
        );

        $handler(new FakeSwooleServer(), 1);

        $this->assertInstanceOf(Worker::class, $workerState->worker);
        $this->assertInstanceOf(SwooleClient::class, $workerState->client);
        $this->assertSame(1, $workerState->workerId);
        $this->assertSame(
            $workerState->tables['example'],
            $workerState->worker->getManager()->table('example'),
        );
    }

    /**
     * The environment application class wins over the fallback chain.
     *
     * @return void
     */
    public function testApplicationClassResolvesEnvironmentValue(): void
    {
        $previous = $_SERVER['STIMULUS_APP_CLASS'] ?? null;

        $_SERVER['STIMULUS_APP_CLASS'] = Application::class;

        try {
            $handler = new OnWorkerStart(
                new SwooleExtension(),
                '/base',
                [],
                new WorkerState(),
            );

            $method = new ReflectionMethod($handler, 'applicationClass');

            $this->assertSame(Application::class, $method->invoke($handler));
        } finally {
            if ($previous === null) {
                unset($_SERVER['STIMULUS_APP_CLASS']);
            } else {
                $_SERVER['STIMULUS_APP_CLASS'] = $previous;
            }
        }
    }

    /**
     * An unresolvable class falls back to the base application class.
     *
     * @return void
     */
    public function testApplicationClassGuardsUnknownClasses(): void
    {
        $handler = new OnWorkerStart(
            new SwooleExtension(),
            '/base',
            [],
            new WorkerState(),
        );

        $method = new ReflectionMethod($handler, 'applicationClass');

        $this->assertSame(
            BaseApplication::class,
            $method->invoke($handler),
        );
    }
}
