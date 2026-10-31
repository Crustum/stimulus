<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Command;

use Cake\Collection\Collection;
use Cake\Console\CommandFactoryInterface;
use Cake\Console\ConsoleOptionParser;
use Cake\Core\Configure;
use Crustum\Stimulus\Bridge\FrankenPhp\ServerProcessInspector as FrankenPhpServerProcessInspector;
use Crustum\Stimulus\Bridge\FrankenPhp\ServerStateFile as FrankenPhpServerStateFile;
use Crustum\Stimulus\Command\Trait\InstallsFrankenPhpDependenciesTrait;
use Crustum\Stimulus\Command\Trait\InteractsWithEnvironmentVariablesTrait;
use Crustum\Stimulus\Command\Trait\InteractsWithServersTrait;
use Crustum\Stimulus\Contracts\ServerProcessInspector as ServerProcessInspectorContract;
use InvalidArgumentException;
use Override;
use SignalHandler\Command\Trait\SignalHandlerTrait;
use Symfony\Component\Process\Process;

/**
 * Starts the Stimulus FrankenPHP server.
 *
 * The server spawns through `frankenphp run` with the shipped Caddyfile,
 * with the Caddy environment, worker count, Mercure, and watcher directives
 * mapped onto configuration reads.
 */
class StartFrankenPhpCommand extends StimulusCommand
{
    use SignalHandlerTrait;
    use InstallsFrankenPhpDependenciesTrait;
    use InteractsWithEnvironmentVariablesTrait;
    use InteractsWithServersTrait;

    /**
     * Creates a new FrankenPHP starter command.
     *
     * @param \Crustum\Stimulus\Contracts\ServerProcessInspector|null $inspector Inspector for the FrankenPHP server, or null for the default.
     * @param \Crustum\Stimulus\Bridge\FrankenPhp\ServerStateFile|null $serverStateFile State file for the FrankenPHP server, or null for the default.
     * @param \Cake\Console\CommandFactoryInterface|null $factory Command factory instance.
     */
    public function __construct(
        protected ?ServerProcessInspectorContract $inspector = null,
        protected ?FrankenPhpServerStateFile $serverStateFile = null,
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
        return 'stimulus frankenphp';
    }

