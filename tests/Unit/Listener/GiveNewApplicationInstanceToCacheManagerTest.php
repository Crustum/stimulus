<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Listener;

use Cake\Cache\Cache;
use Cake\Cache\Engine\ArrayEngine;
use Cake\Http\ServerRequest;
use Crustum\Stimulus\Event\RequestReceivedEvent;
use Crustum\Stimulus\Listener\GiveNewApplicationInstanceToCacheManager;
use Crustum\Stimulus\Test\TestCase;
use stdClass;

/**
 * Tests for the cache manager refreshing listener.
 *
 * Covers the cache manager state scenario: resolved engines are forgotten
 * while their configuration survives for the rebuild.
 */
class GiveNewApplicationInstanceToCacheManagerTest extends TestCase
{
    /**
     * Resolved engines are forgotten while their configuration survives.
     *
     * @return void
     */
    public function testResolvedEnginesAreForgotten(): void
    {
        Cache::setConfig('stimulus_test_cache', ['className' => ArrayEngine::class]);
        Cache::write('key', 'value', 'stimulus_test_cache');

        $before = Cache::pool('stimulus_test_cache');

        try {
            $event = new RequestReceivedEvent(new stdClass(), new stdClass(), new ServerRequest(['url' => '/']));

            (new GiveNewApplicationInstanceToCacheManager(['stimulus_test_cache']))->handleRequestReceived($event);

            $this->assertContains('stimulus_test_cache', Cache::configured());
            $this->assertNotSame($before, Cache::pool('stimulus_test_cache'));
            $this->assertNull(Cache::read('key', 'stimulus_test_cache'));
        } finally {
            Cache::drop('stimulus_test_cache');
        }
    }

    /**
     * Missing pools are skipped without touching the configured ones.
     *
     * @return void
     */
    public function testMissingPoolsAreSkipped(): void
    {
        Cache::setConfig('stimulus_test_cache_kept', ['className' => ArrayEngine::class]);

        try {
            $event = new RequestReceivedEvent(new stdClass(), new stdClass(), new ServerRequest(['url' => '/']));

            (new GiveNewApplicationInstanceToCacheManager(['stimulus_test_missing']))->handleRequestReceived($event);

            $this->assertContains('stimulus_test_cache_kept', Cache::configured());
        } finally {
            Cache::drop('stimulus_test_cache_kept');
        }
    }

    /**
     * Partial configurations are left untouched instead of destroyed.
     *
     * Entries without a rebuildable adapter survive the operation exactly
     * as found, so one refresh never kills a pool for the rest of the
     * worker process.
     *
     * @return void
     */
    public function testPartialConfigsAreLeftUntouched(): void
    {
        Cache::setConfig('stimulus_test_cache_partial', ['prefix' => 'partial_']);

        try {
            $event = new RequestReceivedEvent(new stdClass(), new stdClass(), new ServerRequest(['url' => '/']));

            (new GiveNewApplicationInstanceToCacheManager(['stimulus_test_cache_partial']))->handleRequestReceived($event);

            $this->assertContains('stimulus_test_cache_partial', Cache::configured());
            $this->assertSame(['prefix' => 'partial_'], Cache::getConfig('stimulus_test_cache_partial'));
        } finally {
            Cache::drop('stimulus_test_cache_partial');
        }
    }
}
