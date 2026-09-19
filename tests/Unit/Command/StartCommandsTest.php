<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Command;

use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\TestSuite\StubConsoleOutput;
use Cake\Core\Configure;
use Crustum\Stimulus\Bridge\RoadRunner\ServerStateFile as RoadRunnerServerStateFile;
use Crustum\Stimulus\Bridge\Swoole\ServerStateFile as SwooleServerStateFile;
use Crustum\Stimulus\Bridge\Swoole\SwooleExtension;
use Crustum\Stimulus\Command\StartCommand;
use Crustum\Stimulus\Command\StartFrankenPhpCommand;
use Crustum\Stimulus\Command\StartRoadRunnerCommand;
use Crustum\Stimulus\Command\StartSwooleCommand;
use Crustum\Stimulus\Test\Support\SpawnsServersTrait;
use Crustum\Stimulus\Test\TestCase;
use InvalidArgumentException;
use OpenSwoole\Http\Server as OpenSwooleHttpServer;
use Swoole\Http\Server as SwooleHttpServer;
use TestApp\Application;

/**
 * Unit tests for the starter command helpers.
 *
 * Covers the argument and path resolution of the hidden starters without
 * spawning servers, plus live server spawns proving the runtime entries
 * end to end. Each live spawn runs behind its runtime guard — the Swoole
 * entry on the Swoole runtime, the OpenSwoole entry on the OpenSwoole
 * runtime, the RoadRunner spawn on the `rr` binary, the FrankenPHP spawn on
 * its binary — and skips cleanly where the runtime is absent.
 */
class StartCommandsTest extends TestCase
{
    use SpawnsServersTrait;

    /**
     * Previous Stimulus configuration restored after each test.
     *
     * @var mixed
     */
    protected mixed $stimulusConfig = null;

    /**
     * Temporary paths removed after each test.
     *
     * @var array<int, string>
     */
    protected array $temporaryPaths = [];

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
     * Removes temporary paths and restores configuration.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        foreach ($this->temporaryPaths as $path) {
            if (! is_dir($path) && file_exists($path)) {
                unlink($path);
            }
        }

        foreach ($this->temporaryPaths as $path) {
            if (! is_dir($path)) {
                continue;
            }

            $files = glob($path . DIRECTORY_SEPARATOR . '*');

            foreach (is_array($files) ? $files : [] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }

            rmdir($path);
        }

        $this->temporaryPaths = [];

        if ($this->stimulusConfig === null) {
            Configure::delete('Stimulus');
        } else {
            Configure::write('Stimulus', $this->stimulusConfig);
        }

