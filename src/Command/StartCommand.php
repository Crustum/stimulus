<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Command;

use Cake\Collection\Collection;
use Cake\Console\Arguments;
use Cake\Console\CommandFactoryInterface;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Override;
use SignalHandler\Command\Trait\SignalHandlerTrait;

/**
 * Starts the Stimulus server.
 *
 * The selected driver forwards to its hidden starter command through
 * list-style argument vectors. Graceful termination binds like the
 * starters; the forwarded starter rebinds the same signals for its own run
 * loop, so the dispatcher binding only covers the pre-forward window.
 */
class StartCommand extends StimulusCommand
{
    use SignalHandlerTrait;

    /**
     * Creates a new start dispatcher command.
     *
     * @param \Crustum\Stimulus\Command\StartSwooleCommand|null $swooleStarter Starter for the Swoole server, or null for the default.
     * @param \Crustum\Stimulus\Command\StartRoadRunnerCommand|null $roadRunnerStarter Starter for the RoadRunner server, or null for the default.
     * @param \Crustum\Stimulus\Command\StartFrankenPhpCommand|null $frankenPhpStarter Starter for the FrankenPHP server, or null for the default.
     * @param \Cake\Console\CommandFactoryInterface|null $factory Command factory instance.
     */
    public function __construct(
        protected ?StartSwooleCommand $swooleStarter = null,
        protected ?StartRoadRunnerCommand $roadRunnerStarter = null,
        protected ?StartFrankenPhpCommand $frankenPhpStarter = null,
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
        return 'stimulus start';
    }

