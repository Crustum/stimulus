<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Bridge\RoadRunner;

use Cake\Core\Configure;
use Closure;
use Crustum\Stimulus\Bridge\RoadRunner\ServerProcessInspector;
use Crustum\Stimulus\Bridge\RoadRunner\ServerStateFile;
use Crustum\Stimulus\Process\SymfonyProcessFactory;
use Crustum\Stimulus\System\PosixExtension;
use Crustum\Stimulus\System\ProcessTerminator;
use Crustum\Stimulus\Test\TestCase;
use Mockery;
use Symfony\Component\Process\Process;

/**
 * Tests for the RoadRunner server process inspector.
 */
class ServerProcessInspectorTest extends TestCase
{
    /**
     * Base path configured before each test.
     *
     * @var mixed
     */
    protected mixed $basePath = null;

    /**
     * Stores the base path so reload tests can restore it.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->basePath = Configure::read('Stimulus.basePath');
    }

    /**
     * Restores the base path and closes Mockery expectations.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        if ($this->basePath === null) {
            Configure::delete('Stimulus.basePath');
        } else {
            Configure::write('Stimulus.basePath', $this->basePath);
        }

        Mockery::close();
        parent::tearDown();
    }

    /**
     * Inspector reports the server running when the master answers the probe.
     *
     * @return void
     */
    public function testCanDetermineIfRoadRunnerServerProcessIsRunningWhenMasterIsRunning(): void
    {
        $inspector = new ServerProcessInspector(
            $processIdFile = new ServerStateFile(sys_get_temp_dir() . '/stimulus-roadrunner-inspector-test.json'),
            new SymfonyProcessFactory(),
            $terminator = Mockery::mock(ProcessTerminator::class),
        );

        $terminator->shouldReceive('isRunning')->with(1)->andReturn(true);

        $processIdFile->writeProcessId(1);

        $this->assertTrue($inspector->serverIsRunning());

        $processIdFile->delete();
    }

    /**
     * Inspector reports the server stopped when the master stays silent.
     *
     * @return void
     */
    public function testCanDetermineIfRoadRunnerServerProcessIsRunningWhenMasterCantBeCommunicatedWith(): void
    {
        $inspector = new ServerProcessInspector(
            $processIdFile = new ServerStateFile(sys_get_temp_dir() . '/stimulus-roadrunner-inspector-test.json'),
            new SymfonyProcessFactory(),
            $terminator = Mockery::mock(ProcessTerminator::class),
        );

        $terminator->shouldReceive('isRunning')->with(1)->andReturn(false);

        $processIdFile->writeProcessId(1);

        $this->assertFalse($inspector->serverIsRunning());

        $processIdFile->delete();
    }

    /**
     * Inspector reloads the server through the RoadRunner binary.
     *
     * @return void
     */
    public function testRoadRunnerServerProcessCanBeReloaded(): void
    {
        if (! class_exists(Process::class)) {
            $this->markTestSkipped('symfony/process is not installed.');
        }

        $basePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'stimulus-roadrunner-binary-test';

        if (! is_dir($basePath)) {
            mkdir($basePath, 0777, true);
        }

        file_put_contents($basePath . DIRECTORY_SEPARATOR . 'rr', '');

        Configure::write('Stimulus.basePath', $basePath);

        $inspector = new ServerProcessInspector(
            $processIdFile = new ServerStateFile(sys_get_temp_dir() . '/stimulus-roadrunner-inspector-test.json'),
            $processFactory = Mockery::mock(SymfonyProcessFactory::class),
            new ProcessTerminator(new PosixExtension()),
        );

        $processIdFile->writeState([
            'host' => '127.0.0.1',
            'rpcPort' => '6002',
        ]);

        $processFactory->shouldReceive('createProcess')->once()->with(
            [
                $basePath . DIRECTORY_SEPARATOR . 'rr',
                'reset',
                '-o',
                'version=3',
                '-o',
                'rpc.listen=tcp://127.0.0.1:6002',
                '-s',
            ],
            $basePath,
        )->andReturn($process = Mockery::mock(Process::class));

        $process->shouldReceive('start')->once();
        $process->shouldReceive('waitUntil')->once()->with(Mockery::type(Closure::class))->andReturnTrue();

        $inspector->reloadServer();

        $processIdFile->delete();
    }

    /**
     * Inspector stops the server by terminating the master process.
     *
     * @return void
     */
    public function testRoadRunnerServerProcessCanBeStopped(): void
    {
        $inspector = new ServerProcessInspector(
            $processIdFile = new ServerStateFile(sys_get_temp_dir() . '/stimulus-roadrunner-inspector-test.json'),
            new SymfonyProcessFactory(),
            $terminator = Mockery::mock(ProcessTerminator::class),
        );

        $terminator->shouldReceive('terminate')->with(1)->andReturn(true);

        $processIdFile->writeProcessId(1);

        $this->assertTrue($inspector->stopServer());

        $processIdFile->delete();
    }
}
