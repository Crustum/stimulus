<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Cache;

use Closure;

/**
 * In-memory array cache store for worker sandboxes.
 *
 * Lean backend over the shared Stimulus store base. Each sandbox receives a
 * fresh instance so cached values never leak between requests, with a
 * flush-between-requests lifecycle. Interval values resolve eagerly
 * instead of deferring refreshes to worker ticks, adding only the interval
 * methods to the base array behavior.
 */
class StimulusArrayStore extends AbstractStimulusStore
{
    /**
     * Stored entries keyed by cache key.
     *
     * Each entry holds a `value` and an `expiration` Unix timestamp or null
     * when the entry never expires.
     *
     * @var array<string, array{value: mixed, expiration: int|null}>
     */
    protected array $storage = [];

    /**
     * Registers a cache key that should be refreshed at a given interval.
     *
     * The array store resolves the value immediately instead of deferring
     * refreshes to worker ticks.
     *
     * @param string $key Cache key.
     * @param \Closure $resolver Callback resolving the interval value.
     * @param int $seconds Refresh interval in seconds.
     * @return mixed Resolved interval value.
     */
    public function interval(string $key, Closure $resolver, int $seconds): mixed
    {
        return $resolver();
    }

    /**
     * Refreshes all of the applicable interval caches.
     *
     * The array store resolves interval values eagerly, so there is nothing
     * to refresh on worker ticks.
     *
     * @return void
     */
    public function refreshIntervalCaches(): void
    {
    }

    /**
     * Reads the raw storage record behind the given key.
     *
     * @param string $key Cache key.
     * @return array{value: mixed, expiration: int|null}|null Storage record or null when missing.
     */
    protected function readRecord(string $key): ?array
    {
        return $this->storage[$key] ?? null;
    }

    /**
     * Writes the raw storage record behind the given key.
     *
     * @param string $key Cache key.
     * @param array{value: mixed, expiration: int|null} $record Storage record.
     * @return void
     */
    protected function writeRecord(string $key, array $record): void
    {
        $this->storage[$key] = $record;
    }

    /**
     * Deletes the storage record behind the given key.
     *
     * @param string $key Cache key.
     * @return void
     */
    protected function deleteRecord(string $key): void
    {
        unset($this->storage[$key]);
    }

    /**
     * Deletes every storage record held by the backend.
     *
     * @return void
     */
    protected function flushRecords(): void
    {
        $this->storage = [];
    }
}
