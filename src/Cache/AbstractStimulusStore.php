<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Cache;

use Cake\Collection\Collection;
use Closure;
use Crustum\Stimulus\Contracts\CacheStore;

/**
 * Shared base for Stimulus cache stores.
 *
 * Holds every backend-agnostic behavior so the array store and the future
 * Swoole-backed store extend the same logic instead of duplicating it:
 * expiration records, value serialization, remember helpers, and the
 * interval key registry. Subclasses provide only the record backend plus
 * the interval strategy, which is eager for the array store and
 * tick-driven for the Swoole-backed store, so those two methods stay
 * abstract here.
 */
abstract class AbstractStimulusStore implements CacheStore
{
    /**
     * Seconds used when an entry should persist indefinitely.
     *
     * @var int
     */
    protected const ONE_YEAR = 31536000;

    /**
     * Whether values are serialized on write and unserialized on read.
     *
     * @var bool
     */
    protected bool $serializesValues;

    /**
     * Interval cache keys registered for tick-driven refreshes.
     *
     * The array store resolves intervals eagerly and leaves the registry
     * empty; the Swoole-backed store fills it through `interval` and drains
     * it through `refreshIntervalCaches`.
     *
     * @var list<string>
     */
    protected array $intervals = [];

    /**
     * Creates a new Stimulus cache store.
     *
     * @param bool $serializesValues Whether values are serialized on write and unserialized on read.
     */
    public function __construct(bool $serializesValues = false)
    {
        $this->serializesValues = $serializesValues;
    }

    /**
     * Retrieves an item from the cache by key.
     *
     * @param string $key Cache key.
     * @param mixed $default Default value returned when the entry is missing or expired.
     * @return mixed Cached value or the given default.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $record = $this->readRecord($key);

        if ($this->recordIsFalseOrExpired($record)) {
            $this->deleteRecord($key);

            return $default;
        }

        return $this->readValue($record);
    }

    /**
     * Retrieves multiple items from the cache by key.
     *
     * Items not found in the cache will have a null value.
     *
     * Resolves each key through `get` in a collection pipeline for multi-gets.
     *
     * @param list<string> $keys Cache keys.
     * @return array<string, mixed> Cached values keyed by their given keys.
     */
    public function many(array $keys): array
    {
        return (new Collection(array_fill_keys($keys, null)))
            ->map(fn(mixed $value, string $key): mixed => $this->get($key))
            ->toArray();
    }

    /**
     * Stores an item in the cache for a given number of seconds.
     *
     * @param string $key Cache key.
     * @param mixed $value Value to store.
     * @param int $seconds Seconds until the entry expires. Non-positive values persist indefinitely.
     * @return bool Whether the entry was stored.
     */
    public function put(string $key, mixed $value, int $seconds): bool
    {
        $this->writeRecord($key, [
            'value' => $this->writeValue($value),
            'expiration' => $seconds > 0 ? time() + $seconds : null,
        ]);

        return true;
    }

    /**
     * Stores multiple items in the cache for a given number of seconds.
     *
     * Iterates imperatively over the values instead of collecting them.
     *
     * @param array<string, mixed> $values Values to store keyed by cache key.
     * @param int $seconds Seconds until the entries expire. Non-positive values persist indefinitely.
     * @return bool Whether the entries were stored.
     */
    public function putMany(array $values, int $seconds): bool
    {
        foreach ($values as $key => $value) {
            $this->put($key, $value, $seconds);
        }

        return true;
    }

    /**
     * Increments the value of an item in the cache.
     *
     * @param string $key Cache key.
     * @param int $value Amount to increment by.
     * @return int|bool New value, or false when the current value is not numeric.
     */
    public function increment(string $key, int $value = 1): int|bool
    {
        $record = $this->readRecord($key);

        if ($this->recordIsFalseOrExpired($record)) {
            $this->forever($key, $value);

            return $value;
        }

        $current = $this->readValue($record);

        if (!is_numeric($current)) {
            return false;
        }

        $newValue = (int)$current + $value;

        $this->writeRecord($key, [
            'value' => $this->writeValue($newValue),
            'expiration' => $record['expiration'],
        ]);

        return $newValue;
    }

    /**
     * Decrements the value of an item in the cache.
     *
     * @param string $key Cache key.
     * @param int $value Amount to decrement by.
     * @return int|bool New value, or false when the current value is not numeric.
     */
    public function decrement(string $key, int $value = 1): int|bool
    {
        return $this->increment($key, $value * -1);
    }

    /**
     * Stores an item in the cache indefinitely.
     *
     * @param string $key Cache key.
     * @param mixed $value Value to store.
     * @return bool Whether the entry was stored.
     */
    public function forever(string $key, mixed $value): bool
    {
        return $this->put($key, $value, static::ONE_YEAR);
    }

