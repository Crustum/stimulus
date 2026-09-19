<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Command;

use Cake\Console\ConsoleIo;
use Cake\Console\TestSuite\StubConsoleOutput;
use Cake\Core\Configure;
use Crustum\Stimulus\Command\ReloadCommand;
use Crustum\Stimulus\Test\Support\FakeServerProcessInspector;
use Crustum\Stimulus\Test\TestCase;

/**
 * Feature tests for the reload command.
 *
 * Covers the per-driver reload delegation with fake inspectors so no real
 * server is needed.
 */
class ReloadCommandTest extends TestCase
{
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
     * Restores the Stimulus configuration after each test.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        if ($this->stimulusConfig === null) {
            Configure::delete('Stimulus');
        } else {
            Configure::write('Stimulus', $this->stimulusConfig);
        }

        parent::tearDown();
    }

    /**
     * A running RoadRunner server reloads its workers.
     *
     * @return void
     */
    public function testRunningRoadRunnerServerReloads(): void
    {
        $inspector = new FakeServerProcessInspector();
        $inspector->running = true;

        $output = new StubConsoleOutput();

        $code = (new ReloadCommand(
            new FakeServerProcessInspector(),
            $inspector,
            new FakeServerProcessInspector(),
        ))->run(['--server', 'roadrunner'], new ConsoleIo($output, $output));

        $this->assertSame(ReloadCommand::CODE_SUCCESS, $code);
        $this->assertSame(1, $inspector->reloads);
        $this->assertStringContainsString('Reloading workers...', $output->output());
    }

    /**
     * A stopped Swoole server cannot reload.
     *
     * @return void
     */
    public function testStoppedSwooleServerFails(): void
    {
        $inspector = new FakeServerProcessInspector();

        $output = new StubConsoleOutput();

        $code = (new ReloadCommand(
            $inspector,
            new FakeServerProcessInspector(),
            new FakeServerProcessInspector(),
        ))->run(['--server', 'swoole'], new ConsoleIo($output, $output));

        $this->assertSame(ReloadCommand::CODE_ERROR, $code);
        $this->assertSame(0, $inspector->reloads);
        $this->assertStringContainsString('Stimulus server is not running.', $output->output());
    }

    /**
     * A running FrankenPHP server reloads its workers.
     *
     * @return void
     */
    public function testRunningFrankenPhpServerReloads(): void
    {
        $inspector = new FakeServerProcessInspector();
        $inspector->running = true;

        $output = new StubConsoleOutput();

        $code = (new ReloadCommand(
            new FakeServerProcessInspector(),
            new FakeServerProcessInspector(),
            $inspector,
        ))->run(['--server', 'frankenphp'], new ConsoleIo($output, $output));

        $this->assertSame(ReloadCommand::CODE_SUCCESS, $code);
        $this->assertSame(1, $inspector->reloads);
    }

    /**
     * An invalid server choice fails the reload.
     *
     * @return void
     */
    public function testInvalidServerFails(): void
    {
        $output = new StubConsoleOutput();

        $code = (new ReloadCommand(
            new FakeServerProcessInspector(),
            new FakeServerProcessInspector(),
            new FakeServerProcessInspector(),
        ))->run(['--server', 'bogus'], new ConsoleIo($output, $output));

        $this->assertSame(ReloadCommand::CODE_ERROR, $code);
        $this->assertStringContainsString('Invalid server: bogus.', $output->output());
    }
}
