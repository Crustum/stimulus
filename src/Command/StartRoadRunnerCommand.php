<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Command;

use Cake\Collection\Collection;
use Cake\Console\CommandFactoryInterface;
use Cake\Console\ConsoleOptionParser;
use Cake\Core\Configure;
use Crustum\Stimulus\Bridge\RoadRunner\ServerStateFile as RoadRunnerServerStateFile;
use Crustum\Stimulus\Command\Trait\InstallsRoadRunnerDependenciesTrait;
use Crustum\Stimulus\Command\Trait\InteractsWithEnvironmentVariablesTrait;
use Crustum\Stimulus\Command\Trait\InteractsWithServersTrait;
use Crustum\Stimulus\Contracts\ServerProcessInspector as ServerProcessInspectorContract;
use InvalidArgumentException;
use Override;
use SignalHandler\Command\Trait\SignalHandlerTrait;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Starts the Stimulus RoadRunner server.
 *
 * The server spawns through the `rr serve` binary, with the worker command,
 * pool, RPC, logging, and static file options mapped onto configuration
 * reads.
 */
class StartRoadRunnerCommand extends StimulusCommand
{
    use SignalHandlerTrait;
    use InstallsRoadRunnerDependenciesTrait;
    use InteractsWithEnvironmentVariablesTrait;
    use InteractsWithServersTrait;

    /**
     * Creates a new RoadRunner starter command.
     *
     * @param \Crustum\Stimulus\Contracts\ServerProcessInspector|null $inspector Inspector for the RoadRunner server, or null for the default.
     * @param \Crustum\Stimulus\Bridge\RoadRunner\ServerStateFile|null $serverStateFile State file for the RoadRunner server, or null for the default.
     * @param \Cake\Console\CommandFactoryInterface|null $factory Command factory instance.
     */
    public function __construct(
        protected ?ServerProcessInspectorContract $inspector = null,
        protected ?RoadRunnerServerStateFile $serverStateFile = null,
        ?CommandFactoryInterface $factory = null,
    ) {
        parent::__construct($factory);
    }

    /**
     * Gets the command name.
     *
     * @return string The command name.
     */
    #[Override]
    public static function defaultName(): string
    {
        return 'stimulus roadrunner';
    }

    /**
     * Gets the command description.
     *
     * @return string The command description.
     */
    #[Override]
    public static function getDescription(): string
    {
        return 'Start the Stimulus RoadRunner server';
    }

