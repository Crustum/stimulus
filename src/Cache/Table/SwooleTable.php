<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Cache\Table;

use Cake\Collection\Collection;
use Swoole\Table;

/**
 * Swoole table validating string column sizes on write.
 *
 * Lives under `Cache\Table` beside the Swoole-backed cache store instead of a top-level
 * `Table` namespace, keeping the whole Swoole-table backend next to its only
 * consumer. The class loads only on extension hosts through the
 * `TableFactory` composer-less-safe pattern, so the Swoole parent reference
 * never resolves where the extension is absent.
 */
class SwooleTable extends Table
{
    use EnsuresColumnSizesTrait;

    /**
     * Sets the data type and size of the columns.
     *
     * Records the column definition for size validation before delegating to
     * the Swoole parent.
     *
     * @param string $name Column name.
     * @param int $type Column type constant from the Swoole table.
     * @param int $size Column size in bytes for string columns.
     * @return bool Whether the column was defined.
     */
    public function column(string $name, int $type, int $size = 0): bool
    {
        $this->columns[$name] = [$type, $size];

        return parent::column($name, $type, $size);
    }

    /**
     * Updates a row of the table.
     *
     * Runs the values through a `Cake\Collection\Collection` pipeline
     * before delegating to the Swoole parent.
     *
     * @param string $key Row key.
     * @param array<string, mixed> $values Column values to store.
     * @return bool Whether the row was stored.
     */
    public function set(string $key, array $values): bool
    {
        (new Collection($values))
            ->each($this->ensureColumnsSize());

        return parent::set($key, $values);
    }
}
