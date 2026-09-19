<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Command;

use Cake\Collection\Collection;
use Cake\Console\Arguments;
use Cake\Console\CommandFactoryInterface;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Core\Configure;
use Crustum\Stimulus\Bridge\Swoole\ServerStateFile as SwooleServerStateFile;
use Crustum\Stimulus\Bridge\Swoole\SwooleExtension;
use Crustum\Stimulus\Command\Trait\InteractsWithEnvironmentVariablesTrait;
use Crustum\Stimulus\Command\Trait\InteractsWithServersTrait;
use Crustum\Stimulus\Contracts\ServerProcessInspector as ServerProcessInspectorContract;
use Override;
use SignalHandler\Command\Trait\SignalHandlerTrait;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Starts the Stimulus Swoole server.
 *
 * The extension, port, running, and TLS guards run before the state file
 * carries the server options, and the worker process spawns through the
 * configured Swoole command with the state path. The Swoole runtime entries
 * the process boots land in P9, so this phase ports the command while
 * execution proof waits for the runtime.
 */
class StartSwooleCommand extends StimulusCommand
{
    use SignalHandlerTrait;
    use InteractsWithEnvironmentVariablesTrait;
    use InteractsWithServersTrait;

    /**
     * Creates a new Swoole starter command.
     *
     * @param \Crustum\Stimulus\Contracts\ServerProcessInspector|null $inspector Inspector for the Swoole server, or null for the default.
     * @param \Crustum\Stimulus\Bridge\Swoole\ServerStateFile|null $serverStateFile State file for the Swoole server, or null for the default.
     * @param \Crustum\Stimulus\Bridge\Swoole\SwooleExtension|null $extension Extension helper, or null for the default.
     * @param \Cake\Console\CommandFactoryInterface|null $factory Command factory instance.
     */
    public function __construct(
        protected ?ServerProcessInspectorContract $inspector = null,
        protected ?SwooleServerStateFile $serverStateFile = null,
        protected ?SwooleExtension $extension = null,
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
        return 'stimulus swoole';
    }

    /**
     * Gets the command description.
     *
     * @return string The command description.
     */
    #[Override]
    public static function getDescription(): string
    {
        return 'Start the Stimulus Swoole server';
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
            ->addOption('workers', [
                'help' => 'The number of workers that should be available to handle requests',
                'default' => 'auto',
            ])
            ->addOption('task-workers', [
                'help' => 'The number of task workers that should be available to handle tasks',
                'default' => 'auto',
            ])
            ->addOption('max-requests', [
                'help' => 'The number of requests to process before reloading the server',
                'default' => '500',
            ])
            ->addOption('watch', [
                'help' => 'Automatically reload the server when the application is modified',
                'boolean' => true,
            ])
            ->addOption('poll', [
                'help' => 'Use file system polling while watching in order to watch files over a network',
                'boolean' => true,
            ]);
    }

    /**
     * Executes the command.
     *
     * Applies the extension, port, running, and TLS guards, writes the server
     * state, forgets the environment, and spawns the configured Swoole
     * command with the state path before pumping its output through the
     * server loop.
     *
     * @param \Cake\Console\Arguments $args The command arguments.
     * @param \Cake\Console\ConsoleIo $io The console io.
     * @return int|null The exit code or null for success.
     */
    public function execute(Arguments $args, ConsoleIo $io): ?int
    {
        $this->bindGracefulTermination(function (int $signal) use ($io): void {
            $io->warning('Stimulus Swoole received termination signal, shutting down gracefully...');

            $this->isRunning = false;
        });

        $inspector = $this->serverInspector();

        $extension = $this->swooleExtension();

        if (! $extension->isInstalled()) {
            $this->error('The Swoole extension is missing.');

            return self::CODE_ERROR;
        }

        $this->ensurePortIsAvailable();

        if ($inspector->serverIsRunning()) {
            $this->error('Server is already running.');

            return self::CODE_ERROR;
        }

        if ($this->stimulusConfig('swoole.ssl', false) === true && ! defined('SWOOLE_SSL')) {
            $this->error('You must configure Swoole with `--enable-openssl` to support ssl.');

            return self::CODE_ERROR;
        }

        $serverStateFile = $this->serverStateFile();

        $this->writeServerStateFile($serverStateFile, $extension);

        $this->ensureSwooleLogDirectory($extension);

        $this->forgetEnvironmentVariables();

        $server = new Process(
            [
                (string)(new PhpExecutableFinder())->find(),
                ...(array)$this->stimulusConfig('swoole.php_options', []),
                $this->swooleServerCommand(),
                $serverStateFile->path(),
            ],
            $this->applicationBasePath(),
            [
                'APP_ENV' => $_ENV['APP_ENV'] ?? 'production',
                'APP_BASE_PATH' => $this->applicationBasePath(),
                'STIMULUS' => '1',
            ],
        );

        $server->start();

        return $this->runServer($server, $inspector, 'swoole');
    }

