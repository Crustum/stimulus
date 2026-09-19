<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Command\Trait;

use Cake\Collection\Collection;
use Cake\Http\Client;
use Cake\Log\Log;
use Crustum\Stimulus\Bridge\FrankenPhp\FindsFrankenPhpBinaryTrait;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Installs the FrankenPHP server dependencies.
 *
 * Confirmations go through the base command confirmation helper,
 * the GitHub API and binary downloads go through the HTTP client,
 * and binary locations resolve from the application base path.
 *
 * @mixin \Crustum\Stimulus\Command\StimulusCommand
 */
trait InstallsFrankenPhpDependenciesTrait
{
    use FindsFrankenPhpBinaryTrait;

    /**
     * The minimum required version of the FrankenPHP binary.
     *
     * @var string
     */
    protected string $requiredFrankenPhpVersion = '1.5.0';

    /**
     * Ensures the FrankenPHP worker script is installed.
     *
     * Copies the shipped worker stub into the public path when the host has
     * not published its own copy yet.
     *
     * @return void
     */
    public function ensureFrankenPhpWorkerIsInstalled(): void
    {
        $target = $this->publicPath() . DIRECTORY_SEPARATOR . 'frankenphp-worker.php';

        if (! file_exists($target)) {
            copy($this->serverResourcePath('frankenphp-worker.php'), $target);
        }
    }

    /**
     * Ensures the FrankenPHP binary is installed into the project.
     *
     * Returns the located binary, or the expected project path after offering
     * the download.
     *
     * @return string Absolute path to the FrankenPHP binary.
     */
    protected function ensureFrankenPhpBinaryIsInstalled(): string
    {
        $frankenphpBinary = $this->findFrankenPhpBinary();

        if ($frankenphpBinary !== null) {
            return $frankenphpBinary;
        }

        if (
            $this->confirmAction(
                'Unable to locate FrankenPHP binary. Should Stimulus download the binary for your operating system?',
                true,
            )
        ) {
            $this->downloadFrankenPhpBinary();
        }

        return $this->applicationBasePath() . DIRECTORY_SEPARATOR . 'frankenphp';
    }

    /**
     * Downloads the latest version of the FrankenPHP binary.
     *
     * Resolves the platform asset name, reads the latest release through the
     * GitHub API with the HTTP client, then streams the matching asset
     * into the project. The download runs without progress output.
     *
     * @return string Absolute path to the downloaded binary.
     * @throws \RuntimeException When the platform is unsupported, the release lookup fails, or the asset is missing.
     */
    protected function downloadFrankenPhpBinary(): string
    {
        $arch = php_uname('m');

        $assetName = match (PHP_OS_FAMILY) {
            'Linux' => (function () use ($arch): string {
                $process = new Process(['getconf', 'GNU_LIBC_VERSION']);

                $process->run();

                $gnu = $process->isSuccessful() ? '-gnu' : '';

                return match ($arch) {
                    'x86_64' => "frankenphp-linux-x86_64{$gnu}",
                    'aarch64' => "frankenphp-linux-aarch64{$gnu}",
                    default => throw new RuntimeException(
                        'FrankenPHP binaries are only available for x86_64 and aarch64 architectures. ' .
                        'For other architectures, compile FrankenPHP manually.',
                    ),
                };
            })(),
            'Darwin' => "frankenphp-mac-{$arch}",
            default => throw new RuntimeException(
                'FrankenPHP binaries are only available for Linux and macOS. ' .
                'On Windows, use WSL or Docker. On other systems use the Docker images or compile FrankenPHP manually.',
            ),
        };

        $githubProxyPrefix = $_ENV['STIMULUS_GITHUB_PROXY_PREFIX'] ?? '';

        $response = (new Client())->get(
            $githubProxyPrefix . 'https://api.github.com/repos/php/frankenphp/releases/latest',
            [],
            [
                'headers' => [
                    'Accept' => 'application/vnd.github+json',
                    'X-GitHub-Api-Version' => '2022-11-28',
                ],
            ],
        );

        if (! $response->isOk()) {
            $this->error('Failed to download FrankenPHP.');

            throw new RuntimeException('Failed to download FrankenPHP.');
        }

        $body = $response->getJson();

        $assets = is_array($body) ? $body['assets'] ?? [] : [];

        foreach ((array)$assets as $asset) {
            if (($asset['name'] ?? null) !== $assetName) {
                continue;
            }

            $path = $this->applicationBasePath() . DIRECTORY_SEPARATOR . 'frankenphp';

            $download = (new Client())->get(
                $githubProxyPrefix . $asset['browser_download_url'],
            );

            if (! $download->isOk()) {
                throw new RuntimeException('Failed to download FrankenPHP.');
            }

            file_put_contents($path, (string)$download->getBody());

            chmod($path, 0755);

            $this->io->out('');

            return $path;
        }

        throw new RuntimeException('FrankenPHP asset not found.');
    }

    /**
     * Ensures the installed FrankenPHP binary meets the requirements.
     *
     * Reads the binary build info and compares the FrankenPHP version against
     * the required one through a collection pipeline over the version lines,
     * offering a re-download when outdated.
     *
     * @param string $frankenPhpBinary Absolute path to the FrankenPHP binary.
     * @return void
     */
    protected function ensureFrankenPhpBinaryMeetsRequirements(string $frankenPhpBinary): void
    {
        $process = new Process([$frankenPhpBinary, 'build-info'], $this->applicationBasePath());

        $process->run();

        $buildInfo = $process->getOutput();

        $lineWithVersion = (new Collection(explode("\n", $buildInfo)))
            ->filter(
                fn($line): bool => str_starts_with((string)$line, 'dep')
                    && str_contains((string)$line, 'github.com/dunglas/frankenphp'),
            )
            ->first();

        if (! is_string($lineWithVersion)) {
            $this->warn('Unable to determine the current FrankenPHP binary version.');

            return;
        }

        $trimmed = trim($lineWithVersion);

        $position = strrpos($trimmed, 'v');

        $version = $position === false ? '' : substr($trimmed, $position + 1);

        if (preg_match('/\d+\.\d+\.\d+/', $version) !== 1) {
            $this->warn('Unable to determine the current FrankenPHP binary version.');

            return;
        }

        if (version_compare($version, $this->requiredFrankenPhpVersion, '>=')) {
            return;
        }

        $this->warn("Your FrankenPHP binary version (<error>{$version}</error>) may be incompatible with Stimulus.");

        if (
            $this->confirmAction(
                'Should Stimulus download the latest FrankenPHP binary version for your operating system?',
                true,
            )
        ) {
            rename($frankenPhpBinary, "{$frankenPhpBinary}.backup");

            try {
                $this->downloadFrankenPhpBinary();
            } catch (Throwable $e) {
                Log::error($e->getMessage());

                rename("{$frankenPhpBinary}.backup", $frankenPhpBinary);

                $this->warn('Unable to download FrankenPHP binary. The underlying error has been logged.');

                return;
            }

            unlink("{$frankenPhpBinary}.backup");
        }
    }
}
