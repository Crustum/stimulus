<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Cache\Table;

use Closure;
use Crustum\Stimulus\Exception\ValueTooLargeForColumnException;

/**
 * Ensures Swoole table string values fit their declared column sizes.
 */
trait EnsuresColumnSizesTrait
{
    /**
     * Table columns keyed by column name holding type and size pairs.
     *
     * @var array<string, array{0: int, 1: int}>
     */
    protected array $columns = [];

    /**
     * Builds the callback ensuring a column value fits its declared size.
     *
     * The tables run the callback through a collection pipeline on every
     * write. Late static binding resolves the string type constant on the
     * concrete table, so the Swoole and OpenSwoole implementations share
     * this check.
     *
     * @return \Closure(mixed, string|int): void Callback validating one column value.
     */
    protected function ensureColumnsSize(): Closure
    {
        return function (mixed $value, string|int $column): void {
            if (!array_key_exists($column, $this->columns)) {
                return;
            }

            [$type, $size] = $this->columns[$column];

            if ($type == static::TYPE_STRING && strlen((string)$value) > $size) {
                throw new ValueTooLargeForColumnException(sprintf(
                    'Value [%s...] is too large for [%s] column.',
                    substr((string)$value, 0, 20),
                    $column,
                ));
            }
        };
    }
}
