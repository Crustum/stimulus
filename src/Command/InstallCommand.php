<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Command;

use Cake\Collection\Collection;
use Cake\Console\ConsoleOptionParser;
use Crustum\PluginManifest\Command\ManifestInstallCommand;
use Crustum\Stimulus\Bridge\Swoole\SwooleExtension;
use Crustum\Stimulus\Command\Trait\InstallsFrankenPhpDependenciesTrait;
use Crustum\Stimulus\Command\Trait\InstallsRoadRunnerDependenciesTrait;
use Override;
use Throwable;

/**
 * Installs the Stimulus components and resources.
 *
 * The server choice resolves from the `--server` option or an interactive
 * choice, dependencies install per driver, the server name is recorded in
 * the env file, and the plugin configuration publishes through the
 * PluginManifest manifest installer instead of duplicating the manifest
 * logic. Both installer traits locate the FrankenPHP and RoadRunner binaries
 * through the same base path lookup, so the duplicate resolution wins
 * deterministically.
 */
class InstallCommand extends StimulusCommand
{
    use InstallsFrankenPhpDependenciesTrait;
    use InstallsRoadRunnerDependenciesTrait {
        InstallsFrankenPhpDependenciesTrait::applicationBasePath insteadof InstallsRoadRunnerDependenciesTrait;
    }

    /**
     * Gets the command name.
     *
     * @return string The command name.
     */
    #[Override]
    public static function defaultName(): string
    {
        return 'stimulus install';
    }

    /**
     * Gets the command description.
     *
     * @return string The command description.
     */
    #[Override]
    public static function getDescription(): string
    {
        return 'Install the Stimulus components and resources';
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
            ->addOption('server', [
                'help' => 'The server that should be used to serve the application',
            ])
            ->addOption('force', [
                'help' => 'Overwrite any existing configuration files',
                'boolean' => true,
            ]);
    }

    /**
     * Executes the command.
     *
     * Installs the selected server dependencies, records the server in the
     * env file, and publishes the plugin configuration. The side-effect chain
     * runs as plain statements.
     *
     * @return int|null The exit code or null for success.
     */
    public function execute(): ?int
    {
        $server = $this->args->getOption('server');
        if (!is_string($server) || $server === '') {
            $server = $this->io->askChoice(
                'Which application server you would like to use?',
                ['frankenphp', 'roadrunner', 'swoole'],
                'frankenphp',
            );
        }

        $installed = match ($server) {
            'swoole' => $this->installSwooleServer(),
            'roadrunner' => $this->installRoadRunnerServer(),
            'frankenphp' => $this->installFrankenPhpServer(),
            default => $this->invalidServer($server),
        };
        if ($installed) {
            $this->updateEnvironmentFile($server);

            $this->publishConfiguration((bool)$this->args->getOption('force'));

            $this->io->success('Stimulus installed successfully.');

            $this->io->out('');
        }

        return $installed ? self::CODE_SUCCESS : self::CODE_ERROR;
    }

    /**
     * Updates the environment file with the given server.
     *
     * Appends the server variable when missing and rewrites it when present.
     * The variable is named `STIMULUS_SERVER` for the plugin.
     *
     * @param string $server Server name to record in the env file.
     * @param string|null $envFile Env file path, or null for the configured path.
     * @return void
     */
    public function updateEnvironmentFile(string $server, ?string $envFile = null): void
    {
        $env = $envFile ?? $this->environmentFilePath();

        if (! file_exists($env)) {
            return;
        }

        $contents = (string)file_get_contents($env);

        if (!str_contains($contents, 'STIMULUS_SERVER=')) {
            file_put_contents($env, PHP_EOL . 'STIMULUS_SERVER=' . $server . PHP_EOL, FILE_APPEND);
        } else {
            file_put_contents(
                $env,
                (string)preg_replace(
                    '/^STIMULUS_SERVER=.*/m',
                    'STIMULUS_SERVER=' . $server,
                    $contents,
                ),
            );
        }
    }

    /**
     * Publishes the plugin configuration through the manifest installer.
     *
     * Delegates to the PluginManifest manifest installer for the plugin
     * configuration tag, so the manifest stays the single publisher of the
     * plugin assets.
     *
     * @param bool $force Whether existing configuration files are overwritten.
     * @return int|null Exit code of the manifest installation.
     */
    protected function publishConfiguration(bool $force): ?int
    {
        $args = [
            '--plugin',
            'Crustum/Stimulus',
            '--tag',
            'config',
            '--no_dependencies',
        ];

        if ($force) {
            $args[] = '--force';
        }

        return $this->executeCommand(ManifestInstallCommand::class, $args);
    }

    /**
     * Installs the RoadRunner dependencies.
     *
     * Extends the git ignore file through a collection pipeline appending
     * each file, then ensures the binary is installed.
     *
     * @return bool Whether the RoadRunner server was installed.
     */
    public function installRoadRunnerServer(): bool
    {
        if (!$this->ensureRoadRunnerPackageIsInstalled()) {
            return false;
        }

        $gitIgnorePath = $this->applicationBasePath() . DIRECTORY_SEPARATOR . '.gitignore';

        if (file_exists($gitIgnorePath)) {
            (new Collection(['rr', '.rr.yaml']))
                ->each(function (string $file) use ($gitIgnorePath): void {
                    $contents = (string)file_get_contents($gitIgnorePath);

                    if (!str_contains($contents, $file . PHP_EOL)) {
                        file_put_contents($gitIgnorePath, $file . PHP_EOL, FILE_APPEND);
                    }
                });
        }

        $this->ensureRoadRunnerBinaryIsInstalled();

        return true;
    }

    /**
     * Installs the Swoole dependencies.
     *
     * Warns when the Swoole extension is missing and always succeeds because
     * the extension installs outside Composer.
     *
     * @return bool Whether the Swoole server was installed.
     */
    public function installSwooleServer(): bool
    {
        if (!(new SwooleExtension())->isInstalled()) {
            $this->warn('The Swoole extension is missing.');
        }

        return true;
    }

    /**
     * Installs the FrankenPHP server.
     *
     * Extends the git ignore file through a collection pipeline filtering and
     * joining the missing entries, ensures the worker script is published,
     * and downloads the binary, reporting download failures as errors.
     *
     * @return bool Whether the FrankenPHP server was installed.
     */
    public function installFrankenPhpServer(): bool
    {
        $gitIgnorePath = $this->applicationBasePath() . DIRECTORY_SEPARATOR . '.gitignore';

        if (file_exists($gitIgnorePath)) {
            $contents = (string)file_get_contents($gitIgnorePath);

            $filesToAppend = (new Collection(['**/caddy', 'frankenphp', 'frankenphp-worker.php']))
                ->filter(fn(string $file): bool => ! str_contains($contents, $file . PHP_EOL))
                ->implode(PHP_EOL);

            if ($filesToAppend !== '') {
                file_put_contents($gitIgnorePath, PHP_EOL . $filesToAppend . PHP_EOL, FILE_APPEND);
            }
        }

        $this->ensureFrankenPhpWorkerIsInstalled();

        try {
            $this->ensureFrankenPhpBinaryIsInstalled();
        } catch (Throwable $throwable) {
            $this->error($throwable->getMessage());

            return false;
        }

        return true;
    }

    /**
     * Informs the user that the server type is invalid.
     *
     * @param string $server Invalid server name given by the user.
     * @return bool False so the install reports a failure.
     */
    protected function invalidServer(string $server): bool
    {
        $this->error("Invalid server: {$server}.");

        return false;
    }
}
