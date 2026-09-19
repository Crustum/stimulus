<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Listener;

use Cake\Http\ServerRequest;
use Crustum\Broadcasting\Broadcaster\NullBroadcaster;
use Crustum\Broadcasting\Broadcasting;
use Crustum\Stimulus\Event\RequestReceivedEvent;
use Crustum\Stimulus\Listener\GiveNewApplicationInstanceToBroadcastManager;
use Crustum\Stimulus\Test\TestCase;
use stdClass;

/**
 * Tests for the broadcast manager refreshing listener.
 *
 * Covers the broadcast manager state scenario: resolved broadcasters are
 * forgotten while their configuration survives for the rebuild.
 */
class GiveNewApplicationInstanceToBroadcastManagerTest extends TestCase
{
    /**
     * Resolved broadcasters are forgotten while their configuration survives.
     *
     * @return void
     */
    public function testResolvedBroadcastersAreForgotten(): void
    {
        Broadcasting::setConfig('stimulus_test_broadcast', ['className' => NullBroadcaster::class]);
        Broadcasting::get('stimulus_test_broadcast');

        $this->assertTrue(Broadcasting::getRegistry()->has('stimulus_test_broadcast'));

        try {
            $event = new RequestReceivedEvent(new stdClass(), new stdClass(), new ServerRequest(['url' => '/']));

            (new GiveNewApplicationInstanceToBroadcastManager())->handleRequestReceived($event);

            $this->assertFalse(Broadcasting::getRegistry()->has('stimulus_test_broadcast'));
            $this->assertContains('stimulus_test_broadcast', Broadcasting::configured());
            $this->assertInstanceOf(
                NullBroadcaster::class,
                Broadcasting::get('stimulus_test_broadcast'),
            );
        } finally {
            Broadcasting::drop('stimulus_test_broadcast');
        }
    }
}
