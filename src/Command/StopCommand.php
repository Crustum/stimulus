<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Command;

use Cake\Console\CommandFactoryInterface;
use Cake\Console\ConsoleOptionParser;
use Crustum\Stimulus\Bridge\FrankenPhp\ServerStateFile as FrankenPhpServerStateFile;
use Crustum\Stimulus\Bridge\RoadRunner\ServerStateFile as RoadRunnerServerStateFile;
use Crustum\Stimulus\Bridge\Swoole\ServerStateFile as SwooleServerStateFile;
use Crustum\Stimulus\Contracts\ServerProcessInspector as ServerProcessInspectorContract;
use Override;

/**
 * Stops the Stimulus server.
 *
 * Each driver stops through its injected inspector and removes its state
 * file, including the Swoole failure branch. Unconfigured drivers fall back
 * to the default inspectors and state files built from the configured paths.
 */
class StopCommand extends StimulusCommand
{
    /**
     * Creates a new stop command.
     *
     * @param \Crustum\Stimulus\Contracts\ServerProcessInspector|null $swooleInspector Inspector for the Swoole server, or null for the default.
     * @param \Crustum\Stimulus\Contracts\ServerProcessInspector|null $roadRunnerInspector Inspector for the RoadRunner server, or null for the default.
     * @param \Crustum\Stimulus\Contracts\ServerProcessInspector|null $frankenPhpInspector Inspector for the FrankenPHP server, or null for the default.
     * @param \Crustum\Stimulus\Bridge\Swoole\ServerStateFile|null $swooleStateFile State file for the Swoole server, or null for the default.
     * @param \Crustum\Stimulus\Bridge\RoadRunner\ServerStateFile|null $roadRunnerStateFile State file for the RoadRunner server, or null for the default.
     * @param \Crustum\Stimulus\Bridge\FrankenPhp\ServerStateFile|null $frankenPhpStateFile State file for the FrankenPHP server, or null for the default.
     * @param \Cake\Console\CommandFactoryInterface|null $factory Command factory instance.
     */
    public function __construct(
        protected ?ServerProcessInspectorContract $swooleInspector = null,
        protected ?ServerProcessInspectorContract $roadRunnerInspector = null,
        protected ?ServerProcessInspectorContract $frankenPhpInspector = null,
        protected ?SwooleServerStateFile $swooleStateFile = null,
        protected ?RoadRunnerServerStateFile $roadRunnerStateFile = null,
        protected ?FrankenPhpServerStateFile $frankenPhpStateFile = null,
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
        return 'stimulus stop';
    }

    /**
     * Gets the command description.
     *
     * @return string The command description.
     */
    #[Override]
    public static function getDescription(): string
    {
        return 'Stop the Stimulus server';
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
        return $parser->addOption('server', [
            'help' => 'The server that is running the application',
        ]);
    }

    /**
     * Executes the command.
     *
     * @return int|null The exit code or null for success.
     */
    public function execute(): ?int
    {
        $server = $this->args->getOption('server') ?: $this->stimulusConfig('server', 'roadrunner');

        return match ($server) {
            'swoole' => $this->stopSwooleServer(),
            'roadrunner' => $this->stopRoadRunnerServer(),
            'frankenphp' => $this->stopFrankenPhpServer(),
            default => $this->invalidServer((string)$server),
        };
    }

    /**
     * Stops the Swoole server.
     *
     * Removes the state file even when the server is not running, and reports
     * a failure when the inspector cannot stop the server.
     *
     * @return int Command exit code.
     */
    protected function stopSwooleServer(): int
    {
        $inspector = $this->swooleInspector();

        if (! $inspector->serverIsRunning()) {
            $this->swooleStateFile()->delete();

            $this->error('Swoole server is not running.');

            return self::CODE_ERROR;
        }

        $this->info('Stopping server...');

        if (! $inspector->stopServer()) {
            $this->error('Failed to stop Swoole server.');

            return self::CODE_ERROR;
        }

        $this->swooleStateFile()->delete();

        return self::CODE_SUCCESS;
    }

    /**
     * Stops the RoadRunner server.
     *
     * @return int Command exit code.
     */
    protected function stopRoadRunnerServer(): int
    {
        $inspector = $this->roadRunnerInspector();

        if (! $inspector->serverIsRunning()) {
            $this->roadRunnerStateFile()->delete();

            $this->error('RoadRunner server is not running.');

            return self::CODE_ERROR;
        }

        $this->info('Stopping server...');

        $inspector->stopServer();

        $this->roadRunnerStateFile()->delete();

        return self::CODE_SUCCESS;
    }

    /**
     * Stops the FrankenPHP server.
     *
     * @return int Command exit code.
     */
    protected function stopFrankenPhpServer(): int
    {
        $inspector = $this->frankenPhpInspector();

        if (! $inspector->serverIsRunning()) {
            $this->frankenPhpStateFile()->delete();

            $this->error('FrankenPHP server is not running.');

            return self::CODE_ERROR;
        }

        $this->info('Stopping server...');

        $inspector->stopServer();

        $this->frankenPhpStateFile()->delete();

        return self::CODE_SUCCESS;
    }

    /**
     * Informs the user that the server type is invalid.
     *
     * @param string $server Invalid server name given by the user.
     * @return int Command exit code.
     */
    protected function invalidServer(string $server): int
    {
        $this->error("Invalid server: {$server}.");

        return self::CODE_ERROR;
    }

    /**
     * Gets the Swoole server process inspector.
     *
     * @return \Crustum\Stimulus\Contracts\ServerProcessInspector Inspector for the Swoole server.
     */
    protected function swooleInspector(): ServerProcessInspectorContract
    {
        return $this->swooleInspector ??= $this->defaultSwooleInspector();
    }

    /**
     * Gets the RoadRunner server process inspector.
     *
     * @return \Crustum\Stimulus\Contracts\ServerProcessInspector Inspector for the RoadRunner server.
     */
    protected function roadRunnerInspector(): ServerProcessInspectorContract
    {
        return $this->roadRunnerInspector ??= $this->defaultRoadRunnerInspector();
    }

    /**
     * Gets the FrankenPHP server process inspector.
     *
     * @return \Crustum\Stimulus\Contracts\ServerProcessInspector Inspector for the FrankenPHP server.
     */
    protected function frankenPhpInspector(): ServerProcessInspectorContract
    {
        return $this->frankenPhpInspector ??= $this->defaultFrankenPhpInspector();
    }

    /**
     * Gets the Swoole server state file.
     *
     * @return \Crustum\Stimulus\Bridge\Swoole\ServerStateFile State file for the Swoole server.
     */
    protected function swooleStateFile(): SwooleServerStateFile
    {
        return $this->swooleStateFile ??= $this->defaultSwooleStateFile();
    }

    /**
     * Gets the RoadRunner server state file.
     *
     * @return \Crustum\Stimulus\Bridge\RoadRunner\ServerStateFile State file for the RoadRunner server.
     */
    protected function roadRunnerStateFile(): RoadRunnerServerStateFile
    {
        return $this->roadRunnerStateFile ??= $this->defaultRoadRunnerStateFile();
    }

    /**
     * Gets the FrankenPHP server state file.
     *
     * @return \Crustum\Stimulus\Bridge\FrankenPhp\ServerStateFile State file for the FrankenPHP server.
     */
    protected function frankenPhpStateFile(): FrankenPhpServerStateFile
    {
        return $this->frankenPhpStateFile ??= $this->defaultFrankenPhpStateFile();
    }
}
