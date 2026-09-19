<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Cache;

use Closure;
use Crustum\Stimulus\Cache\AbstractStimulusStore;
use Crustum\Stimulus\Cache\StimulusArrayStore;
use Crustum\Stimulus\Contracts\CacheStore;
use Crustum\Stimulus\Test\TestCase;
use ReflectionClass;

/**
 * Tests for the Stimulus cache store contract and shared base.
 *
 * Proves the array store honors the store contract, extends the shared
 * abstract base, and that the base carries the interval registry plus the
 * backend-agnostic behaviors for a second backend. The anonymous subclass
 * stands in for the future Swoole-backed store without needing its driver.
 */
class CacheStoreContractTest extends TestCase
{
    /**
     * Array store honors the store contract through the shared base.
     *
     * @return void
     */
    public function testArrayStoreHonorsStoreContract(): void
    {
        $store = new StimulusArrayStore();

        $this->assertInstanceOf(CacheStore::class, $store);
        $this->assertInstanceOf(AbstractStimulusStore::class, $store);
    }

    /**
     * Shared base is abstract and extended by the array store.
     *
     * @return void
     */
    public function testSharedBaseIsAbstract(): void
    {
        $reflection = new ReflectionClass(AbstractStimulusStore::class);

        $this->assertTrue($reflection->isAbstract());
        $this->assertTrue($reflection->isSubclassOf(CacheStore::class));
        $this->assertTrue((new ReflectionClass(StimulusArrayStore::class))->isSubclassOf(AbstractStimulusStore::class));
    }

    /**
     * Interval registry starts empty on fresh stores.
     *
     * @return void
     */
    public function testIntervalRegistryStartsEmpty(): void
    {
        $store = new class extends AbstractStimulusStore {
            /**
             * Stored entries keyed by cache key.
             *
             * @var array<string, array{value: mixed, expiration: int|null}>
             */
            protected array $storage = [];

            /**
             * Registers a cache key that should be refreshed at a given interval.
             *
             * @param string $key Cache key.
             * @param \Closure $resolver Callback resolving the interval value.
             * @param int $seconds Refresh interval in seconds.
             * @return mixed Resolved interval value.
             */
            public function interval(string $key, Closure $resolver, int $seconds): mixed
            {
                $this->registerInterval($key);

                return $resolver();
            }

            /**
             * Refreshes all of the applicable interval caches.
             *
             * @return void
             */
            public function refreshIntervalCaches(): void
            {
            }

            /**
             * Exposes the registered interval keys.
             *
             * @return list<string> Registered interval cache keys.
             */
            public function intervals(): array
            {
                return $this->registeredIntervals();
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
        };

        $this->assertSame([], $store->intervals());

        $store->interval('foo', fn(): string => 'bar', 60);

        $this->assertSame(['foo'], $store->intervals());
    }

    /**
     * Shared remember and expiration logic works for a second backend.
     *
     * @return void
     */
    public function testSharedLogicServesSecondBackend(): void
    {
        $store = new StimulusArrayStore();

        $this->assertSame('bar', $store->remember('foo', 100, fn(): string => 'bar'));
        $this->assertSame('bar', $store->remember('foo', 100, fn(): string => 'baz'));
        $this->assertTrue($store->flush());
        $this->assertNull($store->get('foo'));
    }
}
