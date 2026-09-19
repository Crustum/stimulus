<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Command;

use Cake\Command\Command;
use Cake\Console\CommandInterface;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOutput;
use Cake\Core\Configure;
use Crustum\Stimulus\Bridge\FrankenPhp\ServerProcessInspector as FrankenPhpServerProcessInspector;
use Crustum\Stimulus\Bridge\FrankenPhp\ServerStateFile as FrankenPhpServerStateFile;
use Crustum\Stimulus\Bridge\RoadRunner\ServerProcessInspector as RoadRunnerServerProcessInspector;
use Crustum\Stimulus\Bridge\RoadRunner\ServerStateFile as RoadRunnerServerStateFile;
use Crustum\Stimulus\Bridge\Swoole\ServerProcessInspector as SwooleServerProcessInspector;
use Crustum\Stimulus\Bridge\Swoole\ServerStateFile as SwooleServerStateFile;
use Crustum\Stimulus\Bridge\Swoole\SignalDispatcher;
use Crustum\Stimulus\Bridge\Swoole\SwooleExtension;
use Crustum\Stimulus\Command\Trait\InteractsWithIoTrait;
use Crustum\Stimulus\Contracts\ServerProcessInspector as ServerProcessInspectorContract;
use Crustum\Stimulus\Process\Exec;
use Crustum\Stimulus\Process\SymfonyProcessFactory;
use Crustum\Stimulus\System\PosixExtension;
use Crustum\Stimulus\System\ProcessTerminator;

/**
 * Abstract base command for the Stimulus server suite.
 *
 * The `InteractsWithIO` concern is folded in through the
 * `InteractsWithIoTrait`, keeping the one-to-one file mapping with the
 * concern instead of inlining the formatters into this base. Parsed options
 * are exposed through `serverOption()`, replacing the `option()` helper, and
 * the `isRunning` flag carries the Rhythm `CheckCommand` graceful
 * termination pattern for the long-running starter commands.
 */
abstract class StimulusCommand extends Command
{
    use InteractsWithIoTrait;

    /**
     * Whether the server loop should keep running.
     *
     * Long-running commands flip this flag inside their
     * `bindGracefulTermination()` callback for the Rhythm `CheckCommand`
     * shutdown pattern.
     *
     * @var bool
     */
    protected bool $isRunning = true;

    /**
     * Reads a parsed command option.
     *
     * Replaces the `option()` helper so the server traits can read options
     * without touching the arguments object directly.
     *
     * @param string $name Option name to read.
     * @return string|bool|null Option value, or null when the option is missing.
     */
    protected function serverOption(string $name): string|bool|null
    {
        return $this->args->getOption($name);
    }

    /**
     * Determines whether the server loop should keep running.
     *
     * Checked by the server run loop alongside the process liveness so
     * graceful termination stops the loop even while the process lives.
     *
     * @return bool Whether the server loop should continue.
     */
    protected function serverLoopRunning(): bool
    {
        return $this->isRunning;
    }

    /**
     * Confirms an action with the user.
     *
     * Asks a yes/no choice defaulting as requested.
     *
     * @param string $prompt Question asked to the user.
     * @param bool $default Default answer used for non-interactive runs.
     * @return bool Whether the user confirmed the action.
     */
    protected function confirmAction(string $prompt, bool $default = true): bool
    {
        return $this->io->askChoice(
            $prompt,
            ['yes', 'no'],
            $default ? 'yes' : 'no',
        ) === 'yes';
    }

    /**
     * Reads a Stimulus configuration value.
     *
     * @param string $key Configuration key below `Stimulus.`.
     * @param mixed $default Default returned when the key is missing.
     * @return mixed Configuration value or the default.
     */
    protected function stimulusConfig(string $key, mixed $default = null): mixed
    {
        return Configure::read('Stimulus.' . $key, $default);
    }

    /**
     * Gets the worker HTTP server host IP to bind on.
     *
     * Resolves from the `--host` option, the `Stimulus.host` configuration
     * value, the environment, or the loopback default.
     *
     * @return string Host IP the server binds on.
     */
    protected function getHost(): string
    {
        $host = $this->serverOption('host')
            ?? Configure::read('Stimulus.host')
            ?? $_ENV['STIMULUS_HOST']
            ?? '127.0.0.1';

        return (string)$host;
    }

    /**
     * Gets the worker HTTP server port.
     *
     * Resolves from the `--port` option, the `Stimulus.port` configuration
     * value, the environment, or the `8000` default.
     *
     * @return string Port the server is available on.
     */
    protected function getPort(): string
    {
        $port = $this->serverOption('port')
            ?? Configure::read('Stimulus.port')
            ?? $_ENV['STIMULUS_PORT']
            ?? '8000';

        return (string)$port;
    }

    /**
     * Gets the worker application class booted inside the worker.
     *
     * Resolves from the `Stimulus.appClass` configuration value, the
     * environment, or the conventional application class.
     *
     * @return string Application class name booting the worker.
     */
    protected function applicationClass(): string
    {
        $appClass = Configure::read('Stimulus.appClass')
            ?? $_ENV['STIMULUS_APP_CLASS']
            ?? 'App\\Application';

        return (string)$appClass;
    }

    /**
     * Gets the application base path used to resolve server binaries.
     *
     * @return string Absolute path to the application base directory.
     */
    protected function applicationBasePath(): string
    {
        $basePath = Configure::read('Stimulus.basePath');

        if (is_string($basePath) && $basePath !== '') {
            return $basePath;
        }

        $workingDirectory = getcwd();

        return $workingDirectory === false ? sys_get_temp_dir() : $workingDirectory;
    }

