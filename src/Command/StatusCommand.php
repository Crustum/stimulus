<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Command;

use Cake\Console\Arguments;
use Cake\Console\CommandFactoryInterface;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Crustum\Stimulus\Contracts\ServerProcessInspector as ServerProcessInspectorContract;
use Override;

/**
 * Reports the current status of the Stimulus server.
 *
 * The running probe resolves per driver through constructor-injected
 * inspectors, defaulting to the inspectors built from the configured state
 * file paths, and the exit code inverts the running flag.
 */
class StatusCommand extends StimulusCommand
{
    /**
     * Creates a new status command.
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
        return 'stimulus status';
    }

    /**
     * Gets the command description.
     *
     * @return string The command description.
     */
    #[Override]
    public static function getDescription(): string
    {
        return 'Get the current status of the Stimulus server';
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
     * @param \Cake\Console\Arguments $args The command arguments.
     * @param \Cake\Console\ConsoleIo $io The console io.
     * @return int|null The exit code or null for success.
     */
    public function execute(Arguments $args, ConsoleIo $io): ?int
    {
        $server = $args->getOption('server') ?: $this->stimulusConfig('server', 'roadrunner');

        $isRunning = match ($server) {
            'swoole' => $this->isSwooleServerRunning(),
            'roadrunner' => $this->isRoadRunnerServerRunning(),
            'frankenphp' => $this->isFrankenPhpServerRunning(),
            default => $this->invalidServer((string)$server),
        };

        if ($isRunning) {
            $this->info('Stimulus server is running.');
        } else {
            $this->info('Stimulus server is not running.');
        }

        return $isRunning ? self::CODE_SUCCESS : self::CODE_ERROR;
    }

    /**
     * Checks if the Swoole server is running.
     *
     * @return bool Whether the Swoole server process is running.
     */
    protected function isSwooleServerRunning(): bool
    {
        return $this->swooleInspector()->serverIsRunning();
    }

    /**
     * Checks if the RoadRunner server is running.
     *
     * @return bool Whether the RoadRunner server process is running.
     */
    protected function isRoadRunnerServerRunning(): bool
    {
        return $this->roadRunnerInspector()->serverIsRunning();
    }

    /**
     * Checks if the FrankenPHP server is running.
     *
     * @return bool Whether the FrankenPHP server process is running.
     */
    protected function isFrankenPhpServerRunning(): bool
    {
        return $this->frankenPhpInspector()->serverIsRunning();
    }

    /**
     * Informs the user that the server type is invalid.
     *
     * @param string $server Invalid server name given by the user.
     * @return bool False so the status message reports the server as stopped.
     */
    protected function invalidServer(string $server): bool
    {
        $this->error("Invalid server: {$server}.");

        return false;
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
