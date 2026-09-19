<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Contracts;

use Closure;

/**
 * Contract for Stimulus cache stores.
 *
 * The array store and the future Swoole-backed store share this shape so the worker can swap backends
 * without touching callers. Interval refresh hooks are part of the contract
 * because interval caches register through the store itself.
 */
interface CacheStore
{
    /**
     * Retrieves an item from the cache by key.
     *
     * @param string $key Cache key.
     * @param mixed $default Default value returned when the entry is missing or expired.
     * @return mixed Cached value or the given default.
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Retrieves multiple items from the cache by key.
     *
     * Items not found in the cache will have a null value.
     *
     * @param list<string> $keys Cache keys.
     * @return array<string, mixed> Cached values keyed by their given keys.
     */
    public function many(array $keys): array;

    /**
     * Stores an item in the cache for a given number of seconds.
     *
     * @param string $key Cache key.
     * @param mixed $value Value to store.
     * @param int $seconds Seconds until the entry expires. Non-positive values persist indefinitely.
     * @return bool Whether the entry was stored.
     */
    public function put(string $key, mixed $value, int $seconds): bool;

    /**
     * Stores multiple items in the cache for a given number of seconds.
     *
     * @param array<string, mixed> $values Values to store keyed by cache key.
     * @param int $seconds Seconds until the entries expire. Non-positive values persist indefinitely.
     * @return bool Whether the entries were stored.
     */
    public function putMany(array $values, int $seconds): bool;

    /**
     * Increments the value of an item in the cache.
     *
     * @param string $key Cache key.
     * @param int $value Amount to increment by.
     * @return int|bool New value, or false when the current value is not numeric.
     */
    public function increment(string $key, int $value = 1): int|bool;

    /**
     * Decrements the value of an item in the cache.
     *
     * @param string $key Cache key.
     * @param int $value Amount to decrement by.
     * @return int|bool New value, or false when the current value is not numeric.
     */
    public function decrement(string $key, int $value = 1): int|bool;

    /**
     * Stores an item in the cache indefinitely.
     *
     * @param string $key Cache key.
     * @param mixed $value Value to store.
     * @return bool Whether the entry was stored.
     */
    public function forever(string $key, mixed $value): bool;

    /**
     * Removes an item from the cache.
     *
     * @param string $key Cache key.
     * @return bool Whether the entry was removed.
     */
    public function forget(string $key): bool;

    /**
     * Removes all items from the cache.
     *
     * @return bool Whether the cache was flushed.
     */
    public function flush(): bool;

    /**
     * Gets the cache key prefix.
     *
     * @return string Cache key prefix.
     */
    public function getPrefix(): string;

    /**
     * Registers a cache key that should be refreshed at a given interval.
     *
     * @param string $key Cache key.
     * @param \Closure $resolver Callback resolving the interval value.
     * @param int $seconds Refresh interval in seconds.
     * @return mixed Resolved interval value.
     */
    public function interval(string $key, Closure $resolver, int $seconds): mixed;

    /**
     * Refreshes all of the applicable interval caches.
     *
     * @return void
     */
    public function refreshIntervalCaches(): void;
}
