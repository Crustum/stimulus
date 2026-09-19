<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Listener;

use Cake\Cache\Cache;
use Cake\Cache\Engine\ArrayEngine;
use Cake\Http\ServerRequest;
use Crustum\Stimulus\Event\RequestReceivedEvent;
use Crustum\Stimulus\Listener\FlushArrayCache;
use Crustum\Stimulus\Test\TestCase;
use stdClass;

/**
 * Tests for the array cache flushing listener.
 *
 * Covers the worker cache state scenario: array-engine pools are cleared
 * while persistent pools keep their entries.
 */
class FlushArrayCacheTest extends TestCase
{
    /**
     * Array-engine pools are cleared while file pools keep their entries.
     *
     * @return void
     */
    public function testArrayCachesAreFlushed(): void
    {
        Cache::setConfig('stimulus_test_array', ['className' => ArrayEngine::class]);
        Cache::setConfig('stimulus_test_file', [
            'className' => 'File',
            'path' => CACHE,
            'prefix' => 'stimulus_test_',
        ]);

        try {
            Cache::write('key', 'array-value', 'stimulus_test_array');
            Cache::write('key', 'file-value', 'stimulus_test_file');

            $event = new RequestReceivedEvent(new stdClass(), new stdClass(), new ServerRequest(['url' => '/']));

            (new FlushArrayCache())->handleRequestReceived($event);

            $this->assertNull(Cache::read('key', 'stimulus_test_array'));
            $this->assertSame('file-value', Cache::read('key', 'stimulus_test_file'));
        } finally {
            Cache::drop('stimulus_test_array');
            Cache::drop('stimulus_test_file');
        }
    }

    /**
     * Handling without any array pool configured leaves caches untouched.
     *
     * @return void
     */
    public function testHandleWithoutArrayPoolSucceeds(): void
    {
        $event = new RequestReceivedEvent(new stdClass(), new stdClass(), new ServerRequest(['url' => '/']));

        (new FlushArrayCache())->handleRequestReceived($event);

        $this->assertNotContains('stimulus_test_array', Cache::configured());
    }

    /**
     * Stillborn pools are skipped instead of failing the operation.
     *
     * Host bootstraps may register partial entries without an adapter the
     * engine builder rejects; the flush leaves such entries exactly as
     * found while still clearing healthy array pools.
     *
     * @return void
     */
    public function testStillbornPoolsAreSkipped(): void
    {
        Cache::setConfig('stimulus_test_array_kept', ['className' => ArrayEngine::class]);
        Cache::setConfig('stimulus_test_stillborn', ['duration' => '+2 minutes']);

        try {
            Cache::write('key', 'array-value', 'stimulus_test_array_kept');

            $event = new RequestReceivedEvent(new stdClass(), new stdClass(), new ServerRequest(['url' => '/']));

            (new FlushArrayCache())->handleRequestReceived($event);

            $this->assertNull(Cache::read('key', 'stimulus_test_array_kept'));
            $this->assertContains('stimulus_test_stillborn', Cache::configured());
            $this->assertSame(['duration' => '+2 minutes'], Cache::getConfig('stimulus_test_stillborn'));
        } finally {
            Cache::drop('stimulus_test_array_kept');
            Cache::drop('stimulus_test_stillborn');
        }
    }
}
