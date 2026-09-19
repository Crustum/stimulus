<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Cache;

use Crustum\Stimulus\Cache\StimulusStore;
use Crustum\Stimulus\Cache\Table\TableFactory;
use Crustum\Stimulus\Test\TestCase;
use OpenSwoole\Table as OpenSwooleNativeTable;
use Swoole\Table;

/**
 * Tests for the Swoole-table-backed cache store.
 *
 * Covers retrieval, misses, expiry, pending intervals, multi-gets, writes,
 * increments, forever entries, interval refreshes, touches, forgetting,
 * flushing, and interval survival. The store runs over a real Swoole table;
 * every test skips cleanly where the extension is absent.
 */
class StimulusStoreTest extends TestCase
{
    /**
     * Requires the Swoole extension before each test.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        if (!extension_loaded('swoole') && !extension_loaded('openswoole')) {
            $this->markTestSkipped('The Swoole or OpenSwoole extension is not installed on this host.');
        }
    }

    /**
     * Stored entries return until they expire.
     *
     * @return void
     */
    public function testCanRetrieveItemsFromStore(): void
    {
        $table = $this->createSwooleTable();

        $table->set('foo', ['value' => serialize('bar'), 'expiration' => time() + 100]);

        $store = new StimulusStore($table);

        $this->assertSame('bar', $store->get('foo'));
    }

    /**
     * Missing entries resolve to the default.
     *
     * @return void
     */
    public function testMissingItemsReturnDefault(): void
    {
        $store = new StimulusStore($this->createSwooleTable());

        $this->assertNull($store->get('foo'));
        $this->assertSame('fallback', $store->get('foo', 'fallback'));
    }

    /**
     * Expired entries resolve to the default.
     *
     * @return void
     */
    public function testExpiredItemsReturnDefault(): void
    {
        $table = $this->createSwooleTable();

        $store = new StimulusStore($table);

        $table->set('foo', ['value' => serialize('bar'), 'expiration' => time() - 100]);

        $this->assertNull($store->get('foo'));
    }

    /**
     * Pending intervals resolve through the get method.
     *
     * @return void
     */
    public function testGetMethodCanResolvePendingInterval(): void
    {
        $store = new StimulusStore($this->createSwooleTable());

        $store->interval('foo', static fn(): string => 'bar', 1);

        $this->assertSame('bar', $store->get('foo'));
    }

    /**
     * Multiple entries retrieve keyed by their given keys.
     *
     * @return void
     */
    public function testManyMethodCanReturnManyValues(): void
    {
        $table = $this->createSwooleTable();

        $table->set('foo', ['value' => serialize('bar'), 'expiration' => time() + 100]);
        $table->set('bar', ['value' => serialize('baz'), 'expiration' => time() + 100]);

        $store = new StimulusStore($table);

        $this->assertSame(['foo' => 'bar', 'bar' => 'baz'], $store->many(['foo', 'bar']));
    }

    /**
     * Puts store through the table.
     *
     * @return void
     */
    public function testPutStoresValueInTable(): void
    {
        $store = new StimulusStore($this->createSwooleTable());

        $store->put('foo', 'bar', 5);

        $this->assertSame('bar', $store->get('foo'));
    }

    /**
     * Put-many stores through the table.
     *
     * @return void
     */
    public function testPutManyStoresValuesInTable(): void
    {
        $store = new StimulusStore($this->createSwooleTable());

        $store->putMany(['foo' => 'bar', 'bar' => 'baz'], 5);

        $this->assertSame('bar', $store->get('foo'));
        $this->assertSame('baz', $store->get('bar'));
    }

    /**
     * Counters increment and decrement in place.
     *
     * @return void
     */
    public function testIncrementAndDecrementOperations(): void
    {
        $store = new StimulusStore($this->createSwooleTable());

        $store->increment('counter');
        $this->assertSame(1, $store->get('counter'));

        $store->increment('counter', 2);
        $this->assertSame(3, $store->get('counter'));

        $store->decrement('counter', 2);
        $this->assertSame(1, $store->get('counter'));
    }