        parent::tearDown();
    }

    /**
     * The FrankenPHP admin port defaults to the configured option.
     *
     * @return void
     */
    public function testFrankenPhpAdminPortDefaultsToOption(): void
    {
        $command = $this->frankenPhpCommand();

        $command->run(['--port', '8000'], $this->io());

        $this->assertSame(2019, $command->adminPortPublic());
    }

    /**
     * The FrankenPHP admin port honors the explicit option.
     *
     * @return void
     */
    public function testFrankenPhpAdminPortHonorsExplicitOption(): void
    {
        $command = $this->frankenPhpCommand();

        $command->run(['--port', '9000', '--admin-port', '2021'], $this->io());

        $this->assertSame(2021, $command->adminPortPublic());
    }

    /**
     * Automatic workers map to zero for FrankenPHP.
     *
     * @return void
     */
    public function testFrankenPhpAutomaticWorkersMapToZero(): void
    {
        $command = $this->frankenPhpCommand();

        $command->run(['--workers', 'auto'], $this->io());

        $this->assertSame(0, $command->workerCountPublic());
    }

    /**
     * Explicit workers pass through for FrankenPHP.
     *
     * @return void
     */
    public function testFrankenPhpExplicitWorkersPassThrough(): void
    {
        $command = $this->frankenPhpCommand();

        $command->run(['--workers', '4'], $this->io());

        $this->assertSame('4', $command->workerCountPublic());
    }

    /**
     * The watcher snippet maps configured paths below the base path.
     *
     * @return void
     */
    public function testFrankenPhpWatchConfigMapsPaths(): void
    {
        Configure::write('Stimulus.basePath', sys_get_temp_dir());
        Configure::write('Stimulus.watch', ['src', 'config']);

        $command = $this->frankenPhpCommand();

        $command->run(['--watch'], $this->io());

        $base = sys_get_temp_dir();

        $this->assertSame(
            "\t\twatch {$base}" . DIRECTORY_SEPARATOR . 'src'
            . "\n\t\twatch {$base}" . DIRECTORY_SEPARATOR . 'config',
            $command->buildWatchConfigPublic(),
        );
    }

    /**
     * The watcher snippet stays empty without the watch flag.
     *
     * @return void
     */
    public function testFrankenPhpWatchConfigStaysEmptyWithoutFlag(): void
    {
        $command = $this->frankenPhpCommand();

        $command->run([], $this->io());

        $this->assertSame('', $command->buildWatchConfigPublic());
    }

    /**
     * The Mercure snippet renders flags and keyed values.
     *
     * @return void
     */
    public function testFrankenPhpMercureConfigRenders(): void
    {
        Configure::write('Stimulus.mercure', [
            'publisher' => 'secret',
            'anonymous' => true,
            'disabled' => false,
        ]);

        $command = $this->frankenPhpCommand();

        $command->run([], $this->io());

        $this->assertSame(
            "mercure {\n\t\t\tpublisher secret\n\t\t\tanonymous\n\t\t}",
            $command->buildMercureConfigPublic(),
        );
    }

    /**
     * The Caddyfile defaults to the shipped resource.
     *
     * @return void
     */
    public function testFrankenPhpConfigPathDefaultsToShippedResource(): void
    {
        $command = $this->frankenPhpCommand();

        $command->run([], $this->io());

        $path = $command->configPathPublic();

        $this->assertSame('Caddyfile', basename($path));
        $this->assertFileExists($path);
        $this->assertStringContainsString('frankenphp', (string)file_get_contents($path));
    }

    /**
     * A missing Caddyfile option fails resolution.
     *
     * @return void
     */
    public function testFrankenPhpConfigPathFailsForMissingFile(): void
    {
        $command = $this->frankenPhpCommand();

        $command->run(['--caddyfile', sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'missing-caddyfile'], $this->io());

        $this->expectException(InvalidArgumentException::class);

        $command->configPathPublic();
    }

    /**
     * The worker stub publishes into the public path.
     *
     * @return void
     */
    public function testFrankenPhpWorkerIsPublished(): void
    {
        $publicPath = $this->temporaryDirectory('stimulus-frankenphp-public');

        Configure::write('Stimulus.publicPath', $publicPath);

        $command = $this->frankenPhpCommand();

        $command->ensureFrankenPhpWorkerIsInstalled();

        $target = $publicPath . DIRECTORY_SEPARATOR . 'frankenphp-worker.php';

        $this->assertFileExists($target);
        $this->assertStringContainsString('APP_BASE_PATH', (string)file_get_contents($target));
    }

    /**
     * The RPC port defaults below the HTTP port.
     *
     * @return void
     */
    public function testRoadRunnerRpcPortDefaultsBelowHttpPort(): void
    {
        $command = $this->roadRunnerCommand();

        $command->run([], $this->io());

        $this->assertSame(6001, $command->rpcPortPublic());
    }

    /**
     * The RPC port honors the explicit option.
     *
     * @return void
     */
    public function testRoadRunnerRpcPortHonorsExplicitOption(): void
    {
        $command = $this->roadRunnerCommand();

        $command->run(['--rpc-port', '6003'], $this->io());

        $this->assertSame(6003, $command->rpcPortPublic());
    }

    /**
     * Elapsed durations convert into milliseconds.
     *
     * @return void
     */
    public function testRoadRunnerElapsedTimeConverts(): void
    {
        $command = $this->roadRunnerCommand();

        $this->assertSame(10.0, $command->calculateElapsedTimePublic('10ms'));
        $this->assertSame(1.5, $command->calculateElapsedTimePublic('1500µs'));
        $this->assertSame(5.0, $command->calculateElapsedTimePublic('5'));
        $this->assertSame(1500.0, $command->calculateElapsedTimePublic('1.5'));
    }

    /**
     * Absolute paths cover Unix and Windows layouts.
     *
     * @return void
     */
    public function testRoadRunnerAbsolutePathDetection(): void
    {
        $command = $this->roadRunnerCommand();

        $this->assertTrue($command->isAbsolutePathPublic('/var/www/worker'));
        $this->assertTrue($command->isAbsolutePathPublic('C:\\www\\worker'));
        $this->assertTrue($command->isAbsolutePathPublic('C:/www/worker'));
        $this->assertFalse($command->isAbsolutePathPublic('vendor/bin/worker'));
    }

    /**
     * The default RoadRunner configuration path is touched.
     *
     * @return void
     */
    public function testRoadRunnerConfigPathTouchesDefault(): void
    {
        $basePath = $this->temporaryDirectory('stimulus-roadrunner-base');

        Configure::write('Stimulus.basePath', $basePath);

        $command = $this->roadRunnerCommand();

        $command->run([], $this->io());

        $path = $command->configPathPublic();

        $this->assertSame($basePath . DIRECTORY_SEPARATOR . '.rr.yaml', $path);
        $this->assertFileExists($path);

        $this->temporaryPaths[] = $path;
    }

    /**
     * A missing RoadRunner configuration option fails resolution.
     *
     * @return void
     */
    public function testRoadRunnerConfigPathFailsForMissingFile(): void
    {
        $command = $this->roadRunnerCommand();

        $command->run(['--rr-config', sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'missing-rr.yaml'], $this->io());

        $this->expectException(InvalidArgumentException::class);

        $command->configPathPublic();
    }

    /**
     * The RoadRunner state file carries the resolved server values.
     *
     * @return void
     */
    public function testRoadRunnerStateFileCarriesServerValues(): void
    {
        $command = $this->roadRunnerCommand();

        $command->run(['--port', '8000', '--workers', 'auto', '--max-requests', '500'], $this->io());

        $stateFile = new RoadRunnerServerStateFile($path = $this->temporaryFile('stimulus-rr-state'));

        $command->writeServerStateFilePublic($stateFile);

        $state = $stateFile->read()['state'];

        $this->assertSame('127.0.0.1', $state['host']);
        $this->assertSame('8000', $state['port']);
        $this->assertSame(6001, $state['rpcPort']);
        $this->assertSame(0, $state['workers']);
        $this->assertSame('500', $state['maxRequests']);
    }

    /**
     * Automatic Swoole workers use the CPU count.
     *
     * @return void
     */
    public function testSwooleAutomaticWorkersUseCpuCount(): void
    {
        $command = $this->swooleCommand();

        $command->run(['--workers', 'auto', '--task-workers', '2'], $this->io());

        $this->assertGreaterThanOrEqual(1, $command->workerCountPublic());
        $this->assertSame(2, $command->taskWorkerCountPublic());
    }

    /**
     * The Swoole state file carries the default server options.
     *
     * @return void
     */
    public function testSwooleStateFileCarriesServerOptions(): void
    {
        $command = $this->swooleCommand();

        $command->run(['--port', '8000', '--workers', 'auto', '--max-requests', '500'], $this->io());

        $stateFile = new SwooleServerStateFile($path = $this->temporaryFile('stimulus-swoole-state'));

        $command->writeServerStateFilePublic($stateFile, new SwooleExtension());

        $state = $stateFile->read()['state'];

        $this->assertSame('127.0.0.1', $state['host']);
        $this->assertSame('8000', $state['port']);
        $this->assertSame('App\\Application', $state['appClass']);
        $this->assertArrayHasKey('defaultServerOptions', $state);
        $this->assertArrayHasKey('worker_num', $state['defaultServerOptions']);
    }

    /**
     * The worker application class defaults to the conventional class.
     *
     * @return void
     */
    public function testApplicationClassDefaultsToConventionalClass(): void
    {
        $this->assertSame('App\\Application', $this->swooleCommand()->applicationClassPublic());
    }

    /**
     * The worker application class honors the configured value.
     *
     * @return void
     */
    public function testApplicationClassHonorsConfiguredValue(): void
    {
        Configure::write('Stimulus.appClass', Application::class);

        $this->assertSame(Application::class, $this->swooleCommand()->applicationClassPublic());
    }

    /**
     * The Swoole state file carries the configured application class.
     *
     * @return void
     */
    public function testSwooleStateFileCarriesConfiguredApplicationClass(): void
    {
        Configure::write('Stimulus.appClass', Application::class);

        $command = $this->swooleCommand();

        $command->run(['--port', '8000', '--workers', 'auto', '--max-requests', '500'], $this->io());

        $stateFile = new SwooleServerStateFile($path = $this->temporaryFile('stimulus-swoole-app-class'));

        $command->writeServerStateFilePublic($stateFile, new SwooleExtension());

        $this->assertSame(Application::class, $stateFile->read()['state']['appClass']);
    }

    /**
     * The dispatcher forwards only set options.
     *
     * @return void
     */
    public function testDispatcherForwardsSetOptions(): void
    {
        $command = new class extends StartCommand {
            /**
             * Executes the command.
             *
             * @param \Cake\Console\Arguments $args The command arguments.
             * @param \Cake\Console\ConsoleIo $io The console io.
             * @return int The exit code or null for success.
             */
            public function execute(Arguments $args, ConsoleIo $io): int
            {
                return self::CODE_SUCCESS;
            }

            /**
             * Exposes the argument forwarding.
             *
             * @param array<string, mixed> $options Option names mapped to their values.
             * @return array<int, string> List-style argument vector.
             */
            public function forwardArgumentsPublic(array $options): array
            {
                return $this->forwardArguments($options);
            }
        };

        $this->assertSame(
            ['--host', '127.0.0.1', '--https'],
            $command->forwardArgumentsPublic([
                'host' => '127.0.0.1',
                'port' => null,
                'watch' => false,
                'https' => true,
            ]),
        );
    }

    /**
     * A spawned FrankenPHP server answers worker requests.
     *
     * Spawns the `frankenphp run` process exactly like the starter command
     * with the shipped Caddyfile and a published worker file, then proves
     * two sequential probe operations each answer fresh from the sandbox.
     * The published worker requires the plugin entry by absolute path
     * because the development layout has no installed vendor copy yet.
     *
     * @return void
     */
    public function testFrankenPhpSpawnRunsServer(): void
    {
        $binary = $this->frankenPhpCommand()->findFrankenPhpBinaryPublic();

        if (!is_string($binary) || !$this->serverBinaryMeetsProbe($binary, 'build-info', 'github.com/dunglas/frankenphp')) {
            $this->markTestSkipped('The FrankenPHP binary is not installed on this host.');
        }

        $port = $this->freeLiveServerPort();
        $adminPort = $this->freeLiveServerPort();
        $directory = $this->makeLiveServerDirectory('stimulus-live-frankenphp-');
        $pluginRoot = $this->pluginRootDirectory();
        $testApp = $pluginRoot . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'TestApp';

        $publicPath = $directory . DIRECTORY_SEPARATOR . 'public';

        mkdir($publicPath, 0777, true);

        file_put_contents(
            $publicPath . DIRECTORY_SEPARATOR . 'frankenphp-worker.php',
            "<?php\ndeclare(strict_types=1);\n\n\$_SERVER['APP_BASE_PATH'] = "
                . var_export($testApp, true)
                . ";\nrequire "
                . var_export($pluginRoot . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'frankenphp-worker.php', true)
                . ";\n",
        );

        $process = $this->startLiveServerProcess(
            [
                $binary,
                'run',
                '-c',
                $pluginRoot . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'server' . DIRECTORY_SEPARATOR . 'Caddyfile',
            ],
            $pluginRoot,
            [
                'APP_BASE_PATH' => $testApp,
                'APP_ENV' => 'testing',
                'APP_PUBLIC_PATH' => $publicPath,
                'STIMULUS' => '1',
                'STIMULUS_APP_CLASS' => Application::class,
                'COMPOSER_VENDOR_DIR' => $pluginRoot . DIRECTORY_SEPARATOR . 'vendor',
                'MAX_REQUESTS' => '100',
                'CADDY_GLOBAL_OPTIONS' => '',
                'CADDY_SERVER_ADMIN_PORT' => (string)$adminPort,
                'CADDY_SERVER_ADMIN_HOST' => '127.0.0.1',
                'CADDY_SERVER_LOG_LEVEL' => 'WARN',
                'CADDY_SERVER_LOGGER' => 'json',
                'CADDY_SERVER_SERVER_NAME' => "http://:{$port}",
                'CADDY_SERVER_WORKER_COUNT' => '1',
                'CADDY_SERVER_WORKER_DIRECTIVE' => 'num 1',
                'CADDY_SERVER_EXTRA_DIRECTIVES' => '',
                'CADDY_SERVER_WATCH_DIRECTIVES' => '',
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
     * A spawned Swoole server answers worker requests.
     *
     * Boots the dual-runtime entry on the Swoole runtime and proves two
     * sequential probe operations each answer fresh from the sandbox.
     *
     * @return void
     */
    public function testSwooleSpawnRunsServer(): void
    {
        if (!extension_loaded('swoole') || !class_exists(SwooleHttpServer::class)) {
            $this->markTestSkipped('The Swoole runtime is not installed on this host.');
        }

        $this->assertLiveServerServesProbes();
    }

    /**
     * A spawned OpenSwoole server answers worker requests.
     *
     * Boots the same dual-runtime entry on the OpenSwoole runtime and proves
     * two sequential probe operations each answer fresh from the sandbox.
     *
     * @return void
     */
    public function testOpenSwooleSpawnRunsServer(): void
    {
        if (!extension_loaded('openswoole') || !class_exists(OpenSwooleHttpServer::class)) {
            $this->markTestSkipped('The OpenSwoole runtime is not installed on this host.');
        }

        $this->assertLiveServerServesProbes();
    }

    /**
     * Boots the server entry and proves fresh probe answers.
     *
     * Writes a live state file for the test application, spawns the entry,
     * waits for its port, and asserts two sequential probe bodies each carry
     * a first invocation count. Every spawned process and directory cleans
     * up in the `finally` block.
     *
     * @return void
     */
    protected function assertLiveServerServesProbes(): void
    {
        $port = $this->freeLiveServerPort();
        $directory = $this->makeLiveServerDirectory('stimulus-live-swoole-');
        $pluginRoot = $this->pluginRootDirectory();
        $testApp = $pluginRoot . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'TestApp';

        $statePath = $directory . DIRECTORY_SEPARATOR . 'stimulus.json';

        file_put_contents($statePath, json_encode([
            'state' => [
                'appName' => 'StimulusLiveTest',
                'host' => '127.0.0.1',
                'port' => $port,
                'appClass' => Application::class,
                'publicPath' => $testApp,
                'defaultServerOptions' => [
                    'worker_num' => 1,
                    'daemonize' => false,
                    'log_file' => $directory . DIRECTORY_SEPARATOR . 'server.log',
                    'max_request' => 0,
                ],
                'maxRequests' => 0,
                'stimulusConfig' => ['tick' => false],
            ],
        ]));

        $process = $this->startLiveServerProcess(
            [
                PHP_BINARY,
                $pluginRoot . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'stimulus-swoole-server',
                $statePath,
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
                $this->waitForLiveServerPort('127.0.0.1', $port, 25),
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
     * The start dispatcher forwards options to the Swoole starter.
     *
     * Runs the dispatcher against a recording starter instead of a real
     * server, proving the server selection and option forwarding without
     * spawning anything.
     *
     * @return void
     */
    public function testDispatcherForwardsToSwooleStarter(): void
    {
        $starter = new class extends StartSwooleCommand {
            /**
             * Port option received from the dispatcher.
             *
             * @var string|null
             */
            public ?string $forwardedPort = null;

            /**
             * Records the forwarded options instead of spawning the server.
             *
             * @param \Cake\Console\Arguments $args The arguments forwarded by the dispatcher.
             * @param \Cake\Console\ConsoleIo $io The console io.
             * @return int The exit code.
             */
            public function execute(Arguments $args, ConsoleIo $io): int
            {
                $port = $args->getOption('port');

                $this->forwardedPort = is_string($port) ? $port : null;

                return self::CODE_SUCCESS;
            }
        };

        $dispatcher = new class ($starter) extends StartCommand {
            /**
             * Creates a dispatcher with a recording starter.
             *
             * @param \Crustum\Stimulus\Command\StartSwooleCommand $starter Starter receiving the forwarded options.
             */
            public function __construct(private StartSwooleCommand $starter)
            {
                parent::__construct();
            }

            /**
             * Returns the recording starter instead of spawning a server.
             *
             * @return \Crustum\Stimulus\Command\StartSwooleCommand Starter receiving the forwarded options.
             */
            protected function swooleStarter(): StartSwooleCommand
            {
                return $this->starter;
            }
        };

        $exitCode = $dispatcher->run(['--server', 'swoole', '--port', '8123'], $this->io());

        $this->assertSame(StartSwooleCommand::CODE_SUCCESS, $exitCode);
        $this->assertSame('8123', $starter->forwardedPort);
    }

    /**
     * Builds stub console io for argument initialization.
     *
     * @return \Cake\Console\ConsoleIo Console io with stub streams.
     */
    protected function io(): ConsoleIo
    {
        return new ConsoleIo(new StubConsoleOutput(), new StubConsoleOutput());
    }

    /**
     * Builds a FrankenPHP starter exposing its helpers.
     *
     * @return \Crustum\Stimulus\Command\StartFrankenPhpCommand Starter with public helpers.
     */
    protected function frankenPhpCommand(): StartFrankenPhpCommand
    {
        return new class extends StartFrankenPhpCommand {
            /**
             * Executes the command without spawning the server.
             *
             * @param \Cake\Console\Arguments $args The command arguments.
             * @param \Cake\Console\ConsoleIo $io The console io.
             * @return int The exit code or null for success.
             */
            public function execute(Arguments $args, ConsoleIo $io): int
            {
                return self::CODE_SUCCESS;
            }

            /**
             * Exposes the admin port resolution.
             *
             * @return int Admin port for the FrankenPHP admin endpoint.
             */
            public function adminPortPublic(): int
            {
                return $this->adminPort();
            }

            /**
             * Exposes the worker count resolution.
             *
             * @return string|int Worker count option.
             */
            public function workerCountPublic(): string|int
            {
                return $this->workerCount();
            }

            /**
             * Exposes the watcher snippet builder.
             *
             * @return string Watcher Caddyfile snippet.
             */
            public function buildWatchConfigPublic(): string
            {
                return $this->buildWatchConfig();
            }

            /**
             * Exposes the Mercure snippet builder.
             *
             * @return string Mercure Caddyfile snippet.
             */
            public function buildMercureConfigPublic(): string
            {
                return $this->buildMercureConfig();
            }

            /**
             * Exposes the configuration path resolution.
             *
             * @return string Absolute path to the configuration file.
             */
            public function configPathPublic(): string
            {
                return $this->configPath();
            }

            /**
             * Exposes the FrankenPHP binary lookup.
             *
             * @return string|null Absolute path to the binary, or null when it cannot be found.
             */
            public function findFrankenPhpBinaryPublic(): ?string
            {
                return $this->findFrankenPhpBinary();
            }
        };
    }

    /**
     * Builds a RoadRunner starter exposing its helpers.
     *
     * @return \Crustum\Stimulus\Command\StartRoadRunnerCommand Starter with public helpers.
     */
    protected function roadRunnerCommand(): StartRoadRunnerCommand
    {
        return new class extends StartRoadRunnerCommand {
            /**
             * Executes the command without spawning the server.
             *
             * @param \Cake\Console\Arguments $args The command arguments.
             * @param \Cake\Console\ConsoleIo $io The console io.
             * @return int The exit code or null for success.
             */
            public function execute(Arguments $args, ConsoleIo $io): int
            {
                return self::CODE_SUCCESS;
            }

            /**
             * Exposes the RPC port resolution.
             *
             * @return int RPC port for the server.
             */
            public function rpcPortPublic(): int
            {
                return $this->rpcPort();
            }

            /**
             * Exposes the elapsed time conversion.
             *
             * @param string $elapsed Elapsed duration reported by RoadRunner.
             * @return float Elapsed milliseconds.
             */
            public function calculateElapsedTimePublic(string $elapsed): float
            {
                return $this->calculateElapsedTime($elapsed);
            }

            /**
             * Exposes the absolute path detection.
             *
             * @param string $path Filesystem path to check.
             * @return bool Whether the path is absolute.
             */
            public function isAbsolutePathPublic(string $path): bool
            {
                return $this->isAbsolutePath($path);
            }

            /**
             * Exposes the configuration path resolution.
             *
             * @return string Absolute path to the configuration file.
             */
            public function configPathPublic(): string
            {
                return $this->configPath();
            }

            /**
             * Exposes the server state file writer.
             *
             * @param \Crustum\Stimulus\Bridge\RoadRunner\ServerStateFile $serverStateFile State file tracking the server.
             * @return void
             */
            public function writeServerStateFilePublic(RoadRunnerServerStateFile $serverStateFile): void
            {
                $this->writeServerStateFile($serverStateFile);
            }
        };
    }

    /**
     * Builds a Swoole starter exposing its helpers.
     *
     * @return \Crustum\Stimulus\Command\StartSwooleCommand Starter with public helpers.
     */
    protected function swooleCommand(): StartSwooleCommand
    {
        return new class extends StartSwooleCommand {
            /**
             * Executes the command without spawning the server.
             *
             * @param \Cake\Console\Arguments $args The command arguments.
             * @param \Cake\Console\ConsoleIo $io The console io.
             * @return int The exit code or null for success.
             */
            public function execute(Arguments $args, ConsoleIo $io): int
            {
                return self::CODE_SUCCESS;
            }

            /**
             * Exposes the worker count resolution.
             *
             * @return int Number of Swoole workers.
             */
            public function workerCountPublic(): int
            {
                return $this->workerCount($this->swooleExtension());
            }

            /**
             * Exposes the task worker count resolution.
             *
             * @return int Number of Swoole task workers.
             */
            public function taskWorkerCountPublic(): int
            {
                return $this->taskWorkerCount($this->swooleExtension());
            }

            /**
             * Exposes the worker application class resolution.
             *
             * @return string Application class name booting the worker.
             */
            public function applicationClassPublic(): string
            {
                return $this->applicationClass();
            }

            /**
             * Exposes the server state file writer.
             *
             * @param \Crustum\Stimulus\Bridge\Swoole\ServerStateFile $serverStateFile State file tracking the server.
             * @param \Crustum\Stimulus\Bridge\Swoole\SwooleExtension $extension Extension helper sizing the workers.
             * @return void
             */
            public function writeServerStateFilePublic(
                SwooleServerStateFile $serverStateFile,
                SwooleExtension $extension,
            ): void {
                $this->writeServerStateFile($serverStateFile, $extension);
            }
        };
    }

    /**
     * Builds a tracked temporary directory.
     *
     * @param string $name Directory name below the system temp directory.
     * @return string Absolute path to the temporary directory.
     */
    protected function temporaryDirectory(string $name): string
    {
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $name . '-' . getmypid();

        if (! is_dir($path)) {
            mkdir($path, 0777, true);
        }

        $this->temporaryPaths[] = $path;

        return $path;
    }

    /**
     * Builds a tracked temporary file path.
     *
     * @param string $name File name below the system temp directory.
     * @return string Absolute temporary file path.
     */
    protected function temporaryFile(string $name): string
    {
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $name . '-' . getmypid() . '.json';

        if (file_exists($path)) {
            unlink($path);
        }

        $this->temporaryPaths[] = $path;

        return $path;
    }
}
