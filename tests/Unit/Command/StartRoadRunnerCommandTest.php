<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Command;

use Cake\Core\Configure;
use Crustum\Stimulus\Command\StartRoadRunnerCommand;
use Crustum\Stimulus\Test\Support\SpawnsServersTrait;
use Crustum\Stimulus\Test\TestCase;
use TestApp\Application;

/**
 * Tests for the RoadRunner worker command resolution.
 *
 * The base path helper becomes the `Stimulus.basePath` configuration value,
 * and the RoadRunner command option becomes the
 * `Stimulus.roadrunner.command` value. The live spawn proves the `rr serve`
 * process end to end behind its binary guard and skips cleanly where the
 * binary is absent.
 */
class StartRoadRunnerCommandTest extends TestCase
{
    use SpawnsServersTrait;

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
     * The default worker command resolves below the base path.
     *
     * @return void
     */
    public function testDefaultWorkerCommandIsResolvedRelativeToTheBasePath(): void
    {
        Configure::write('Stimulus.basePath', sys_get_temp_dir());

        $command = $this->command();

        $this->assertSame(
            sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'vendor/bin/roadrunner-worker',
            $command->workerCommand(),
        );
    }

    /**
     * A configured relative worker command resolves below the base path.
     *
     * @return void
     */
    public function testConfiguredRelativeWorkerCommandIsResolvedRelativeToTheBasePath(): void
    {
        Configure::write('Stimulus.basePath', sys_get_temp_dir());
        Configure::write('Stimulus.roadrunner.command', 'vendor/bin/custom-worker');

        $command = $this->command();

        $this->assertSame(
            sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'vendor/bin/custom-worker',
            $command->workerCommand(),
        );
    }

    /**
     * A configured absolute worker command keeps its path.
     *
     * @return void
     */
    public function testConfiguredAbsoluteWorkerCommandIsNotPrefixedWithTheBasePath(): void
    {
        Configure::write('Stimulus.basePath', sys_get_temp_dir());
        Configure::write(
            'Stimulus.roadrunner.command',
            '/var/www/current/vendor/bin/roadrunner-worker',
        );

        $command = $this->command();

        $this->assertSame(
            '/var/www/current/vendor/bin/roadrunner-worker',
            $command->workerCommand(),
        );
    }

    /**
     * A spawned RoadRunner server answers worker requests.
     *
     * Spawns the `rr serve` process with the same option overrides the
     * starter command builds over an empty configuration file, then proves
     * two sequential probe operations each answer fresh from the sandbox.
     *
     * @return void
     */
    public function testRoadRunnerSpawnRunsServer(): void
    {
        $binary = $this->command()->findRoadRunnerBinaryPublic();

        if (!is_string($binary) || !$this->serverBinaryMeetsProbe($binary, '--version', ' version ')) {
            $this->markTestSkipped('The RoadRunner binary is not installed on this host.');
        }

        $port = $this->freeLiveServerPort();
        $rpcPort = $this->freeLiveServerPort();
        $directory = $this->makeLiveServerDirectory('stimulus-live-roadrunner-');
        $pluginRoot = $this->pluginRootDirectory();
        $testApp = $pluginRoot . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'TestApp';

        $configPath = $directory . DIRECTORY_SEPARATOR . '.rr.yaml';

        touch($configPath);

        $process = $this->startLiveServerProcess(
            [
                $binary,
                '-c',
                $configPath,
                '-o',
                'version=3',
                '-o',
                "http.address=127.0.0.1:{$port}",
                '-o',
                'server.command=' . PHP_BINARY . ',' . $pluginRoot . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'roadrunner-worker',
                '-o',
                'http.pool.num_workers=1',
                '-o',
                'http.pool.max_jobs=100',
                '-o',
                "rpc.listen=tcp://127.0.0.1:{$rpcPort}",
                '-o',
                'http.pool.supervisor.exec_ttl=30s',
                '-o',
                'http.static.dir=' . $testApp,
                '-o',
                'http.middleware=static',
                '-o',
                'logs.mode=production',
                '-o',
                'logs.level=warn',
                '-o',
                'logs.output=stdout',
                '-o',
                'logs.encoding=json',
                'serve',
            ],
            $pluginRoot,
            [
                'APP_BASE_PATH' => $testApp,
                'APP_ENV' => 'testing',
                'STIMULUS' => '1',
                'STIMULUS_APP_CLASS' => Application::class,
                'COMPOSER_VENDOR_DIR' => $pluginRoot . DIRECTORY_SEPARATOR . 'vendor',
            ],
        );

        try {
            $this->assertTrue(
                $this->waitForLiveServerPort('127.0.0.1', $port, 30),
                'The live server did not accept connections in time.',
            );

            $first = $this->fetchLiveServerBody("http://127.0.0.1:{$port}/live-probe");
            $second = $this->fetchLiveServerBody("http://127.0.0.1:{$port}/live-probe");

            $this->assertSame(200, $first['status']);
            $this->assertSame('probe:1', $first['body']);
            $this->assertSame(200, $second['status']);
            $this->assertSame('probe:1', $second['body']);
            $this->assertTrue($process->isRunning());
        } finally {
            $this->stopLiveServerProcesses();
            $this->removeLiveServerDirectories();
        }
    }

    /**
     * Builds a starter exposing the worker command resolution.
     *
     * @return \Crustum\Stimulus\Command\StartRoadRunnerCommand Starter with a public worker command.
     */
    protected function command(): StartRoadRunnerCommand
    {
        return new class extends StartRoadRunnerCommand {
            /**
             * Gets the path to the RoadRunner worker command.
             *
             * @return string Absolute path to the RoadRunner worker command.
             */
            public function workerCommand(): string
            {
                return parent::workerCommand();
            }

            /**
             * Exposes the RoadRunner binary lookup.
             *
             * @return string|null Absolute path to the binary, or null when it cannot be found.
             */
            public function findRoadRunnerBinaryPublic(): ?string
            {
                return $this->findRoadRunnerBinary();
            }
        };
    }
}