    /**
     * Writes the Swoole server state file.
     *
     * @param \Crustum\Stimulus\Bridge\Swoole\ServerStateFile $serverStateFile State file tracking the server.
     * @param \Crustum\Stimulus\Bridge\Swoole\SwooleExtension $extension Extension helper sizing the workers.
     * @return void
     */
    protected function writeServerStateFile(
        SwooleServerStateFile $serverStateFile,
        SwooleExtension $extension,
    ): void {
        $serverStateFile->writeState([
            'appName' => $this->stimulusConfig('app.name', 'Stimulus'),
            'host' => $this->getHost(),
            'port' => $this->getPort(),
            'appClass' => $this->applicationClass(),
            'workers' => $this->workerCount($extension),
            'taskWorkers' => $this->taskWorkerCount($extension),
            'maxRequests' => $this->serverOption('max-requests'),
            'publicPath' => $this->publicPath(),
            'storagePath' => $this->storagePath(),
            'defaultServerOptions' => $this->defaultServerOptions($extension),
            'stimulusConfig' => Configure::read('Stimulus', []),
        ]);
    }

    /**
     * Gets the default Swoole server options.
     *
     * The Swoole log constants resolve by name with local fallbacks so the
     * file loads without the extension.
     *
     * @param \Crustum\Stimulus\Bridge\Swoole\SwooleExtension $extension Extension helper sizing the workers.
     * @return array<string, mixed> Default Swoole server options.
     */
    protected function defaultServerOptions(SwooleExtension $extension): array
    {
        $isLocal = ($_ENV['APP_ENV'] ?? 'production') === 'local';

        $infoLevel = defined('SWOOLE_LOG_INFO') ? constant('SWOOLE_LOG_INFO') : 0;
        $errorLevel = defined('SWOOLE_LOG_ERROR') ? constant('SWOOLE_LOG_ERROR') : 0;

        return [
            'enable_coroutine' => false,
            'daemonize' => false,
            'log_file' => $this->storagePath() . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'swoole_http.log',
            'log_level' => $isLocal ? $infoLevel : $errorLevel,
            'max_request' => $this->serverOption('max-requests'),
            'package_max_length' => 10 * 1024 * 1024,
            'reactor_num' => $this->workerCount($extension),
            'send_yield' => true,
            'socket_buffer_size' => 10 * 1024 * 1024,
            'task_max_request' => $this->serverOption('max-requests'),
            'task_worker_num' => $this->taskWorkerCount($extension),
            'worker_num' => $this->workerCount($extension),
        ];
    }

    /**
     * Gets the number of workers that should be started.
     *
     * @param \Crustum\Stimulus\Bridge\Swoole\SwooleExtension $extension Extension helper sizing automatic workers.
     * @return int Number of Swoole workers.
     */
    protected function workerCount(SwooleExtension $extension): int
    {
        $workers = $this->serverOption('workers') ?? $this->stimulusConfig('workers', 'auto');

        return $workers === 'auto' ? $extension->cpuCount() : (int)$workers;
    }

    /**
     * Gets the number of task workers that should be started.
     *
     * @param \Crustum\Stimulus\Bridge\Swoole\SwooleExtension $extension Extension helper sizing automatic workers.
     * @return int Number of Swoole task workers.
     */
    protected function taskWorkerCount(SwooleExtension $extension): int
    {
        $taskWorkers = $this->serverOption('task-workers') ?? $this->stimulusConfig('task_workers', 'auto');

        return $taskWorkers === 'auto' ? $extension->cpuCount() : (int)$taskWorkers;
    }

    /**
     * Gets the application storage path for Swoole logs.
     *
     * @return string Absolute path to the storage directory.
     */
    protected function storagePath(): string
    {
        $storagePath = $this->stimulusConfig('storagePath');

        if (is_string($storagePath) && $storagePath !== '') {
            return $storagePath;
        }

        return $this->applicationBasePath() . DIRECTORY_SEPARATOR . 'tmp';
    }