    /**
     * Forever entries persist indefinitely.
     *
     * @return void
     */
    public function testForeverStoresValueInTable(): void
    {
        $store = new StimulusStore($this->createSwooleTable());

        $store->forever('foo', 'bar');

        $this->assertSame('bar', $store->get('foo'));
    }

    /**
     * Interval caches refresh through worker ticks.
     *
     * @return void
     */
    public function testIntervalsCanBeRefreshed(): void
    {
        $store = new StimulusStore($this->createSwooleTable());

        $store->interval('foo', static fn(): string => uniqid('stimulus-', true), 0);

        $store->refreshIntervalCaches();

        $this->assertIsString($first = $store->get('foo'));

        $store->refreshIntervalCaches();

        $this->assertIsString($second = $store->get('foo'));
        $this->assertNotSame($first, $second);
    }

    /**
     * Touches extend the expiration.
     *
     * @return void
     */
    public function testTouchUpdatesExpiration(): void
    {
        $table = $this->createSwooleTable();

        $store = new StimulusStore($table);

        $store->put('foo', 'bar', 100);

        $this->assertTrue($store->touch('foo', 100));
        $this->assertSame('bar', $store->get('foo'));
    }

    /**
     * Touches fail for missing entries.
     *
     * @return void
     */
    public function testTouchReturnsFalseForMissingItems(): void
    {
        $store = new StimulusStore($this->createSwooleTable());

        $this->assertFalse($store->touch('foo', 10));
    }

    /**
     * Touches fail for expired entries.
     *
     * @return void
     */
    public function testTouchReturnsFalseForExpiredItems(): void
    {
        $table = $this->createSwooleTable();

        $store = new StimulusStore($table);

        $table->set('foo', ['value' => serialize('bar'), 'expiration' => time() - 100]);

        $this->assertFalse($store->touch('foo', 10));
    }

    /**
     * Forgetting and flushing drop entries.
     *
     * @return void
     */
    public function testCanForgetCacheItems(): void
    {
        $store = new StimulusStore($this->createSwooleTable());

        $store->put('foo', 'bar', 5);
        $this->assertTrue($store->forget('foo'));

        $this->assertNull($store->get('foo'));

        $store->put('foo', 'bar', 5);
        $this->assertTrue($store->flush());

        $this->assertNull($store->get('foo'));
    }

    /**
     * Flushes keep interval resolvers alive.
     *
     * @return void
     */
    public function testIntervalsAreNotFlushed(): void
    {
        $store = new StimulusStore($this->createSwooleTable());

        $store->interval('foo', static fn(): string => 'bar', 1);
        $this->assertTrue($store->flush());

        $this->assertSame('bar', $store->get('foo'));
    }

    /**
     * Remember helpers resolve through the table.
     *
     * @return void
     */
    public function testRememberHelpers(): void
    {
        $store = new StimulusStore($this->createSwooleTable());

        $this->assertSame('bar', $store->remember('foo', 100, static fn(): string => 'bar'));
        $this->assertSame('bar', $store->remember('foo', 100, static fn(): string => 'baz'));
        $this->assertSame('baz', $store->rememberForever('forever', static fn(): string => 'baz'));
    }

    /**
     * Builds a Swoole table backing the store.
     *
     * Column type constants come from the loaded runtime so the factory
     * table works on OpenSwoole-only hosts too.
     *
     * @return object Swoole table with cache columns.
     */
    protected function createSwooleTable(): object
    {
        $table = TableFactory::make(1000);

        $nativeTableClass = $this->nativeTableClass();

        $table->column('value', $nativeTableClass::TYPE_STRING, 10000);
        $table->column('expiration', $nativeTableClass::TYPE_INT);

        $table->create();

        return $table;
    }

    /**
     * Resolves the native table class of the loaded runtime.
     *
     * Prefers Swoole and falls back to OpenSwoole, so column constants
     * always belong to the backing table.
     *
     * @return class-string Native table class name.
     */
    protected function nativeTableClass(): string
    {
        if (class_exists(Table::class)) {
            return Table::class;
        }

        return OpenSwooleNativeTable::class;
    }
}
