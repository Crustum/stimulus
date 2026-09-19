<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Bridge\FrankenPhp;

use Cake\Core\Configure;
use Symfony\Component\Process\ExecutableFinder;

/**
 * Locates the FrankenPHP binary used by the application.
 *
 * The `Stimulus.basePath` configuration value lets host applications
 * point the bridge at their own checkout layout.
 */
trait FindsFrankenPhpBinaryTrait
{
    /**
     * Finds the FrankenPHP binary used by the application.
     *
     * @return string|null Absolute path to the FrankenPHP binary, or null when it cannot be found.
     */
    protected function findFrankenPhpBinary(): ?string
    {
        $binary = (new ExecutableFinder())->find('frankenphp', null, [$this->applicationBasePath()]);

        return is_string($binary) ? $binary : null;
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
}
