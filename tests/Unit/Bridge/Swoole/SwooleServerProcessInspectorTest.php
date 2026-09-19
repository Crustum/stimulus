<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Bridge\Swoole;

use Crustum\Stimulus\Bridge\Swoole\ServerProcessInspector;
use Crustum\Stimulus\Bridge\Swoole\ServerStateFile;
use Crustum\Stimulus\Bridge\Swoole\SignalDispatcher;
use Crustum\Stimulus\Process\Exec;
use Crustum\Stimulus\Test\TestCase;
use Mockery;

/**
 * Tests for the Swoole server process inspector.
 *
 * Covers running detection through the manager and master processes plus
 * server stops. The dispatcher and shell arrive as mocks while the state
 * file stays real on a temporary path; every test skips cleanly where the
 * extension is absent.
 */
class SwooleServerProcessInspectorTest extends TestCase
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
     * The inspector reports the server running when the manager answers.
     *
     * @return void
     */
    public function testCanDetermineIfSwooleServerProcessIsRunningWhenManagerIsRunning(): void
    {
        $path = $this->statePath();

        $inspector = new ServerProcessInspector(
            $dispatcher = Mockery::mock(SignalDispatcher::class),
            $processIdFile = new ServerStateFile($path),
            Mockery::mock(Exec::class),
        );

        $dispatcher->shouldReceive('canCommunicateWith')->with(2)->andReturn(true);

        $processIdFile->writeProcessIds(1, 2);

        $this->assertTrue($inspector->serverIsRunning());

        $processIdFile->delete();
    }

    /**
     * The inspector reports the server stopped when the manager stays silent.
     *
     * @return void
     */
    public function testCanDetermineIfSwooleServerProcessIsRunningWhenManagerCantBeCommunicatedWith(): void
    {
        $path = $this->statePath();

        $inspector = new ServerProcessInspector(
            $dispatcher = Mockery::mock(SignalDispatcher::class),
            $processIdFile = new ServerStateFile($path),
            Mockery::mock(Exec::class),
        );

        $dispatcher->shouldReceive('canCommunicateWith')->with(2)->andReturn(false);

        $processIdFile->writeProcessIds(1, 2);

        $this->assertFalse($inspector->serverIsRunning());

        $processIdFile->delete();
    }

    /**
     * The inspector reports the server running when only the master answers.
     *
     * @return void
     */
    public function testCanDetermineIfSwooleServerProcessIsRunningWhenOnlyMasterIsRunning(): void
    {
        $path = $this->statePath();

        $inspector = new ServerProcessInspector(
            $dispatcher = Mockery::mock(SignalDispatcher::class),
            $processIdFile = new ServerStateFile($path),
            Mockery::mock(Exec::class),
        );

        $dispatcher->shouldReceive('canCommunicateWith')->with(1)->andReturn(true);

        $processIdFile->writeProcessIds(1, 0);

        $this->assertTrue($inspector->serverIsRunning());

        $processIdFile->delete();
    }

    /**
     * The inspector reports the server stopped when the master stays silent.
     *
     * @return void
     */
    public function testCanDetermineIfSwooleServerProcessIsRunningWhenMasterCantBeCommunicatedWith(): void
    {
        $path = $this->statePath();

        $inspector = new ServerProcessInspector(
            $dispatcher = Mockery::mock(SignalDispatcher::class),
            $processIdFile = new ServerStateFile($path),
            Mockery::mock(Exec::class),
        );

        $dispatcher->shouldReceive('canCommunicateWith')->with(1)->andReturn(false);

        $processIdFile->writeProcessIds(1, 0);

        $this->assertFalse($inspector->serverIsRunning());

        $processIdFile->delete();
    }

    /**
     * The inspector stops the master, manager, and worker processes.
     *
     * @return void
     */
    public function testSwooleServerProcessCanBeStopped(): void
    {
        $path = $this->statePath();

        $inspector = new ServerProcessInspector(
            $dispatcher = Mockery::mock(SignalDispatcher::class),
            $processIdFile = new ServerStateFile($path),
            $exec = Mockery::mock(Exec::class),
        );

        $processIdFile->writeProcessIds(3, 2);

        $exec->shouldReceive('run')->once()->with('pgrep -P 2')->andReturn(['4', '5']);

        foreach ([3, 2, 4, 5] as $processId) {
            $dispatcher->shouldReceive('signal')->with($processId, SIGKILL)->once();
        }

        $this->assertTrue($inspector->stopServer());

        $processIdFile->delete();
    }

    /**
     * Builds a temporary state file path.
     *
     * @return string Temporary state file path.
     */
    protected function statePath(): string
    {
        $path = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'stimulus-swoole-inspector-test-' . getmypid() . '.json';

        if (file_exists($path)) {
            unlink($path);
        }

        return $path;
    }
}
