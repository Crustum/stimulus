<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Listener;

use Cake\Cache\Cache;
use Cake\Cache\Engine\ArrayEngine;
use Cake\Http\ServerRequest;
use Cake\Queue\QueueManager;
use Crustum\Stimulus\Event\RequestReceivedEvent;
use Crustum\Stimulus\Listener\GiveNewApplicationInstanceToQueueManager;
use Crustum\Stimulus\Test\TestCase;
use stdClass;

/**
 * Tests for the queue manager refreshing listener.
 *
 * Covers the queue manager state scenario: resolved clients are forgotten
 * while their configuration survives for the rebuild.
 */
class GiveNewApplicationInstanceToQueueManagerTest extends TestCase
{
    /**
     * Resolved clients are forgotten while their configuration survives.
     *
     * @return void
     */
    public function testResolvedClientsAreForgotten(): void
    {
        QueueManager::setConfig('stimulus_test_queue', ['url' => 'null:']);

        $before = QueueManager::engine('stimulus_test_queue');

        try {
            $event = new RequestReceivedEvent(new stdClass(), new stdClass(), new ServerRequest(['url' => '/']));

            (new GiveNewApplicationInstanceToQueueManager(['stimulus_test_queue']))->handleRequestReceived($event);

            $this->assertContains('stimulus_test_queue', QueueManager::configured());
            $this->assertNotSame($before, QueueManager::engine('stimulus_test_queue'));
        } finally {
            QueueManager::drop('stimulus_test_queue');
        }
    }

    /**
     * Missing queues are skipped without touching the configured ones.
     *
     * @return void
     */
    public function testMissingQueuesAreSkipped(): void
    {
        QueueManager::setConfig('stimulus_test_queue_kept', ['url' => 'null:']);

        try {
            $event = new RequestReceivedEvent(new stdClass(), new stdClass(), new ServerRequest(['url' => '/']));

            (new GiveNewApplicationInstanceToQueueManager(['stimulus_test_missing']))->handleRequestReceived($event);

            $this->assertContains('stimulus_test_queue_kept', QueueManager::configured());
        } finally {
            QueueManager::drop('stimulus_test_queue_kept');
        }
    }

    /**
     * Unique-job cache pools survive the queue refresh.
     *
     * The queue re-registration recreates its cache pool as a side effect,
     * so the pool dropped ahead of it must exist again afterwards alongside
     * the queue itself.
     *
     * @return void
     */
    public function testUniqueCachePoolSurvivesQueueRefresh(): void
    {
        QueueManager::setConfig('stimulus_test_queue_cache', [
            'url' => 'null:',
            'uniqueCache' => ['className' => ArrayEngine::class],
        ]);

        $pool = 'Cake/Queue.queueUnique.stimulus_test_queue_cache';

        try {
            $event = new RequestReceivedEvent(new stdClass(), new stdClass(), new ServerRequest(['url' => '/']));

            (new GiveNewApplicationInstanceToQueueManager(['stimulus_test_queue_cache']))->handleRequestReceived($event);

            $this->assertContains('stimulus_test_queue_cache', QueueManager::configured());
            $this->assertContains($pool, Cache::configured());
        } finally {
            QueueManager::drop('stimulus_test_queue_cache');
            Cache::drop($pool);
        }
    }
}