    /**
     * Gets the path to the application env file.
     *
     * Resolves from the `Stimulus.envFile` configuration value or the
     * application base path.
     *
     * @return string Absolute path to the env file carrying the server variables.
     */
    protected function environmentFilePath(): string
    {
        $envFile = Configure::read('Stimulus.envFile');

        if (is_string($envFile) && $envFile !== '') {
            return $envFile;
        }

        return $this->applicationBasePath() . DIRECTORY_SEPARATOR . '.env';
    }

    /**
     * Gets the application public path serving worker assets.
     *
     * @return string Absolute path to the public webroot directory.
     */
    protected function publicPath(): string
    {
        $publicPath = Configure::read('Stimulus.publicPath');

        if (is_string($publicPath) && $publicPath !== '') {
            return $publicPath;
        }

        return $this->applicationBasePath() . DIRECTORY_SEPARATOR . 'webroot';
    }

    /**
     * Gets the plugin root path carrying the shipped server assets.
     *
     * @return string Absolute path to the plugin root directory.
     */
    protected function pluginRootPath(): string
    {
        return dirname(__DIR__, 2);
    }

    /**
     * Gets the plugin binary path carrying the file watcher.
     *
     * @return string Absolute path to the plugin binary directory.
     */
    protected function pluginBinPath(): string
    {
        return $this->pluginRootPath() . DIRECTORY_SEPARATOR . 'bin';
    }

    /**
     * Gets the path to a shipped server resource.
     *
     * @param string $file Resource file name below `resources/server/`.
     * @return string Absolute path to the shipped server resource.
     */
    protected function serverResourcePath(string $file): string
    {
        return $this->pluginRootPath()
            . DIRECTORY_SEPARATOR . 'resources'
            . DIRECTORY_SEPARATOR . 'server'
            . DIRECTORY_SEPARATOR . $file;
    }

    /**
     * Executes another command without forwarding its output.
     *
     * Runs against an in-memory output stream to keep the sub-command
     * silent.
     *
     * @param \Cake\Console\CommandInterface $command Command to execute.
     * @param array<int|string, mixed> $args Arguments forwarded to the command.
     * @return int|null Exit code of the executed command.
     */
    protected function executeSilently(CommandInterface $command, array $args = []): ?int
    {
        $null = DIRECTORY_SEPARATOR === '\\' ? 'NUL' : '/dev/null';

        return $this->executeCommand(
            $command,
            $args,
            new ConsoleIo(new ConsoleOutput($null), new ConsoleOutput($null)),
        );
    }

    /**
     * Creates the default RoadRunner server process inspector.
     *
     * @return \Crustum\Stimulus\Contracts\ServerProcessInspector Inspector for the RoadRunner server.
     */
    protected function defaultRoadRunnerInspector(): ServerProcessInspectorContract
    {
        return new RoadRunnerServerProcessInspector(
            $this->defaultRoadRunnerStateFile(),
            new SymfonyProcessFactory(),
            new ProcessTerminator(new PosixExtension()),
        );
    }

    /**
     * Creates the default RoadRunner server state file.
     *
     * @return \Crustum\Stimulus\Bridge\RoadRunner\ServerStateFile State file tracking the RoadRunner server.
     */
    protected function defaultRoadRunnerStateFile(): RoadRunnerServerStateFile
    {
        $path = $this->stimulusConfig('stateFile.roadrunner');

        if (! is_string($path) || $path === '') {
            $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'stimulus-roadrunner.json';
        }

        return new RoadRunnerServerStateFile($path);
    }

    /**
     * Creates the default FrankenPHP server process inspector.
     *
     * @return \Crustum\Stimulus\Contracts\ServerProcessInspector Inspector for the FrankenPHP server.
     */
    protected function defaultFrankenPhpInspector(): ServerProcessInspectorContract
    {
        return new FrankenPhpServerProcessInspector(
            $this->defaultFrankenPhpStateFile(),
        );
    }

    /**
     * Creates the default FrankenPHP server state file.
     *
     * @return \Crustum\Stimulus\Bridge\FrankenPhp\ServerStateFile State file tracking the FrankenPHP server.
     */
    protected function defaultFrankenPhpStateFile(): FrankenPhpServerStateFile
    {
        $path = $this->stimulusConfig('stateFile.frankenphp');

        if (! is_string($path) || $path === '') {
            $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'stimulus-frankenphp.json';
        }

        return new FrankenPhpServerStateFile($path);
    }

    /**
     * Creates the default Swoole server process inspector.
     *
     * @return \Crustum\Stimulus\Contracts\ServerProcessInspector Inspector for the Swoole server.
     */
    protected function defaultSwooleInspector(): ServerProcessInspectorContract
    {
        return new SwooleServerProcessInspector(
            new SignalDispatcher(new SwooleExtension()),
            $this->defaultSwooleStateFile(),
            new Exec(),
        );
    }

    /**
     * Creates the default Swoole server state file.
     *
     * @return \Crustum\Stimulus\Bridge\Swoole\ServerStateFile State file tracking the Swoole server.
     */
    protected function defaultSwooleStateFile(): SwooleServerStateFile
    {
        $path = $this->stimulusConfig('stateFile.swoole');

        if (! is_string($path) || $path === '') {
            $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'stimulus-swoole.json';
        }

        return new SwooleServerStateFile($path);
    }

    /**
     * Creates the default Swoole extension helper.
     *
     * @return \Crustum\Stimulus\Bridge\Swoole\SwooleExtension Helper probing the Swoole extension.
     */
    protected function defaultSwooleExtension(): SwooleExtension
    {
        return new SwooleExtension();
    }
}
