<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Cache\Table;

use RuntimeException;

/**
 * Creates Swoole tables for the loaded extension.
 *
 * Follows the composer-less-safe pattern of requiring the table files on demand instead
 * of relying on the autoloader, so server entries bootstrapped without
 * Composer still resolve the implementation matching the loaded extension.
 * The factory raises before touching the files on hosts without the
 * extension, so nothing fatals where Swoole is absent.
 */
class TableFactory
{
    /**
     * Creates a new Swoole table with the given size.
     *
     * Picks the OpenSwoole implementation when the OpenSwoole extension is
     * loaded and the Swoole implementation otherwise.
     *
     * @param int $size Maximum number of rows held by the table.
     * @return object Swoole or OpenSwoole table instance.
     * @throws \RuntimeException When neither the Swoole nor the OpenSwoole extension is loaded.
     */
    public static function make(int $size): object
    {
        static::ensureExtensionIsLoaded();

        static::ensureDependenciesAreLoaded();

        return extension_loaded('openswoole')
            ? new OpenSwooleTable($size, 1)
            : new SwooleTable($size, 1);
    }

    /**
     * Ensures a Swoole extension is loaded before touching table files.
     *
     * Guards the file requires below: requiring either table file without
     * its extension would fatal on the missing Swoole parent, so hosts
     * without the extension receive a controlled exception instead.
     *
     * @return void
     * @throws \RuntimeException When neither the Swoole nor the OpenSwoole extension is loaded.
     */
    protected static function ensureExtensionIsLoaded(): void
    {
        if (!extension_loaded('swoole') && !extension_loaded('openswoole')) {
            throw new RuntimeException('Swoole tables require the Swoole or OpenSwoole extension.');
        }
    }

    /**
     * Ensures the table dependencies are loaded.
     *
     * Because those tables may be required without Composer, the trait and
     * the matching implementation file are required on demand.
     *
     * @return void
     */
    protected static function ensureDependenciesAreLoaded(): void
    {
        require_once __DIR__ . '/EnsuresColumnSizesTrait.php';

        if (extension_loaded('openswoole')) {
            require_once __DIR__ . '/OpenSwooleTable.php';

            return;
        }

        require_once __DIR__ . '/SwooleTable.php';
    }
}
