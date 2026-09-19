<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Command;

use Cake\Console\ConsoleIo;
use Cake\Console\TestSuite\StubConsoleOutput;
use Cake\Core\Configure;
use Crustum\Stimulus\Bridge\FrankenPhp\ServerStateFile as FrankenPhpServerStateFile;
use Crustum\Stimulus\Bridge\RoadRunner\ServerStateFile as RoadRunnerServerStateFile;
use Crustum\Stimulus\Bridge\Swoole\ServerStateFile as SwooleServerStateFile;
use Crustum\Stimulus\Command\StopCommand;
use Crustum\Stimulus\Test\Support\FakeServerProcessInspector;
use Crustum\Stimulus\Test\TestCase;

/**
 * Feature tests for the stop command.
 *
 * Covers the per-driver teardown with fake inspectors and temporary state
 * files so no real server is needed.
 */
class StopCommandTest extends TestCase
{
    /**
     * Temporary state file paths removed after each test.
     *
     * @var array<int, string>
     */
    protected array $stateFiles = [];

    /**
     * Previous Stimulus configuration restored after each test.
     *
     * @var mixed
     */
    protected mixed $stimulusConfig = null;

    /**
     * Stores the Stimulus configuration for teardown.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->stimulusConfig = Configure::read('Stimulus');
    }

    /**
     * Removes temporary state files and restores configuration.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        foreach ($this->stateFiles as $stateFile) {
            if (file_exists($stateFile)) {
                unlink($stateFile);
            }
        }

        $this->stateFiles = [];

        if ($this->stimulusConfig === null) {
            Configure::delete('Stimulus');
        } else {
            Configure::write('Stimulus', $this->stimulusConfig);
        }

        parent::tearDown();
    }

    /**
     * A running RoadRunner server stops and removes its state file.
     *
     * @return void
     */
    public function testRunningRoadRunnerServerStops(): void
    {
        $inspector = new FakeServerProcessInspector();
        $inspector->running = true;

        $stateFile = new RoadRunnerServerStateFile($path = $this->statePath('rr'));
        $stateFile->writeProcessId(1234);

        $output = new StubConsoleOutput();

        $code = (new StopCommand(
            new FakeServerProcessInspector(),
            $inspector,
            new FakeServerProcessInspector(),
            new SwooleServerStateFile($this->statePath('swoole')),
            $stateFile,
            new FrankenPhpServerStateFile($this->statePath('frankenphp')),
        ))->run(['--server', 'roadrunner'], new ConsoleIo($output, $output));

        $this->assertSame(StopCommand::CODE_SUCCESS, $code);
        $this->assertSame(1, $inspector->stops);
        $this->assertFileDoesNotExist($path);
        $this->assertStringContainsString('Stopping server...', $output->output());
    }

    /**
     * A stopped Swoole server still removes its state file.
     *
     * @return void
     */
    public function testStoppedSwooleServerRemovesStateFile(): void
    {
        $stateFile = new SwooleServerStateFile($path = $this->statePath('swoole'));
        $stateFile->writeProcessIds(1234, 5678);

        $output = new StubConsoleOutput();

        $code = (new StopCommand(
            new FakeServerProcessInspector(),
            new FakeServerProcessInspector(),
            new FakeServerProcessInspector(),
            $stateFile,
            new RoadRunnerServerStateFile($this->statePath('rr')),
            new FrankenPhpServerStateFile($this->statePath('frankenphp')),
        ))->run(['--server', 'swoole'], new ConsoleIo($output, $output));

        $this->assertSame(StopCommand::CODE_ERROR, $code);
        $this->assertFileDoesNotExist($path);
        $this->assertStringContainsString('Swoole server is not running.', $output->output());
    }

    /**
     * A failing Swoole stop keeps the failure exit code.
     *
     * @return void
     */
    public function testFailingSwooleStopReportsError(): void
    {
        $inspector = new FakeServerProcessInspector();
        $inspector->running = true;
        $inspector->stopResult = false;

        $stateFile = new SwooleServerStateFile($path = $this->statePath('swoole'));
        $stateFile->writeProcessIds(1234, 5678);

        $output = new StubConsoleOutput();

        $code = (new StopCommand(
            $inspector,
            new FakeServerProcessInspector(),
            new FakeServerProcessInspector(),
            $stateFile,
            new RoadRunnerServerStateFile($this->statePath('rr')),
            new FrankenPhpServerStateFile($this->statePath('frankenphp')),
        ))->run(['--server', 'swoole'], new ConsoleIo($output, $output));

        $this->assertSame(StopCommand::CODE_ERROR, $code);
        $this->assertStringContainsString('Failed to stop Swoole server.', $output->output());
        $this->assertFileExists($path);
    }

    /**
     * An invalid server choice fails the stop.
     *
     * @return void
     */
    public function testInvalidServerFails(): void
    {
        $output = new StubConsoleOutput();

        $code = (new StopCommand(
            new FakeServerProcessInspector(),
            new FakeServerProcessInspector(),
            new FakeServerProcessInspector(),
            new SwooleServerStateFile($this->statePath('swoole')),
            new RoadRunnerServerStateFile($this->statePath('rr')),
            new FrankenPhpServerStateFile($this->statePath('frankenphp')),
        ))->run(['--server', 'bogus'], new ConsoleIo($output, $output));

        $this->assertSame(StopCommand::CODE_ERROR, $code);
        $this->assertStringContainsString('Invalid server: bogus.', $output->output());
    }

    /**
     * Builds a tracked temporary state file path.
     *
     * @param string $driver Server driver owning the state file.
     * @return string Temporary state file path.
     */
    protected function statePath(string $driver): string
    {
        $path = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'stimulus-stop-test-' . $driver . '-' . getmypid() . '.json';

        if (file_exists($path)) {
            unlink($path);
        }

        $this->stateFiles[] = $path;

        return $path;
    }
}