    /**
     * Writes the server process output to the console.
     *
     * Decodes the streams as collection pipelines over the standard and
     * grouped error output lines. Groups hold plain arrays, so the
     * representative line resolves from the group array.
     *
     * @param \Symfony\Component\Process\Process $server Running Swoole server process.
     * @return void
     */
    protected function writeServerOutput(Process $server): void
    {
        [$output, $errorOutput] = $this->getServerOutput($server);

        (new Collection(explode("\n", $output)))
            ->filter()
            ->each(function ($output): void {
                $stream = json_decode((string)$output, true);

                if (is_array($stream)) {
                    $this->handleStream($stream);
                } else {
                    $this->info((string)$output);
                }
            });

        (new Collection(explode("\n", $errorOutput)))
            ->filter()
            ->groupBy(fn($output): string => (string)$output)
            ->each(function ($group): void {
                $lines = is_array($group) ? array_values($group) : [$group];

                $output = (string)($lines[0] ?? '');

                $stream = json_decode($output, true);

                if (is_array($stream) && isset($stream['type'])) {
                    $this->handleStream($stream);
                } else {
                    $this->raw($output);
                }
            });
    }

    /**
     * Stops the server.
     *
     * @return void
     */
    protected function stopServer(): void
    {
        $this->executeSilently(new StopCommand(), ['--server', 'swoole']);
    }

    /**
     * Gets the path to the Swoole server entry.
     *
     * Defaults to the shipped plugin entry. A configured absolute path is
     * used as-is; a relative path resolves below the application base path
     * when present there, otherwise below the plugin binary directory.
     *
     * @return string Absolute path to the Swoole server entry.
     */
    protected function swooleServerCommand(): string
    {
        $command = (string)$this->stimulusConfig('swoole.command', '');

        if ($command !== '' && $this->isAbsolutePath($command)) {
            return $command;
        }

        if ($command !== '') {
            $appPath = $this->applicationBasePath() . DIRECTORY_SEPARATOR . $command;

            if (is_file($appPath)) {
                return $appPath;
            }

            $pluginPath = $this->pluginBinPath() . DIRECTORY_SEPARATOR . $command;

            if (is_file($pluginPath)) {
                return $pluginPath;
            }

            return $appPath;
        }

        return $this->pluginBinPath() . DIRECTORY_SEPARATOR . 'stimulus-swoole-server';
    }

    /**
     * Determines if the given path is absolute.
     *
     * Both starters resolve configured commands the same way on Unix and
     * Windows.
     *
     * @param string $path Filesystem path to check.
     * @return bool Whether the path is absolute on Unix or Windows.
     */
    protected function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/') || preg_match('/^[a-zA-Z]:[\\\\\/]/', $path) === 1;
    }

    /**
     * Ensures the Swoole log directory exists.
     *
     * The default server options point the log file below the application
     * storage path, which may not exist on host applications, so the
     * directory is created before the server boots instead of failing
     * inside the worker process.
     *
     * @param \Crustum\Stimulus\Bridge\Swoole\SwooleExtension $extension Extension helper sizing the workers.
     * @return void
     */
    protected function ensureSwooleLogDirectory(SwooleExtension $extension): void
    {
        $logFile = (string)($this->defaultServerOptions($extension)['log_file'] ?? '');

        if ($logFile === '') {
            return;
        }

        $directory = dirname($logFile);

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
    }

    /**
     * Gets the Swoole server process inspector.
     *
     * @return \Crustum\Stimulus\Contracts\ServerProcessInspector Inspector for the Swoole server.
     */
    protected function serverInspector(): ServerProcessInspectorContract
    {
        if (!$this->inspector instanceof ServerProcessInspectorContract) {
            $this->inspector = $this->defaultSwooleInspector();
        }

        return $this->inspector;
    }

    /**
     * Gets the Swoole server state file.
     *
     * @return \Crustum\Stimulus\Bridge\Swoole\ServerStateFile State file for the Swoole server.
     */
    protected function serverStateFile(): SwooleServerStateFile
    {
        if (!$this->serverStateFile instanceof SwooleServerStateFile) {
            $this->serverStateFile = $this->defaultSwooleStateFile();
        }

        return $this->serverStateFile;
    }

    /**
     * Gets the Swoole extension helper.
     *
     * @return \Crustum\Stimulus\Bridge\Swoole\SwooleExtension Extension helper probing the installation.
     */
    protected function swooleExtension(): SwooleExtension
    {
        if (!$this->extension instanceof SwooleExtension) {
            $this->extension = $this->defaultSwooleExtension();
        }

        return $this->extension;
    }
}
