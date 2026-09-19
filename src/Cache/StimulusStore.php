<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Cache;

use Cake\Log\Log;
use Closure;
use Laravel\SerializableClosure\SerializableClosure;
use Override;
use RuntimeException;
use Throwable;

/**
 * Swoole-table-backed cache store for Stimulus workers.
 */
class StimulusStore extends AbstractStimulusStore
{
    /**
     * Creates a new Swoole-table-backed cache store.
     *
     * Values serialize on write, so the store opts into the
     * base serialization behavior.
     *
     * @param mixed $table Swoole table instance created through `TableFactory`.
     * @throws \RuntimeException When neither the Swoole nor the OpenSwoole extension is loaded.
     */
    public function __construct(protected mixed $table)
    {
        if (!extension_loaded('swoole') && !extension_loaded('openswoole')) {
            throw new RuntimeException('The stimulus cache store requires the Swoole or OpenSwoole extension.');
        }

        parent::__construct(true);
    }

    /**
     * Retrieves an item from the cache by key.
     *
     * Resolves pending interval values when the stored record is missing or expired.
     *
     * @param string $key Cache key.
     * @param mixed $default Default value returned when the entry is missing or expired.
     * @return mixed Cached value, resolved interval value, or the given default.
     */
    #[Override]
    public function get(string $key, mixed $default = null): mixed
    {
        $record = $this->readRecord($key);

        if (!$this->recordIsFalseOrExpired($record)) {
            return $this->readValue($record);
        }

        $interval = in_array($key, $this->registeredIntervals(), true)
            ? $this->getInterval($key)
            : null;

        if ($interval !== null) {
            $resolver = $interval['resolver'];

            return $resolver();
        }

        return $default;
    }

    /**
     * Registers a cache key that should be refreshed at a given interval.
     *
     * The first registration persists the resolver record
     * and later registrations only join the refresh registry, while reads
     * resolve pending values through `get`.
     *
     * @param string $key Cache key.
     * @param \Closure $resolver Callback resolving the interval value.
     * @param int $seconds Refresh interval in seconds.
     * @return mixed Always null.
     */
    public function interval(string $key, Closure $resolver, int $seconds): mixed
    {
        if ($this->getInterval($key) !== null) {
            $this->registerInterval($key);

            return null;
        }

        $this->forever('interval-' . $key, [
            'resolver' => new SerializableClosure($resolver),
            'lastRefreshedAt' => null,
            'refreshInterval' => $seconds,
        ]);

        $this->registerInterval($key);

        return null;
    }

    /**
     * Refreshes all of the applicable interval caches.
     *
     * Iterates imperatively over the registered intervals instead of collecting them. Failures
     * report through the log.
     *
     * @return void
     */
    public function refreshIntervalCaches(): void
    {
        foreach ($this->registeredIntervals() as $key) {
            $interval = $this->getInterval($key);
            if ($interval === null) {
                continue;
            }

            if (!$this->intervalShouldBeRefreshed($interval)) {
                continue;
            }

            try {
                $this->forever('interval-' . $key, array_merge(
                    $interval,
                    ['lastRefreshedAt' => time()],
                ));

                $resolver = $interval['resolver'];

                $this->forever($key, $resolver());
            } catch (Throwable $throwable) {
                Log::error($throwable::class . ': ' . $throwable->getMessage(), ['code' => $throwable->getCode()]);
            }
        }
    }

    /**
     * Sets the expiration of a cached item.
     *
     * Refreshes only the expiration column of a live record through a partial row update.
     *
     * @param string $key Cache key.
     * @param int $seconds Seconds until the entry expires.
     * @return bool Whether the expiration was updated.
     */
    public function touch(string $key, int $seconds): bool
    {
        if ($this->recordIsFalseOrExpired($this->readRecord($key))) {
            return false;
        }

        if (!is_object($this->table) || !method_exists($this->table, 'set')) {
            return false;
        }

        return $this->table->set($key, [
            'expiration' => time() + $seconds,
        ]);
    }

    /**
     * Reads the raw storage record behind the given key.
     *
     * Normalizes the Swoole `false` miss into null for the shared base.
     *
     * @param string $key Cache key.
     * @return array{value: mixed, expiration: int|null}|null Storage record or null when missing.
     */
    protected function readRecord(string $key): ?array
    {
        if (!is_object($this->table) || !method_exists($this->table, 'get')) {
            return null;
        }

        $record = $this->table->get($key);

        if ($record === false || !is_array($record)) {
            return null;
        }

        return [
            'value' => $record['value'] ?? null,
            'expiration' => isset($record['expiration']) ? (int)$record['expiration'] : null,
        ];
    }

    /**
     * Writes the raw storage record behind the given key.
     *
     * Null expirations coerce to the one-year horizon because the Swoole
     * integer column cannot store null.
     *
     * @param string $key Cache key.
     * @param array{value: mixed, expiration: int|null} $record Storage record.
     * @return void
     */
    protected function writeRecord(string $key, array $record): void
    {
        if (!is_object($this->table) || !method_exists($this->table, 'set')) {
            return;
        }

        $this->table->set($key, [
            'value' => $record['value'],
            'expiration' => $record['expiration'] ?? time() + static::ONE_YEAR,
        ]);
    }

    /**
     * Deletes the storage record behind the given key.
     *
     * @param string $key Cache key.
     * @return void
     */
    protected function deleteRecord(string $key): void
    {
        if (!is_object($this->table) || !method_exists($this->table, 'del')) {
            return;
        }

        $this->table->del($key);
    }

    /**
     * Deletes every storage record held by the backend.
     *
     * Iterates imperatively over the table rows instead of collecting them, and keeps
     * `interval-` resolver rows so registered intervals survive flushes.
     *
     * @return void
     */
    protected function flushRecords(): void
    {
        $table = $this->table;

        if (!is_iterable($table)) {
            return;
        }

        foreach ($table as $key => $record) {
            if (str_starts_with((string)$key, 'interval-')) {
                continue;
            }

            $this->deleteRecord((string)$key);
        }
    }

    /**
     * Retrieves an interval record from the cache.
     *
     * Values unserialize through the base reader with no explicit unserialize
     * call. Malformed rows read as missing so a
     * single corrupt record cannot break the refresh pass.
     *
     * @param string $key Cache key behind the interval.
     * @return array{resolver: callable, lastRefreshedAt: int|null, refreshInterval: int}|null Interval record or null when missing.
     */
    protected function getInterval(string $key): ?array
    {
        $interval = $this->get('interval-' . $key);

        if (!is_array($interval)) {
            return null;
        }

        $resolver = $interval['resolver'] ?? null;
        $refreshInterval = $interval['refreshInterval'] ?? null;
        $lastRefreshedAt = $interval['lastRefreshedAt'] ?? null;

        if (!is_callable($resolver) || !is_int($refreshInterval)) {
            return null;
        }

        if ($lastRefreshedAt !== null && !is_int($lastRefreshedAt)) {
            return null;
        }

        return [
            'resolver' => $resolver,
            'lastRefreshedAt' => $lastRefreshedAt,
            'refreshInterval' => $refreshInterval,
        ];
    }

    /**
     * Determines if the given interval record should be refreshed.
     *
     * @param array{resolver: callable, lastRefreshedAt: int|null, refreshInterval: int} $interval Interval record.
     * @return bool Whether the interval is due for a refresh.
     */
    protected function intervalShouldBeRefreshed(array $interval): bool
    {
        return $interval['lastRefreshedAt'] === null ||
            (time() - $interval['lastRefreshedAt']) >= $interval['refreshInterval'];
    }
}
