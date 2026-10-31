<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Command\Trait;

use Cake\Log\Log;
use Crustum\Stimulus\Bridge\RoadRunner\FindsRoadRunnerBinaryTrait;
use RuntimeException;
use Spiral\RoadRunner\Http\PSR7Worker;
use Symfony\Component\Process\Exception\ProcessSignaledException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Installs the RoadRunner server dependencies.
 *
 * Confirmations go through the base command confirmation helper,
 * binary locations resolve from the application base path, and the shipped
 * `rr.yaml` stub is copied from the plugin server resources.
 *
 * @mixin \Crustum\Stimulus\Command\StimulusCommand
 */
trait InstallsRoadRunnerDependenciesTrait
{
    use FindsRoadRunnerBinaryTrait;

    /**
     * The minimum required version of the RoadRunner binary.
     *
     * @var string
     */
    protected string $requiredRoadRunnerVersion = '2023.3.0';

    /**
     * The first RoadRunner binary version using semantic versioning.
     *
     * RoadRunner moved from calendar versioning to semantic versioning with
     * `3.0.0`, so older-looking versions compare against this floor instead.
     *
     * @var string
     */
    protected string $firstSemanticRoadRunnerVersion = '3.0.0';

    /**
     * Determines if RoadRunner is installed.
     *
     * The Spiral worker class lives in the host dependencies, so the check
     * stays a class probe that is safe when the package is absent.
     *
     * @return bool Whether the RoadRunner HTTP worker class is available.
     */
    protected function isRoadRunnerInstalled(): bool
    {
        return class_exists(PSR7Worker::class);
    }

    /**
     * Ensures the RoadRunner package is installed into the project.
     *
     * Offers the Composer installation, streaming the Composer output through
     * the console.
     *
     * @return bool Whether the RoadRunner package is available.
     */
    protected function ensureRoadRunnerPackageIsInstalled(): bool
    {
        if ($this->isRoadRunnerInstalled()) {
            return true;
        }

        if (
            ! $this->confirmAction(
                'Stimulus requires "spiral/roadrunner-http:^4.1" and "spiral/roadrunner-cli". ' .
                'Do you wish to install them as dependencies?',
                false,
            )
        ) {
            $this->error('Stimulus requires "spiral/roadrunner-http" and "spiral/roadrunner-cli".');

            return false;
        }

        $command = $this->findComposer()
            . ' require spiral/roadrunner-http:^4.1 spiral/roadrunner-cli --with-all-dependencies';

        $process = Process::fromShellCommandline($command, null, null, null, null);

        if (DIRECTORY_SEPARATOR !== '\\' && file_exists('/dev/tty') && is_readable('/dev/tty')) {
            try {
                $process->setTty(true);
            } catch (RuntimeException $e) {
                $this->io->out('Warning: ' . $e->getMessage());
            }
        }

        try {
            $process->run(function ($type, $line): void {
                $this->io->out($line);
            });
        } catch (ProcessSignaledException $processSignaledException) {
            if (extension_loaded('pcntl') && $processSignaledException->getSignal() !== SIGINT) {
                throw $processSignaledException;
            }
        }

        if (!$process->isSuccessful()) {
            $this->error('Stimulus could not install "spiral/roadrunner-http" and "spiral/roadrunner-cli".');

            return false;
        }

        return true;
    }

    /**
     * Gets the composer command for the environment.
     *
     * A local `composer.phar` runs through the current PHP binary. A
     * discovered executable ending in `.bat` or `.cmd` is a Windows shell
     * proxy and runs directly without a PHP prefix, which would otherwise
     * feed the proxy script to the PHP interpreter instead of executing it.
     *
     * @return string Composer invocation for the current PHP binary.
     */
    protected function findComposer(): string
    {
        $composerPath = getcwd() . '/composer.phar';

        $phpPath = (new PhpExecutableFinder())->find();

        if (!file_exists($composerPath)) {
            $composerPath = (string)(new ExecutableFinder())->find('composer');
        }

        $lowered = strtolower($composerPath);

        if (str_ends_with($lowered, '.bat') || str_ends_with($lowered, '.cmd')) {
            return '"' . $composerPath . '"';
        }

        return '"' . $phpPath . '" "' . $composerPath . '"';
    }

