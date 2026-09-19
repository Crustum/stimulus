<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Bridge\RoadRunner;

use Cake\Core\Configure;
use Symfony\Component\Process\ExecutableFinder;

/**
 * Locates the RoadRunner binary used by the application.
 *
 * The `Stimulus.basePath` configuration value lets host applications
 * point the bridge at their own checkout layout.
 */
trait FindsRoadRunnerBinaryTrait
{
    /**
     * Finds the RoadRunner binary used by the application.
     *
     * Prefers the platform executable name first so shared checkouts holding
     * both the Unix and Windows server binaries resolve the runnable one:
     * `rr.exe` on Windows, `rr` elsewhere. Falls back to the path search
     * when neither sibling file exists. The candidates iterate
     * imperatively with an early return because the lookup stops at the
     * first existing file.
     *
     * @return string|null Absolute path to the RoadRunner binary, or null when it cannot be found.
     */
    protected function findRoadRunnerBinary(): ?string
    {
        $basePath = $this->applicationBasePath();

        $candidates = PHP_OS_FAMILY === 'Windows'
            ? ['rr.exe', 'rr']
            : ['rr', 'rr.exe'];

        foreach ($candidates as $candidate) {
            if (file_exists($basePath . DIRECTORY_SEPARATOR . $candidate)) {
                return $basePath . DIRECTORY_SEPARATOR . $candidate;
            }
        }

        $roadRunnerBinary = (new ExecutableFinder())->find('rr', null, [$basePath]);

        if (is_string($roadRunnerBinary) && ! str_contains($this->normalizePath($roadRunnerBinary), 'vendor/bin/rr')) {
            return $roadRunnerBinary;
        }

        return null;
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
     * Normalizes directory separators so path comparisons match on Windows.
     *
     * @param string $path Filesystem path to normalize.
     * @return string Path using forward slashes as separators.
     */
    protected function normalizePath(string $path): string
    {
        return str_replace('\\', '/', $path);
    }
}