    /**
     * Gets the command description.
     *
     * @return string The command description.
     */
    #[Override]
    public static function getDescription(): string
    {
        return 'Start the Stimulus FrankenPHP server';
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
            ->addOption('host', [
                'help' => 'The IP address the server should bind to',
                'default' => '127.0.0.1',
            ])
            ->addOption('port', ['help' => 'The port the server should be available on'])
            ->addOption('admin-host', [
                'help' => 'The host the admin server should be available on',
                'default' => 'localhost',
            ])
            ->addOption('admin-port', [
                'help' => 'The port the admin server should be available on',
                'default' => '2019',
            ])
            ->addOption('workers', [
                'help' => 'The number of workers that should be available to handle requests',
                'default' => 'auto',
            ])
            ->addOption('max-requests', [
                'help' => 'The number of requests to process before reloading the server',
                'default' => '500',
            ])
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
     * Guards the worker file, ports, running state, and binary requirements,
     * then spawns `frankenphp run` with the Caddy environment and pumps its
     * output through the server loop.
     *
     * @return int|null The exit code or null for success.
     */
    public function execute(): ?int
    {
        $this->bindGracefulTermination(function (int $signal): void {
            $this->io->warning('Stimulus FrankenPHP received termination signal, shutting down gracefully...');

            $this->isRunning = false;
        });

        $inspector = $this->serverInspector();

        $this->ensureFrankenPhpWorkerIsInstalled();

        $this->ensurePortIsAvailable();

        $frankenphpBinary = $this->ensureFrankenPhpBinaryIsInstalled();

        if ($inspector->serverIsRunning()) {
            $this->error('FrankenPHP server is already running.');

            return self::CODE_ERROR;
        }

        $this->ensureFrankenPhpBinaryMeetsRequirements($frankenphpBinary);

        $serverStateFile = $this->serverStateFile();

        $this->writeServerStateFile($serverStateFile);

        $this->forgetEnvironmentVariables();

        $host = $this->getHost();

        $port = $this->getPort();

        $https = $this->serverOption('https');

        $serverName = $https ? "https://{$host}:{$port}" : "http://:{$port}";

        $logLevel = $this->serverOption('log-level');

        if (! is_string($logLevel) || $logLevel === '') {
            $logLevel = ($_ENV['APP_ENV'] ?? 'production') === 'local' ? 'INFO' : 'WARN';
        }

        $server = new Process(
            [
                $frankenphpBinary,
                'run',
                '-c',
                $this->configPath(),
            ],
            $this->applicationBasePath(),
            [
                'APP_ENV' => $_ENV['APP_ENV'] ?? 'production',
                'APP_BASE_PATH' => $this->applicationBasePath(),
                'APP_PUBLIC_PATH' => $this->publicPath(),
                'STIMULUS' => '1',
                'STIMULUS_APP_CLASS' => $this->applicationClass(),
                'MAX_REQUESTS' => (string)$this->serverOption('max-requests'),
                'REQUEST_MAX_EXECUTION_TIME' => (string)$this->maxExecutionTime(),
                'CADDY_GLOBAL_OPTIONS' => $https && $this->serverOption('http-redirect')
                    ? ''
                    : 'auto_https disable_redirects',
                'CADDY_SERVER_ADMIN_PORT' => (string)$this->adminPort(),
                'CADDY_SERVER_ADMIN_HOST' => (string)$this->serverOption('admin-host'),
                'CADDY_SERVER_LOG_LEVEL' => $logLevel,
                'CADDY_SERVER_LOGGER' => 'json',
                'CADDY_SERVER_SERVER_NAME' => $serverName,
                'CADDY_SERVER_WORKER_COUNT' => $this->workerCount() ?: '',
                'CADDY_SERVER_WORKER_DIRECTIVE' => $this->workerCount()
                    ? "num {$this->workerCount()}"
                    : '',
                'CADDY_SERVER_EXTRA_DIRECTIVES' => $this->buildMercureConfig(),
                ...$this->mercureEnvironmentVariables(),
                'CADDY_SERVER_WATCH_DIRECTIVES' => $this->buildWatchConfig(),
                ...(array)$this->stimulusConfig('caddy.env', []),
            ],
        );

        $server->start();

        $pid = $server->getPid();

        $serverStateFile->writeProcessId($pid ?? 0);

        return $this->runServer($server, $inspector, 'frankenphp');
    }

    /**
     * Ensures the server and admin localhost ports are available.
     *
     * Extends the base port guard with the admin port when binding localhost.
     * Probes each port silently so a free port never surfaces a connection
     * warning. Ports iterate imperatively with an early throw because the
     * guard stops at the first port in use.
     *
     * @return void
     * @throws \InvalidArgumentException When the server or admin port is already in use.
     */
    protected function ensurePortIsAvailable(): void
    {
        $host = $this->getHost();

        $serverPort = $this->getPort();

        $adminPort = $this->adminPort();

        if ($host !== '127.0.0.1') {
            return;
        }

        set_error_handler(static fn(): bool => true);

        try {
            foreach ([$serverPort, $adminPort] as $port) {
                $connection = fsockopen($host, (int)$port, $errorNumber, $errorMessage);

                if (is_resource($connection)) {
                    fclose($connection);

                    throw new InvalidArgumentException("Unable to start server. Port {$port} is already in use.");
                }
            }
        } finally {
            restore_error_handler();
        }
    }

    /**
     * Gets the path to the FrankenPHP configuration file.
     *
     * Falls back to the shipped Caddyfile resource when no explicit path is
     * configured.
     *
     * @return string Absolute path to the FrankenPHP configuration file.
     * @throws \InvalidArgumentException When the configuration file cannot be located.
     */
    protected function configPath(): string
    {
        $option = $this->serverOption('caddyfile');

        $path = is_string($option) && $option !== ''
            ? $option
            : $this->serverResourcePath('Caddyfile');

        $resolved = realpath($path);

        if ($resolved === false) {
            throw new InvalidArgumentException('Unable to locate specified configuration file.');
        }

        return $resolved;
    }

    /**
     * Generates the Mercure configuration snippet for the Caddyfile.
     *
     * Falls back to the directives derived from the default broadcasting
     * connection when no explicit Mercure configuration exists, so the hub
     * serves the application origin without manual Caddyfile blocks. An
     * explicit `false` value keeps the hub disabled.
     *
     * @return string Mercure Caddyfile snippet, or empty when unconfigured.
     */
    protected function buildMercureConfig(): string
    {
        $mercure = $this->stimulusConfig('mercure');

        $mercure ??= $this->mercureConfigFromBroadcasting();

        if (! $mercure) {
            return '';
        }

        return 'mercure ' . $this->buildCaddyfileBlock((array)$mercure, 2);
    }

    /**
     * Generates a Caddyfile block from the given directives.
     *
     * Nested arrays render as sub-blocks, `true` renders a bare directive,
     * and `false` skips the directive. The loop stays imperative because the
     * reference implementation builds the snippet imperatively.
     *
     * @param array<string, mixed> $directives Directives mapped to their values.
     * @param int $depth Nesting depth driving the indentation.
     * @return string Rendered Caddyfile block.
     */
    protected function buildCaddyfileBlock(array $directives, int $depth): string
    {
        $indent = str_repeat("\t", $depth + 1);

        $config = '{';

        foreach ($directives as $key => $value) {
            if ($value === false) {
                continue;
            }

            $config .= "\n{$indent}" . match (true) {
                $value === true => (string)$key,
                is_array($value) => $key . ' ' . $this->buildCaddyfileBlock($value, $depth + 1),
                default => $key . ' ' . $value,
            };
        }

        return $config . "\n" . str_repeat("\t", $depth) . '}';
    }

    /**
     * Generates the Mercure hub directives matching the default broadcasting connection.
     *
     * Mirrors the claims the broadcaster signs its tokens with, so browsers
     * subscribing on the application origin authenticate against the hub
     * without manual configuration. Non-HMAC algorithms cannot be expressed
     * as shared secrets and disable the derivation with a warning.
     *
     * @return array<string, mixed> Hub directives, or empty when derivation is unavailable.
     */
    protected function mercureConfigFromBroadcasting(): array
    {
        $connection = $this->frankenPhpMercureConnection();

        if ($connection === null) {
            return [];
        }

        $keys = $this->mercureKeys($connection);

        foreach (array_filter($keys) as [, $algorithm]) {
            if (! str_starts_with((string)$algorithm, 'HS')) {
                $this->warn(
                    "Unable to configure the FrankenPHP Mercure hub for the [{$algorithm}] algorithm. "
                    . 'Please configure it using the [Stimulus.mercure] configuration option.',
                );

                return [];
            }
        }

        $claims = $connection['claims'] ?? [];

        $claims = is_array($claims) ? $claims : [];

        $appUrl = Configure::read('App.fullBaseUrl');

        $appUrl = is_string($appUrl) ? $appUrl : null;

        $origin = $this->origin($appUrl);

        $url = $connection['url'] ?? null
            ?: ($connection['public_url'] ?? null ?: ($origin ? $origin . '/.well-known/mercure' : '/.well-known/mercure'));

        $aud = $claims['aud'] ?? null;

        $aud = is_array($aud) ? ($aud[0] ?? null) : $aud;

        $audience = $aud ?: ($connection['public_url'] ?? null ?: $url);

        $issuer = $claims['iss'] ?? null ?: ($appUrl ?: $url);

        return [
            'issuer ' . $issuer => [
                'publisher' => $keys['publisher']
                    ? ['jwt' => '{env.STIMULUS_MERCURE_PUBLISHER_JWT_KEY} ' . $keys['publisher'][1]]
                    : false,
                'subscriber' => ['jwt' => '{env.STIMULUS_MERCURE_SUBSCRIBER_JWT_KEY} ' . $keys['subscriber'][1]],
            ],
            'resource_identifier' => $this->origin(is_string($audience) ? $audience : null) ? $audience : false,
            'subscriptions' => true,
            'publish_origins' => $this->mercureClientEvents($connection) && $origin ? $origin : false,
            'cookie_name' => $connection['cookie_name'] ?? null ?: false,
        ];
    }

    /**
     * Gets the keys verifying the tokens of the FrankenPHP Mercure hub.
     *
     * Explicit Mercure configuration takes precedence and carries its own
     * secrets, so no environment variables are derived in that case.
     *
     * @return array<string, string> Environment variables carrying the hub secrets.
     */
    protected function mercureEnvironmentVariables(): array
    {
        if ($this->stimulusConfig('mercure') !== null) {
            return [];
        }

        $connection = $this->frankenPhpMercureConnection();

        if ($connection === null) {
            return [];
        }

        $keys = $this->mercureKeys($connection);

        return [
            'STIMULUS_MERCURE_PUBLISHER_JWT_KEY' => $keys['publisher'][0] ?? '',
            'STIMULUS_MERCURE_SUBSCRIBER_JWT_KEY' => $keys['subscriber'][0],
        ];
    }

    /**
     * Gets the secret and algorithm of the publisher and subscriber tokens of the given connection.
     *
     * Browsers publish client events with their subscriber token, so the
     * publisher secret follows the subscriber secret unless updates are
     * published over HTTP.
     *
     * @param array<string, mixed> $connection Broadcasting connection configuration.
     * @return array{publisher: array{0: string, 1: string}|null, subscriber: array{0: string, 1: string}} Token secrets paired with their algorithms.
     */
    protected function mercureKeys(array $connection): array
    {
        $key = fn(string $side): array => [
            (string)($connection[$side . '_secret'] ?? null ?: ($connection['secret'] ?? '')),
            $connection[$side . '_algorithm'] ?? $connection['algorithm'] ?? 'HS256',
        ];

        return [
            'publisher' => match (true) {
                ! empty($connection['url']) => $key('publish'),
                $this->mercureClientEvents($connection) => $key('subscribe'),
                default => null,
            },
            'subscriber' => $key('subscribe'),
        ];
    }

    /**
     * Determines whether the given connection allows clients to whisper.
     *
     * @param array<string, mixed> $connection Broadcasting connection configuration.
     * @return bool Whether client events stay enabled.
     */
    protected function mercureClientEvents(array $connection): bool
    {
        return (bool)($connection['client_events'] ?? true);
    }

    /**
     * Gets the default broadcasting connection when browsers subscribe to the built-in Mercure hub.
     *
     * Connections name their broadcaster through `className`, so the marker
     * alone recognises a Mercure connection. A hub served from another origin
     * is a remote one and stays unmanaged.
     *
     * @return array<string, mixed>|null Broadcasting connection configuration, or null when unmanaged.
     */
    protected function frankenPhpMercureConnection(): ?array
    {
        $connection = Configure::read('Broadcasting.connections.' . Configure::read('Broadcasting.default'));

        if (! is_array($connection)) {
            return null;
        }

        if (! $this->isMercureBroadcaster($connection['className'] ?? null)) {
            return null;
        }

        $origin = $this->origin($connection['public_url'] ?? null ?: ($connection['url'] ?? null));

        $appUrl = Configure::read('App.fullBaseUrl');

        if ($origin && $origin !== $this->origin(is_string($appUrl) ? $appUrl : null)) {
            return null;
        }

        return $connection;
    }

    /**
     * Determines whether the given broadcaster class name belongs to a Mercure broadcaster.
     *
     * Connection entries name their broadcaster through `className` as a full
     * class or a plugin-style `Vendor/Plugin.Name` reference, so the name
     * alone recognises the broadcaster without loading the sibling plugin.
     *
     * @param mixed $className Broadcaster class name from the connection configuration.
     * @return bool Whether the class name belongs to a Mercure broadcaster.
     */
    protected function isMercureBroadcaster(mixed $className): bool
    {
        return is_string($className) && str_contains(strtolower($className), 'mercure');
    }

    /**
     * Gets the origin of the given absolute URL.
     *
     * @param string|null $url Absolute URL to reduce to its origin.
     * @return string|null Scheme, host, and port of the URL, or null when relative.
     */
    protected function origin(?string $url): ?string
    {
        $parts = parse_url((string)$url);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        return $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
    }

    /**
     * Starts the watcher stand-in for the server.
     *
     * FrankenPHP watches natively through the Caddyfile directives, so the
     * no-op stand-in is always returned.
     *
     * @return object No-op watcher stand-in.
     */
    protected function startServerWatcher(): object
    {
        return new class {
            /**
             * Reports the native watcher as stopped.
             *
             * @return bool False because FrankenPHP watches natively.
             */
            public function isRunning(): bool
            {
                return false;
            }

            /**
             * Reports no watcher output for the native watcher.
             *
             * @return string Empty watcher output.
             */
            public function getIncrementalOutput(): string
            {
                return '';
            }

            /**
             * Reports the native watcher as alive.
             *
             * @return bool False because FrankenPHP watches natively.
             */
            public function isTerminated(): bool
            {
                return false;
            }

            /**
             * Reports no watcher error output for the native watcher.
             *
             * @return string Empty watcher error output.
             */
            public function getErrorOutput(): string
            {
                return '';
            }
        };
    }

    /**
     * Generates the file watcher configuration snippet for the Caddyfile.
     *
     * Falls back to the native watcher directive when no paths are
     * configured, and maps configured paths through a collection pipeline.
     *
     * @return string Watcher Caddyfile snippet, or empty when watching is disabled.
     */
    protected function buildWatchConfig(): string
    {
        if (! $this->serverOption('watch')) {
            return '';
        }

        $paths = $this->stimulusConfig('watch', []);

        if (empty($paths)) {
            return "\t\twatch";
        }

        return (new Collection((array)$paths))
            ->map(
                fn($path): string => "\t\twatch "
                    . $this->applicationBasePath()
                    . DIRECTORY_SEPARATOR
                    . $path,
            )
            ->implode("\n");
    }

    /**
     * Gets the maximum seconds workers may execute a single request.
     *
     * @return int Maximum execution time in seconds.
     */
    protected function maxExecutionTime(): int
    {
        return (int)$this->stimulusConfig('max_execution_time', 30);
    }

    /**
     * Writes the FrankenPHP server state file.
     *
     * @param \Crustum\Stimulus\Bridge\FrankenPhp\ServerStateFile $serverStateFile State file tracking the server.
     * @return void
     */
    protected function writeServerStateFile(FrankenPhpServerStateFile $serverStateFile): void
    {
        $serverStateFile->writeState([
            'appName' => $this->stimulusConfig('app.name', 'Stimulus'),
            'host' => $this->getHost(),
            'port' => $this->getPort(),
            'adminHost' => $this->serverOption('admin-host'),
            'adminPort' => $this->adminPort(),
            'workers' => $this->workerCount(),
            'maxRequests' => $this->serverOption('max-requests'),
            'stimulusConfig' => Configure::read('Stimulus', []),
        ]);
    }

    /**
     * Gets the port the admin URL should be available on.
     *
     * Derives the port from the HTTP port when the option is missing.
     *
     * @return int Admin port for the FrankenPHP admin endpoint.
     * @throws \InvalidArgumentException When the admin port cannot be derived.
     */
    protected function adminPort(): int
    {
        $option = $this->serverOption('admin-port');

        if (!in_array($option, [null, false, ''], true)) {
            return (int)$option;
        }

        $defaultPort = 2019;

        $adminPort = $defaultPort + (int)$this->getPort() - 8000;

        if ($adminPort < 0) {
            throw new InvalidArgumentException(
                'Unable to determine admin port. Please specify the [--admin-port] option.',
            );
        }

        return $adminPort;
    }

    /**
     * Gets the number of workers that should be started.
     *
     * @return string|int Worker count option, with `auto` mapped to zero workers.
     */
    protected function workerCount(): string|int
    {
        $workers = $this->serverOption('workers') ?? $this->stimulusConfig('workers', 'auto');

        return $workers === 'auto' ? 0 : $workers;
    }

    /**
     * Writes the server process output to the console.
     *
     * Decodes the Caddy JSON logs as a collection pipeline over the error
     * output lines.
     *
     * @param \Symfony\Component\Process\Process $server Running FrankenPHP server process.
     * @return void
     */
    protected function writeServerOutput(Process $server): void
    {
        [, $errorOutput] = $this->getServerOutput($server);

        $lines = (new Collection(explode("\n", $errorOutput)))
            ->filter()
            ->values();

        $logLevel = $this->serverOption('log-level');

        if (!in_array($logLevel, [null, false, ''], true)) {
            $lines->each(fn($output) => $this->raw((string)$output));

            return;
        }

        $lines->each(function ($output): void {
            $debug = json_decode((string)$output, true);

            if (! is_array($debug)) {
                $this->info((string)$output);

                return;
            }

            $message = $debug['msg'] ?? 'unknown error';

            $stream = json_decode((string)$message, true);

            if (is_array($stream)) {
                $this->handleStream($stream);

                return;
            }

            if ($message == 'handled request') {
                if (($_ENV['APP_ENV'] ?? 'production') !== 'local') {
                    return;
                }

                [
                    'duration' => $duration,
                    'request' => [
                        'method' => $method,
                        'uri' => $url,
                    ],
                    'status' => $statusCode,
                ] = $debug;

                if (str_starts_with((string)$url, '/.well-known/mercure')) {
                    return;
                }

                $this->requestInfo([
                    'method' => $method,
                    'url' => $url,
                    'statusCode' => $statusCode,
                    'duration' => (float)$duration * 1000,
                ]);

                return;
            }

            if (isset($debug['level'])) {
                if ($debug['level'] === 'warn') {
                    $this->warn((string)$message);

                    return;
                }

                if ($debug['level'] !== 'info') {
                    if (isset($debug['exit_status']) && $debug['exit_status'] === 255) {
                        return;
                    }

                    $this->error((string)$message);
                }
            }
        });
    }

    /**
     * Writes the server process output to the console.
     *
     * Suppresses the running message when an explicit log level takes over
     * the output.
     *
     * @return void
     */
    protected function writeServerRunningMessage(): void
    {
        $logLevel = $this->serverOption('log-level');

        if (in_array($logLevel, [null, false, ''], true)) {
            $this->info('Server running…');

            $scheme = $this->serverOption('https') ? 'https://' : 'http://';

            $this->io->out([
                '',
                '  Local: <info>' . $scheme . $this->getHost() . ':' . $this->getPort() . '</info> ',
                '',
                '  <warning>Press Ctrl+C to stop the server</warning>',
                '',
            ]);
        }
    }

    /**
     * Stops the server.
     *
     * @return void
     */
    protected function stopServer(): void
    {
        $this->executeSilently(new StopCommand(), ['--server', 'frankenphp']);
    }

    /**
     * Gets the FrankenPHP server process inspector.
     *
     * @return \Crustum\Stimulus\Contracts\ServerProcessInspector Inspector for the FrankenPHP server.
     */
    protected function serverInspector(): ServerProcessInspectorContract
    {
        if (!$this->inspector instanceof ServerProcessInspectorContract) {
            $this->inspector = new FrankenPhpServerProcessInspector(
                $this->serverStateFile(),
            );
        }

        return $this->inspector;
    }

    /**
     * Gets the FrankenPHP server state file.
     *
     * @return \Crustum\Stimulus\Bridge\FrankenPhp\ServerStateFile State file for the FrankenPHP server.
     */
    protected function serverStateFile(): FrankenPhpServerStateFile
    {
        if (!$this->serverStateFile instanceof FrankenPhpServerStateFile) {
            $this->serverStateFile = $this->defaultFrankenPhpStateFile();
        }

        return $this->serverStateFile;
    }
}