    /**
     * Gets the command description.
     *
     * @return string The command description.
     */
    #[Override]
    public static function getDescription(): string
    {
        return 'Start the Stimulus server';
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
            ->addOption('server', ['help' => 'The server that should be used to serve the application'])
            ->addOption('host', ['help' => 'The IP address the server should bind to'])
            ->addOption('port', [
                'help' => 'The port the server should be available on',
                'default' => '8000',
            ])
            ->addOption('admin-port', ['help' => 'The port the admin server should be available on'])
            ->addOption('rpc-host', ['help' => 'The RPC IP address the server should bind to'])
            ->addOption('rpc-port', ['help' => 'The RPC port the server should be available on'])
            ->addOption('workers', ['help' => 'The number of workers that should be available to handle requests'])
            ->addOption('task-workers', ['help' => 'The number of task workers that should be available to handle tasks'])
            ->addOption('max-requests', ['help' => 'The number of requests to process before reloading the server'])
            ->addOption('rr-config', ['help' => 'The path to the RoadRunner .rr.yaml file'])
            ->addOption('caddyfile', ['help' => 'The path to the FrankenPHP Caddyfile file'])
            ->addOption('https', [
                'help' => 'Enable HTTPS, HTTP/2, and HTTP/3, and automatically generate and renew certificates',
                'boolean' => true,
            ])
            ->addOption('http-redirect', [
                'help' => 'Enable HTTP to HTTPS redirection (only enabled if --https is passed)',
                'boolean' => true,
            ])
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
     * Forwards the parsed options to the starter of the selected server per
     * driver.
     *
     * @param \Cake\Console\Arguments $args The command arguments.
     * @param \Cake\Console\ConsoleIo $io The console io.
     * @return int|null The exit code or null for success.
     */
    public function execute(Arguments $args, ConsoleIo $io): ?int
    {
        $this->bindGracefulTermination(function (int $signal) use ($io): void {
            $io->warning('Stimulus start received termination signal, shutting down gracefully...');

            $this->isRunning = false;
        });

        $server = $args->getOption('server') ?: $this->stimulusConfig('server', 'roadrunner');

        return match ($server) {
            'swoole' => $this->startSwooleServer($io),
            'roadrunner' => $this->startRoadRunnerServer($io),
            'frankenphp' => $this->startFrankenPhpServer($io),
            default => $this->invalidServer((string)$server),
        };
    }

    /**
     * Starts the Swoole server for Stimulus.
     *
     * @param \Cake\Console\ConsoleIo $io The console io.
     * @return int|null The exit code or null for success of the starter.
     */
    protected function startSwooleServer(ConsoleIo $io): ?int
    {
        return $this->executeCommand(
            $this->swooleStarter(),
            $this->forwardArguments([
                'host' => $this->getHost(),
                'port' => $this->getPort(),
                'workers' => $this->serverOption('workers') ?? $this->stimulusConfig('workers', 'auto'),
                'task-workers' => $this->serverOption('task-workers') ?? $this->stimulusConfig('task_workers', 'auto'),
                'max-requests' => $this->serverOption('max-requests') ?? $this->stimulusConfig('max_requests', 500),
                'watch' => $this->serverOption('watch'),
                'poll' => $this->serverOption('poll'),
            ]),
            $io,
        );
    }

    /**
     * Starts the RoadRunner server for Stimulus.
     *
     * @param \Cake\Console\ConsoleIo $io The console io.
     * @return int|null The exit code or null for success of the starter.
     */
    protected function startRoadRunnerServer(ConsoleIo $io): ?int
    {
        return $this->executeCommand(
            $this->roadRunnerStarter(),
            $this->forwardArguments([
                'host' => $this->getHost(),
                'port' => $this->getPort(),
                'rpc-host' => $this->serverOption('rpc-host'),
                'rpc-port' => $this->serverOption('rpc-port'),
                'workers' => $this->serverOption('workers') ?? $this->stimulusConfig('workers', 'auto'),
                'max-requests' => $this->serverOption('max-requests') ?? $this->stimulusConfig('max_requests', 500),
                'rr-config' => $this->serverOption('rr-config'),
                'watch' => $this->serverOption('watch'),
                'poll' => $this->serverOption('poll'),
                'log-level' => $this->serverOption('log-level'),
            ]),
            $io,
        );
    }

    /**
     * Starts the FrankenPHP server for Stimulus.
     *
     * @param \Cake\Console\ConsoleIo $io The console io.
     * @return int|null The exit code or null for success of the starter.
     */
    protected function startFrankenPhpServer(ConsoleIo $io): ?int
    {
        return $this->executeCommand(
            $this->frankenPhpStarter(),
            $this->forwardArguments([
                'host' => $this->getHost(),
                'port' => $this->getPort(),
                'admin-port' => $this->serverOption('admin-port'),
                'workers' => $this->serverOption('workers') ?? $this->stimulusConfig('workers', 'auto'),
                'max-requests' => $this->serverOption('max-requests') ?? $this->stimulusConfig('max_requests', 500),
                'caddyfile' => $this->serverOption('caddyfile'),
                'https' => $this->serverOption('https'),
                'http-redirect' => $this->serverOption('http-redirect'),
                'watch' => $this->serverOption('watch'),
                'poll' => $this->serverOption('poll'),
                'log-level' => $this->serverOption('log-level'),
            ]),
            $io,
        );
    }

    /**
     * Converts named option values into a list-style argument vector.
     *
     * Missing and negative values are filtered out of the pipeline so the
     * starter defaults apply.
     *
     * @param array<string, mixed> $options Option names mapped to their values.
     * @return array<int, string> List-style argument vector for the starter.
     */
    protected function forwardArguments(array $options): array
    {
        $forwarded = [];

        (new Collection($options))
            ->filter(fn($value): bool => !in_array($value, [null, false, ''], true))
            ->each(function ($value, $name) use (&$forwarded): void {
                if ($value === true) {
                    $forwarded[] = "--{$name}";
                } else {
                    $forwarded[] = "--{$name}";
                    $forwarded[] = (string)$value;
                }
            });

        return $forwarded;
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
     * Stops the server.
     *
     * @return void
     */
    protected function stopServer(): void
    {
        $server = $this->serverOption('server') ?: $this->stimulusConfig('server', 'roadrunner');

        $this->executeSilently(new StopCommand(), ['--server', (string)$server]);
    }

    /**
     * Gets the Swoole starter command.
     *
     * @return \Crustum\Stimulus\Command\StartSwooleCommand Starter for the Swoole server.
     */
    protected function swooleStarter(): StartSwooleCommand
    {
        return $this->swooleStarter ??= new StartSwooleCommand();
    }

    /**
     * Gets the RoadRunner starter command.
     *
     * @return \Crustum\Stimulus\Command\StartRoadRunnerCommand Starter for the RoadRunner server.
     */
    protected function roadRunnerStarter(): StartRoadRunnerCommand
    {
        return $this->roadRunnerStarter ??= new StartRoadRunnerCommand();
    }

    /**
     * Gets the FrankenPHP starter command.
     *
     * @return \Crustum\Stimulus\Command\StartFrankenPhpCommand Starter for the FrankenPHP server.
     */
    protected function frankenPhpStarter(): StartFrankenPhpCommand
    {
        return $this->frankenPhpStarter ??= new StartFrankenPhpCommand();
    }
}
