<?php
declare(strict_types=1);

namespace OpenSwoole;

use Swoole\Table as SwooleTable;

/**
 * Analysis stub for the OpenSwoole shared-memory table.
 *
 * This declaration exists solely for PHPStan (wired through `stubFiles` in
 * `phpstan.neon`) so the ext-gated Swoole bridge analyzes without the
 * extensions installed. It is never autoloaded at runtime: Composer maps
 * only `Crustum\Stimulus\` to `src/`, and the `TableFactory` throws before
 * touching table files on hosts without the extension. Only symbols missing
 * from PHPStan's bundled stubs are declared here. Inherits the Swoole table
 * surface, mirroring the real OpenSwoole table API used by the cache
 * backend.
 */
class Table extends SwooleTable
{
}
