<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Cache;

use Crustum\Stimulus\Cache\AbstractStimulusStore;
use Crustum\Stimulus\Cache\StimulusArrayStore;
use Crustum\Stimulus\Contracts\CacheStore;
use Crustum\Stimulus\Test\TestCase;

/**
 * Tests for the in-memory array cache store.
 *
 * Covers the store API plus the interval behavior. The worker-level cache
 * state test needs the full worker and router from a later phase, so its
 * flush-between-requests scenario is asserted here at store level instead.
 */
class StimulusArrayStoreTest extends TestCase
{
    /**
     * Stored entries are returned until they expire.
     *
     * @return void
     */
    public function testPutAndGet(): void
    {
        $store = new StimulusArrayStore();

        $this->assertTrue($store->put('foo', 'bar', 100));
        $this->assertSame('bar', $store->get('foo'));
    }

    /**
     * Missing entries resolve to the given default.
     *
     * @return void
     */
    public function testMissingEntriesReturnDefault(): void
    {
        $store = new StimulusArrayStore();

        $this->assertNull($store->get('missing'));
        $this->assertSame('fallback', $store->get('missing', 'fallback'));
    }

    /**
     * Multiple entries are retrieved keyed by their given keys.
     *
     * @return void
     */
    public function testMany(): void
    {
        $store = new StimulusArrayStore();
        $store->put('first', 1, 100);
        $store->put('second', 2, 100);

        $this->assertSame(['first' => 1, 'second' => 2, 'missing' => null], $store->many(['first', 'second', 'missing']));
    }

    /**
     * Multiple entries are stored with a shared lifetime.
     *
     * @return void
     */
    public function testPutMany(): void
    {
        $store = new StimulusArrayStore();

        $this->assertTrue($store->putMany(['first' => 1, 'second' => 2], 100));
        $this->assertSame(1, $store->get('first'));
        $this->assertSame(2, $store->get('second'));
    }

    /**
     * Numeric entries are incremented in place.
     *
     * @return void
     */
    public function testIncrement(): void
    {
        $store = new StimulusArrayStore();
        $store->put('counter', 1, 100);

        $this->assertSame(3, $store->increment('counter', 2));
        $this->assertSame(3, $store->get('counter'));
    }

    /**
     * Missing entries are seeded by the increment amount.
     *
     * @return void
     */
    public function testIncrementSeedsMissingEntries(): void
    {
        $store = new StimulusArrayStore();

        $this->assertSame(5, $store->increment('counter', 5));
        $this->assertSame(5, $store->get('counter'));
    }

    /**
     * Non-numeric entries cannot be incremented.
     *
     * @return void
     */
    public function testIncrementRejectsNonNumericEntries(): void
    {
        $store = new StimulusArrayStore();
        $store->put('name', 'taylor', 100);

        $this->assertFalse($store->increment('name'));
    }

    /**
     * Numeric entries are decremented in place.
     *
     * @return void
     */
    public function testDecrement(): void
    {
        $store = new StimulusArrayStore();
        $store->put('counter', 5, 100);

        $this->assertSame(3, $store->decrement('counter', 2));
        $this->assertSame(3, $store->get('counter'));
    }

    /**
     * Entries stored indefinitely survive retrieval.
     *
     * @return void
     */
    public function testForever(): void
    {
        $store = new StimulusArrayStore();

        $this->assertTrue($store->forever('foo', 'bar'));
        $this->assertSame('bar', $store->get('foo'));
    }

    /**
     * Non-positive lifetimes persist entries indefinitely.
     *
     * @return void
     */
    public function testNonPositiveLifetimePersistsIndefinitely(): void
    {
        $store = new StimulusArrayStore();
        $store->put('foo', 'bar', 0);

        $this->assertSame('bar', $store->get('foo'));
    }

    /**
     * Cache misses resolve through the callback and persist the result.
     *
     * @return void
     */
    public function testRemember(): void
    {
        $store = new StimulusArrayStore();
        $calls = 0;

        $value = $store->remember('foo', 100, function () use (&$calls): string {
            $calls++;

            return 'bar';
        });

        $this->assertSame('bar', $value);
        $this->assertSame('bar', $store->remember('foo', 100, fn(): string => 'baz'));
        $this->assertSame(1, $calls);
    }

    /**
     * Cache misses resolve indefinitely through the callback.
     *
     * @return void
     */
    public function testRememberForever(): void
    {
        $store = new StimulusArrayStore();

        $this->assertSame('bar', $store->rememberForever('foo', fn(): string => 'bar'));
        $this->assertSame('bar', $store->rememberForever('foo', fn(): string => 'baz'));
    }

    /**
     * Removed entries resolve to the default.
     *
     * @return void
     */
    public function testForget(): void
    {
        $store = new StimulusArrayStore();
        $store->put('foo', 'bar', 100);

        $this->assertTrue($store->forget('foo'));
        $this->assertNull($store->get('foo'));
    }

    /**
     * Flushing removes every entry from the store.
     *
     * @return void
     */
    public function testFlush(): void
    {
        $store = new StimulusArrayStore();
        $store->put('first', 1, 100);
        $store->forever('second', 2);

        $this->assertTrue($store->flush());
        $this->assertNull($store->get('first'));
        $this->assertNull($store->get('second'));
    }

    /**
     * Flushing between requests gives each request a fresh cached value.
     *
     * Store-level coverage of the worker cache state scenario, which needs
     * the full worker and router from a later phase: the first
     * request remembers a value, the sandbox flushes, and the next request
     * resolves its own value instead of reusing the first one.
     *
     * @return void
     */
    public function testArrayCacheIsFlushedBetweenRequests(): void
    {
        $store = new StimulusArrayStore();

        $first = $store->rememberForever('nitro', fn(): string => 'first');
        $store->flush();
        $second = $store->rememberForever('nitro', fn(): string => 'second');

        $this->assertSame('first', $first);
        $this->assertSame('second', $second);
    }

    /**
     * Interval keys resolve eagerly through the resolver.
     *
     * @return void
     */
    public function testIntervalResolvesImmediately(): void
    {
        $store = new StimulusArrayStore();

        $this->assertSame('bar', $store->interval('foo', fn(): string => 'bar', 60));
    }

    /**
     * Refreshing interval caches is a safe no-op for the array store.
     *
     * @return void
     */
    public function testRefreshIntervalCachesIsNoOp(): void
    {
        $store = new StimulusArrayStore();
        $store->put('foo', 'bar', 100);

        $store->refreshIntervalCaches();

        $this->assertSame('bar', $store->get('foo'));
    }

    /**
     * Serialized values round-trip through serialize and unserialize.
     *
     * @return void
     */
    public function testSerializesValues(): void
    {
        $store = new StimulusArrayStore(true);
        $store->put('foo', ['name' => 'taylor'], 100);

        $this->assertSame(['name' => 'taylor'], $store->get('foo'));
    }

    /**
     * The store honors the contract through the shared abstract base.
     *
     * @return void
     */
    public function testStoreHonorsContractThroughSharedBase(): void
    {
        $store = new StimulusArrayStore();

        $this->assertInstanceOf(CacheStore::class, $store);
        $this->assertInstanceOf(AbstractStimulusStore::class, $store);
    }

    /**
     * The store uses no key prefix.
     *
     * @return void
     */
    public function testGetPrefix(): void
    {
        $store = new StimulusArrayStore();

        $this->assertSame('', $store->getPrefix());
    }
}