    /**
     * Hook method for defining this command's option parser.
     *
     * @param \Cake\Console\ConsoleOptionParser $parser The parser to be defined.
     * @return \Cake\Console\ConsoleOptionParser The built parser.
     */
    #[Override]
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return $parser
            ->addOption('host', ['help' => 'The IP address the server should bind to'])
            ->addOption('port', ['help' => 'The port the server should be available on'])
            ->addOption('rpc-host', ['help' => 'The RPC IP address the server should bind to'])
            ->addOption('rpc-port', ['help' => 'The RPC port the server should be available on'])
            ->addOption('workers', [
                'help' => 'The number of workers that should be available to handle requests',
                'default' => 'auto',
            ])
            ->addOption('max-requests', [
                'help' => 'The number of requests to process before reloading the server',
                'default' => '500',
            ])
            ->addOption('rr-config', ['help' => 'The path to the RoadRunner .rr.yaml file'])
            ->addOption('watch', [
                'help' => 'Automatically reload the server when the application is modified',
                'boolean' => true,
            ])
            ->addOption('poll', [
                'help' => 'Use file system polling while watching in order to watch files over a network',
                'boolean' => true,
            ])
            ->addOption('log-level', ['help' => 'Log messages at or above the specified log level']);
    }

    /**
     * Executes the command.
     *
     * Guards the installation, port, and running state, then spawns
     * `rr serve` with the mapped server options and pumps its output through
     * the server loop.
     *
     * @return int|null The exit code or null for success.
     */
    public function execute(): ?int
    {
        $this->bindGracefulTermination(function (int $signal): void {
            $this->io->warning('Stimulus RoadRunner received termination signal, shutting down gracefully...');

            $this->isRunning = false;
        });

        $inspector = $this->serverInspector();

        if (! $this->isRoadRunnerInstalled()) {
            $this->error('RoadRunner not installed. Please execute the `stimulus.install` command.');

            return self::CODE_ERROR;
        }

        $roadRunnerBinary = $this->ensureRoadRunnerBinaryIsInstalled();

        $this->ensurePortIsAvailable();

        if ($inspector->serverIsRunning()) {
            $this->error('RoadRunner server is already running.');

            return self::CODE_ERROR;
        }

        $this->ensureRoadRunnerBinaryMeetsRequirements($roadRunnerBinary);

        $serverStateFile = $this->serverStateFile();

        $this->writeServerStateFile($serverStateFile);

        $this->forgetEnvironmentVariables();

        $logLevel = $this->serverOption('log-level');

        if (! is_string($logLevel) || $logLevel === '') {
            $logLevel = ($_ENV['APP_ENV'] ?? 'production') === 'local' ? 'debug' : 'warn';
        }

        $server = new Process(
            array_filter([
                $roadRunnerBinary,
                '-c',
                $this->configPath(),
                '-o',
                'version=3',
                '-o',
                'http.address=' . $this->getHost() . ':' . $this->getPort(),
                '-o',
                'server.command=' . (new PhpExecutableFinder())->find() . ',' . $this->workerCommand(),
                '-o',
                'http.pool.num_workers=' . $this->workerCount(),
                '-o',
                'http.pool.max_jobs=' . $this->serverOption('max-requests'),
                '-o',
                'rpc.listen=tcp://' . $this->rpcHost() . ':' . $this->rpcPort(),
                '-o',
                'http.pool.supervisor.exec_ttl=' . $this->maxExecutionTime(),
                '-o',
                'http.static.dir=' . $this->publicPath(),
                '-o',
                'http.middleware=' . $this->stimulusConfig('roadrunner.http_middleware', 'static'),
                '-o',
                'logs.mode=production',
                '-o',
                'logs.level=' . $logLevel,
                '-o',
                'logs.output=stdout',
                '-o',
                'logs.encoding=json',
                'serve',
            ]),
            $this->applicationBasePath(),
            [
                'APP_ENV' => $_ENV['APP_ENV'] ?? 'production',
                'APP_BASE_PATH' => $this->applicationBasePath(),
                'STIMULUS' => '1',
                'STIMULUS_APP_CLASS' => $this->applicationClass(),
            ],
        );

        $server->start();

        $pid = $server->getPid();

        $serverStateFile->writeProcessId($pid ?? 0);

        return $this->runServer($server, $inspector, 'roadrunner');
    }

    /**
     * Writes the RoadRunner server state file.
     *
     * @param \Crustum\Stimulus\Bridge\RoadRunner\ServerStateFile $serverStateFile State file tracking the server.
     * @return void
     */
    protected function writeServerStateFile(RoadRunnerServerStateFile $serverStateFile): void
    {
        $serverStateFile->writeState([
            'appName' => $this->stimulusConfig('app.name', 'Stimulus'),
            'host' => $this->getHost(),
            'port' => $this->getPort(),
            'rpcPort' => $this->rpcPort(),
            'workers' => $this->workerCount(),
            'maxRequests' => $this->serverOption('max-requests'),
            'stimulusConfig' => Configure::read('Stimulus', []),
        ]);
    }

    /**
     * Gets the number of workers that should be started.
     *
     * @return string|int Worker count option, with `auto` mapped to zero workers.
     */
    protected function workerCount(): string|int
    {
        $workers = $this->serverOption('workers') ?? $this->stimulusConfig('workers', 'auto');

        return $workers == 'auto' ? 0 : $workers;
    }

    /**
     * Gets the path to the RoadRunner worker command.
     *
     * @return string Absolute path to the RoadRunner worker command.
     */
    protected function workerCommand(): string
    {
        $command = (string)$this->stimulusConfig('roadrunner.command', 'vendor/bin/roadrunner-worker');

        return $this->isAbsolutePath($command)
            ? $command
            : $this->applicationBasePath() . DIRECTORY_SEPARATOR . $command;
    }

    /**
     * Determines if the given path is absolute.
     *
     * @param string $path Filesystem path to check.
     * @return bool Whether the path is absolute on Unix or Windows.
     */
    protected function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/') || preg_match('/^[a-zA-Z]:[\\\\\/]/', $path) === 1;
    }

    /**
     * Gets the path to the RoadRunner configuration file.
     *
     * Touches the default configuration path when no explicit path is given.
     *
     * @return string Absolute path to the RoadRunner configuration file.
     * @throws \InvalidArgumentException When the given configuration file cannot be located.
     */
    protected function configPath(): string
    {
        $path = $this->serverOption('rr-config');

        if (! is_string($path) || $path === '') {
            $default = $this->applicationBasePath() . DIRECTORY_SEPARATOR . '.rr.yaml';

            touch($default);

            return $default;
        }

        $resolved = realpath($path);

        if ($resolved === false) {
            throw new InvalidArgumentException('Unable to locate specified configuration file.');
        }

        return $resolved;
    }

    /**
     * Gets the maximum time workers may execute a single request.
     *
     * @return string Maximum execution time with the RoadRunner duration suffix.
     */
    protected function maxExecutionTime(): string
    {
        return $this->stimulusConfig('max_execution_time', '30') . 's';
    }

    /**
     * Gets the RPC IP address the server should be available on.
     *
     * @return string RPC host falling back to the HTTP host.
     */
    protected function rpcHost(): string
    {
        $host = $this->serverOption('rpc-host') ?: $this->getHost();

        return (string)$host;
    }

    /**
     * Gets the RPC port the server should be available on.
     *
     * @return int RPC port defaulting below the HTTP port.
     */
    protected function rpcPort(): int
    {
        $port = $this->serverOption('rpc-port');

        if (!in_array($port, [null, false, ''], true)) {
            return (int)$port;
        }

        return (int)$this->getPort() - 1999;
    }

    /**
     * Writes the server process output to the console.
     *
     * Decodes the JSON logs as collection pipelines over the standard and
     * error output lines.
     *
     * @param \Symfony\Component\Process\Process $server Running RoadRunner server process.
     * @return void
     */
    protected function writeServerOutput(Process $server): void
    {
        [$output, $errorOutput] = $this->getServerOutput($server);

        (new Collection(explode("\n", $output)))
            ->filter()
            ->each(function ($output): void {
                $debug = json_decode((string)$output, true);

                if (! is_array($debug)) {
                    $this->info((string)$output);

                    return;
                }

                $stream = json_decode((string)($debug['msg'] ?? ''), true);

                if (is_array($stream)) {
                    $this->handleStream($stream);

                    return;
                }

                if (($debug['logger'] ?? null) == 'server') {
                    $this->raw((string)$debug['msg']);

                    return;
                }

                if (
                    ($debug['level'] ?? null) == 'info'
                    && isset($debug['remote_address'], $debug['msg'])
                    && $debug['msg'] == 'http log'
                ) {
                    [
                        'elapsed' => $elapsed,
                        'method' => $method,
                        'status' => $statusCode,
                        'URI' => $url,
                    ] = $debug;

                    $this->requestInfo([
                        'method' => $method,
                        'url' => $url,
                        'statusCode' => $statusCode,
                        'duration' => $this->calculateElapsedTime((string)$elapsed),
                    ]);
                }
            });

        (new Collection(explode("\n", $errorOutput)))
            ->filter()
            ->each(function ($output): void {
                $line = (string)$output;

                $knownLevel = (new Collection(['DEBUG', 'INFO', 'WARN']))
                    ->some(fn(string $level): bool => str_contains($line, $level));

                if (! $knownLevel) {
                    $this->error($line);
                }
            });
    }

    /**
     * Calculates the elapsed time for a request.
     *
     * Converts the RoadRunner duration suffixes into milliseconds. The
     * integer branch casts because strict types forbid returning the numeric
     * string for the float result.
     *
     * @param string $elapsed Elapsed duration reported by RoadRunner.
     * @return float Elapsed milliseconds.
     */
    protected function calculateElapsedTime(string $elapsed): float
    {
        if (str_ends_with($elapsed, 'ms')) {
            return (float)substr($elapsed, 0, -2);
        }

        if (str_ends_with($elapsed, 'µs')) {
            return (float)mb_substr($elapsed, 0, -2) * 0.001;
        }

        if (filter_var($elapsed, FILTER_VALIDATE_INT) !== false) {
            return (float)$elapsed;
        }

        return (float)$elapsed * 1000;
    }

    /**
     * Stops the server.
     *
     * @return void
     */
    protected function stopServer(): void
    {
        $this->executeSilently(new StopCommand(), ['--server', 'roadrunner']);
    }

    /**
     * Gets the RoadRunner server process inspector.
     *
     * @return \Crustum\Stimulus\Contracts\ServerProcessInspector Inspector for the RoadRunner server.
     */
    protected function serverInspector(): ServerProcessInspectorContract
    {
        if (!$this->inspector instanceof ServerProcessInspectorContract) {
            $this->inspector = $this->defaultRoadRunnerInspector();
        }

        return $this->inspector;
    }

    /**
     * Gets the RoadRunner server state file.
     *
     * @return \Crustum\Stimulus\Bridge\RoadRunner\ServerStateFile State file for the RoadRunner server.
     */
    protected function serverStateFile(): RoadRunnerServerStateFile
    {
        if (!$this->serverStateFile instanceof RoadRunnerServerStateFile) {
            $this->serverStateFile = $this->defaultRoadRunnerStateFile();
        }

        return $this->serverStateFile;
    }
}
