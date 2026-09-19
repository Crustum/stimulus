<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Command;

use Cake\Console\ConsoleIo;
use Cake\Console\TestSuite\StubConsoleOutput;
use Cake\Core\Configure;
use Crustum\Stimulus\Command\StatusCommand;
use Crustum\Stimulus\Test\Support\FakeServerProcessInspector;
use Crustum\Stimulus\Test\TestCase;

/**
 * Feature tests for the status command.
 *
 * Covers the per-driver running probes with fake inspectors so no real
 * server is needed.
 */
class StatusCommandTest extends TestCase
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
     * A running server reports success.
     *
     * @return void
     */
    public function testRunningServerReportsSuccess(): void
    {
        $swoole = new FakeServerProcessInspector();
        $swoole->running = true;

        $output = new StubConsoleOutput();

        $code = (new StatusCommand($swoole, new FakeServerProcessInspector(), new FakeServerProcessInspector()))
            ->run(['--server', 'swoole'], new ConsoleIo($output, $output));

        $this->assertSame(StatusCommand::CODE_SUCCESS, $code);
        $this->assertStringContainsString('Stimulus server is running.', $output->output());
    }

    /**
     * A stopped server reports an error.
     *
     * @return void
     */
    public function testStoppedServerReportsError(): void
    {
        $output = new StubConsoleOutput();

        $code = (new StatusCommand(
            new FakeServerProcessInspector(),
            new FakeServerProcessInspector(),
            new FakeServerProcessInspector(),
        ))->run(['--server', 'frankenphp'], new ConsoleIo($output, $output));

        $this->assertSame(StatusCommand::CODE_ERROR, $code);
        $this->assertStringContainsString('Stimulus server is not running.', $output->output());
    }

    /**
     * The configured server applies when the option is missing.
     *
     * @return void
     */
    public function testConfiguredServerAppliesWithoutOption(): void
    {
        Configure::write('Stimulus.server', 'roadrunner');

        $roadRunner = new FakeServerProcessInspector();
        $roadRunner->running = true;

        $output = new StubConsoleOutput();

        $code = (new StatusCommand(
            new FakeServerProcessInspector(),
            $roadRunner,
            new FakeServerProcessInspector(),
        ))->run([], new ConsoleIo($output, $output));

        $this->assertSame(StatusCommand::CODE_SUCCESS, $code);
        $this->assertSame(0, $roadRunner->reloads);
    }

    /**
     * An invalid server choice fails the status check.
     *
     * @return void
     */
    public function testInvalidServerFails(): void
    {
        $output = new StubConsoleOutput();

        $code = (new StatusCommand(
            new FakeServerProcessInspector(),
            new FakeServerProcessInspector(),
            new FakeServerProcessInspector(),
        ))->run(['--server', 'bogus'], new ConsoleIo($output, $output));

        $this->assertSame(StatusCommand::CODE_ERROR, $code);
        $this->assertStringContainsString('Invalid server: bogus.', $output->output());
    }
}