    /**
     * Ensures the RoadRunner binary is installed into the project.
     *
     * Returns the located binary, publishes the shipped stub configuration
     * after offering the download, and falls back to the expected project
     * path.
     *
     * @return string Absolute path to the RoadRunner binary.
     */
    protected function ensureRoadRunnerBinaryIsInstalled(): string
    {
        $roadRunnerBinary = $this->findRoadRunnerBinary();

        if ($roadRunnerBinary !== null) {
            return $roadRunnerBinary;
        }

        if (
            $this->confirmAction(
                'Unable to locate RoadRunner binary. Should Stimulus download the binary for your operating system?',
                true,
            )
        ) {
            $this->downloadRoadRunnerBinary();

            copy(
                $this->serverResourcePath('rr.yaml'),
                $this->applicationBasePath() . DIRECTORY_SEPARATOR . '.rr.yaml',
            );
        }

        return $this->applicationBasePath() . DIRECTORY_SEPARATOR . 'rr';
    }

    /**
     * Ensures the RoadRunner binary meets the requirements.
     *
     * Compares the reported binary version against the required one and
     * offers a re-download when outdated. Accepts the Windows executable name
     * in the version banner alongside the Unix one.
     *
     * @param string $roadRunnerBinary Absolute path to the RoadRunner binary.
     * @return void
     */
    protected function ensureRoadRunnerBinaryMeetsRequirements(string $roadRunnerBinary): void
    {
        $version = $this->roadRunnerBinaryVersion($roadRunnerBinary);

        if ($version === null) {
            $this->warn('Unable to determine the current RoadRunner binary version.');

            return;
        }

        if ($this->roadRunnerBinaryVersionIsSupported($version)) {
            return;
        }

        $this->warn(sprintf('Your RoadRunner binary version (<error>%s</error>) may be incompatible with Stimulus.', $version));

        if (
            $this->confirmAction(
                'Should Stimulus download the latest RoadRunner binary version for your operating system?',
                true,
            )
        ) {
            rename($roadRunnerBinary, $roadRunnerBinary . '.backup');

            try {
                $this->downloadRoadRunnerBinary();
            } catch (Throwable $e) {
                Log::error($e->getMessage());

                rename($roadRunnerBinary . '.backup', $roadRunnerBinary);

                $this->warn('Unable to download RoadRunner binary. The HTTP request exception has been logged.');

                return;
            }

            unlink($roadRunnerBinary . '.backup');
        }
    }

    /**
     * Reads the version reported by the given RoadRunner binary.
     *
     * @param string $roadRunnerBinary Absolute path to the RoadRunner binary.
     * @return string|null Binary version from the banner, or null when unreadable.
     */
    protected function roadRunnerBinaryVersion(string $roadRunnerBinary): ?string
    {
        $process = new Process([$roadRunnerBinary, '--version'], $this->applicationBasePath());

        $process->run();

        $banner = $process->getOutput();

        if (! str_starts_with($banner, 'rr version') && ! str_starts_with($banner, 'rr.exe version')) {
            return null;
        }

        return explode(' ', $banner)[2] ?? null;
    }

    /**
     * Determines whether the given RoadRunner binary version is supported.
     *
     * RoadRunner used calendar versioning from `2023.1.0` through `2025.x`
     * and moved to semantic versioning with `3.0.0`, so short versions
     * compare against the semantic floor instead of the calendar one.
     *
     * @param string $version Binary version from the version banner.
     * @return bool Whether the binary version is supported.
     */
    protected function roadRunnerBinaryVersionIsSupported(string $version): bool
    {
        if ((int)$version < 2000) {
            return version_compare($version, $this->firstSemanticRoadRunnerVersion, '>=');
        }

        return version_compare($version, $this->requiredRoadRunnerVersion, '>=');
    }

    /**
     * Downloads the latest version of the RoadRunner binary.
     *
     * Streams the `rr get-binary` output through the console, then marks the
     * downloaded binary executable.
     *
     * @return void
     */
    protected function downloadRoadRunnerBinary(): void
    {
        $installed = false;

        $process = new Process(
            array_filter([
                (new PhpExecutableFinder())->find(),
                './vendor/bin/rr',
                'get-binary',
                '-n',
                '--ansi',
            ]),
            $this->applicationBasePath(),
            null,
            null,
            null,
        );

        $process->mustRun(function (string $type, string $buffer) use (&$installed): void {
            if (! $installed) {
                $this->io->out($buffer);

                $installed = str_contains($buffer, 'has been installed into');
            }
        });

        chmod($this->applicationBasePath() . DIRECTORY_SEPARATOR . 'rr', 0755);

        $this->io->out('');
    }
}
