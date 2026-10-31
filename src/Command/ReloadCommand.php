<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Command;

use Cake\Console\CommandFactoryInterface;
use Cake\Console\ConsoleOptionParser;
use Crustum\Stimulus\Contracts\ServerProcessInspector as ServerProcessInspectorContract;
use Override;

/**
 * Reloads the Stimulus workers.
 *
 * The server name resolves from the `--server` option or the
 * `Stimulus.server` configuration value, and the per-driver inspectors
 * arrive through constructor injection so tests can substitute fakes;
 * unconfigured drivers fall back to the default inspectors built from the
 * configured state file paths.
 */
class ReloadCommand extends StimulusCommand
{
    /**
     * Creates a new reload command.
     *
     * @param \Crustum\Stimulus\Contracts\ServerProcessInspector|null $swooleInspector Inspector for the Swoole server, or null for the default.
     * @param \Crustum\Stimulus\Contracts\ServerProcessInspector|null $roadRunnerInspector Inspector for the RoadRunner server, or null for the default.
     * @param \Crustum\Stimulus\Contracts\ServerProcessInspector|null $frankenPhpInspector Inspector for the FrankenPHP server, or null for the default.
     * @param \Cake\Console\CommandFactoryInterface|null $factory Command factory instance.
     */
    public function __construct(
        protected ?ServerProcessInspectorContract $swooleInspector = null,
        protected ?ServerProcessInspectorContract $roadRunnerInspector = null,
        protected ?ServerProcessInspectorContract $frankenPhpInspector = null,
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
        return 'stimulus reload';
    }

    /**
     * Gets the command description.
     *
     * @return string The command description.
     */
    #[Override]
    public static function getDescription(): string
    {
        return 'Reload the Stimulus workers';
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
            'swoole' => $this->reloadSwooleServer(),
            'roadrunner' => $this->reloadRoadRunnerServer(),
            'frankenphp' => $this->reloadFrankenPhpServer(),
            default => $this->invalidServer((string)$server),
        };
    }

    /**
     * Reloads the Swoole server workers.
     *
     * @return int Command exit code.
     */
    protected function reloadSwooleServer(): int
    {
        $inspector = $this->swooleInspector();

        if (! $inspector->serverIsRunning()) {
            $this->error('Stimulus server is not running.');

            return self::CODE_ERROR;
        }

        $this->info('Reloading workers...');

        $inspector->reloadServer();

        return self::CODE_SUCCESS;
    }

    /**
     * Reloads the RoadRunner server workers.
     *
     * @return int Command exit code.
     */
    protected function reloadRoadRunnerServer(): int
    {
        $inspector = $this->roadRunnerInspector();

        if (! $inspector->serverIsRunning()) {
            $this->error('Stimulus server is not running.');

            return self::CODE_ERROR;
        }

        $this->info('Reloading workers...');

        $inspector->reloadServer();

        return self::CODE_SUCCESS;
    }

    /**
     * Reloads the FrankenPHP server workers.
     *
     * @return int Command exit code.
     */
    protected function reloadFrankenPhpServer(): int
    {
        $inspector = $this->frankenPhpInspector();

        if (! $inspector->serverIsRunning()) {
            $this->error('Stimulus server is not running.');

            return self::CODE_ERROR;
        }

        $this->info('Reloading workers...');

        $inspector->reloadServer();

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
}
