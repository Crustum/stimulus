<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Command;

use Cake\Console\ConsoleIo;
use Cake\Console\TestSuite\StubConsoleOutput;
use Cake\Core\Configure;
use Crustum\Stimulus\Command\InstallCommand;
use Crustum\Stimulus\Test\TestCase;

/**
 * Tests for the install command.
 *
 * Covers the testable environment file method, then the install chain with
 * a stubbed configuration publisher so no manifest assets are touched.
 */
class InstallCommandTest extends TestCase
{
    /**
     * Environment file used by the tests.
     *
     * @var string
     */
    protected string $environmentFile = '';

    /**
     * Previous Stimulus configuration restored after each test.
     *
     * @var mixed
     */
    protected mixed $stimulusConfig = null;

    /**
     * Prepares the temporary environment file for each test.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->environmentFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'stimulus_install_test.env';

        if (file_exists($this->environmentFile)) {
            unlink($this->environmentFile);
        }

        $this->stimulusConfig = Configure::read('Stimulus');
    }

    /**
     * Removes the temporary environment file after each test.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        if (file_exists($this->environmentFile)) {
            unlink($this->environmentFile);
        }

        if ($this->stimulusConfig === null) {
            Configure::delete('Stimulus');
        } else {
            Configure::write('Stimulus', $this->stimulusConfig);
        }

        parent::tearDown();
    }

    /**
     * The server variable is updated when already present.
     *
     * @return void
     */
    public function testStimulusServerVariableIsUpdatedWhenAlreadyPresent(): void
    {
        file_put_contents(
            $this->environmentFile,
            "APP_NAME=Stimulus\nSTIMULUS_SERVER=swoole\nAPP_ENV=local\n",
        );

        (new InstallCommand())->updateEnvironmentFile('roadrunner', $this->environmentFile);

        $contents = (string)file_get_contents($this->environmentFile);

        $this->assertStringContainsString('STIMULUS_SERVER=roadrunner', $contents);
        $this->assertStringNotContainsString('STIMULUS_SERVER=swoole', $contents);
        $this->assertStringContainsString('APP_NAME=Stimulus', $contents);
        $this->assertStringContainsString('APP_ENV=local', $contents);
    }

    /**
     * The server variable is appended when missing.
     *
     * @return void
     */
    public function testStimulusServerVariableIsAppendedWhenMissing(): void
    {
        file_put_contents($this->environmentFile, "APP_NAME=Stimulus\n");

        (new InstallCommand())->updateEnvironmentFile('roadrunner', $this->environmentFile);

        $this->assertStringContainsString(
            'STIMULUS_SERVER=roadrunner',
            (string)file_get_contents($this->environmentFile),
        );
    }

    /**
     * A missing environment file leaves the install untouched.
     *
     * Covers the file existence guard.
     *
     * @return void
     */
    public function testMissingEnvironmentFileIsIgnored(): void
    {
        (new InstallCommand())->updateEnvironmentFile('roadrunner', $this->environmentFile);

        $this->assertFileDoesNotExist($this->environmentFile);
    }

    /**
     * An invalid server choice fails the install.
     *
     * @return void
     */
    public function testInvalidServerFails(): void
    {
        $output = new StubConsoleOutput();

        $code = (new InstallCommand())->run(['--server', 'bogus'], new ConsoleIo($output, $output));

        $this->assertSame(InstallCommand::CODE_ERROR, $code);
        $this->assertStringContainsString('Invalid server: bogus.', $output->output());
    }

    /**
     * The install chain records the server without publishing assets.
     *
     * Stubs the manifest delegation and the binary installers so the feature
     * flow is covered without touching the host application.
     *
     * @return void
     */
    public function testInstallRecordsServer(): void
    {
        file_put_contents($this->environmentFile, "APP_NAME=Stimulus\n");

        Configure::write('Stimulus.envFile', $this->environmentFile);

        $output = new StubConsoleOutput();

        $command = new class extends InstallCommand {
            /**
             * Stubs the manifest configuration publishing.
             *
             * @param bool $force Whether existing files are overwritten.
             * @param \Cake\Console\ConsoleIo $io The console io.
             * @return int The exit code or null for success.
             */
            protected function publishConfiguration(bool $force, ConsoleIo $io): int
            {
                return self::CODE_SUCCESS;
            }

            /**
             * Stubs the RoadRunner package installation.
             *
             * @return bool Whether the package is available.
             */
            public function installRoadRunnerServer(): bool
            {
                return true;
            }
        };

        $code = $command->run(['--server', 'roadrunner'], new ConsoleIo($output, $output));

        $this->assertSame(InstallCommand::CODE_SUCCESS, $code);
        $this->assertStringContainsString(
            'STIMULUS_SERVER=roadrunner',
            (string)file_get_contents($this->environmentFile),
        );
        $this->assertStringContainsString('Stimulus installed successfully.', $output->output());
    }
}