    /**
     * Gets an item from the cache, or stores the callback result when missing.
     *
     * @param string $key Cache key.
     * @param int $seconds Seconds until the entry expires. Non-positive values persist indefinitely.
     * @param \Closure $callback Callback resolving the value on a cache miss.
     * @return mixed Cached or newly resolved value.
     */
    public function remember(string $key, int $seconds, Closure $callback): mixed
    {
        $value = $this->get($key);

        if ($value !== null) {
            return $value;
        }

        $this->put($key, $value = $callback(), $seconds);

        return $value;
    }

    /**
     * Gets an item from the cache, or stores the callback result indefinitely when missing.
     *
     * @param string $key Cache key.
     * @param \Closure $callback Callback resolving the value on a cache miss.
     * @return mixed Cached or newly resolved value.
     */
    public function rememberForever(string $key, Closure $callback): mixed
    {
        $value = $this->get($key);

        if ($value !== null) {
            return $value;
        }

        $this->forever($key, $value = $callback());

        return $value;
    }

    /**
     * Removes an item from the cache.
     *
     * @param string $key Cache key.
     * @return bool Whether the entry was removed.
     */
    public function forget(string $key): bool
    {
        $this->deleteRecord($key);

        return true;
    }

    /**
     * Removes all items from the cache.
     *
     * @return bool Whether the cache was flushed.
     */
    public function flush(): bool
    {
        $this->flushRecords();

        return true;
    }

    /**
     * Gets the cache key prefix.
     *
     * @return string Cache key prefix.
     */
    public function getPrefix(): string
    {
        return '';
    }

    /**
     * Registers a cache key that should be refreshed at a given interval.
     *
     * The strategy differs per backend: the array store resolves the value
     * immediately while the Swoole-backed store defers refreshes to worker
     * ticks, so subclasses provide the behavior.
     *
     * @param string $key Cache key.
     * @param \Closure $resolver Callback resolving the interval value.
     * @param int $seconds Refresh interval in seconds.
     * @return mixed Resolved interval value.
     */
    abstract public function interval(string $key, Closure $resolver, int $seconds): mixed;

    /**
     * Refreshes all of the applicable interval caches.
     *
     * The strategy differs per backend, so subclasses provide the behavior.
     *
     * @return void
     */
    abstract public function refreshIntervalCaches(): void;

    /**
     * Reads the raw storage record behind the given key.
     *
     * @param string $key Cache key.
     * @return array{value: mixed, expiration: int|null}|null Storage record or null when missing.
     */
    abstract protected function readRecord(string $key): ?array;

    /**
     * Writes the raw storage record behind the given key.
     *
     * @param string $key Cache key.
     * @param array{value: mixed, expiration: int|null} $record Storage record.
     * @return void
     */
    abstract protected function writeRecord(string $key, array $record): void;

    /**
     * Deletes the storage record behind the given key.
     *
     * @param string $key Cache key.
     * @return void
     */
    abstract protected function deleteRecord(string $key): void;

    /**
     * Deletes every storage record held by the backend.
     *
     * @return void
     */
    abstract protected function flushRecords(): void;

    /**
     * Registers an interval cache key for tick-driven refreshes.
     *
     * @param string $key Cache key.
     * @return void
     */
    protected function registerInterval(string $key): void
    {
        $this->intervals[] = $key;
    }

    /**
     * Gets the interval cache keys registered for tick-driven refreshes.
     *
     * @return list<string> Registered interval cache keys.
     */
    protected function registeredIntervals(): array
    {
        return $this->intervals;
    }

    /**
     * Prepares the given value for storage, serializing it when enabled.
     *
     * @param mixed $value Value to store.
     * @return mixed Storage-ready value.
     */
    protected function writeValue(mixed $value): mixed
    {
        return $this->serializesValues ? serialize($value) : $value;
    }

    /**
     * Reads the stored value, unserializing it when serialization is enabled.
     *
     * @param array{value: mixed, expiration: int|null} $record Storage record.
     * @return mixed Stored value.
     */
    protected function readValue(array $record): mixed
    {
        return $this->serializesValues ? unserialize($record['value']) : $record['value'];
    }

    /**
     * Determines if the record is missing or expired.
     *
     * @param array{value: mixed, expiration: int|null}|null $record Storage record or null when missing.
     * @return bool Whether the record is missing or expired.
     */
    protected function recordIsFalseOrExpired(?array $record): bool
    {
        return $record === null || ($record['expiration'] !== null && $record['expiration'] <= time());
    }
}
