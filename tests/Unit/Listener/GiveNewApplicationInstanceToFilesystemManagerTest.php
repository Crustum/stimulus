<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Listener;

use Cake\Http\ServerRequest;
use Crustum\Stimulus\Event\RequestReceivedEvent;
use Crustum\Stimulus\Listener\GiveNewApplicationInstanceToFilesystemManager;
use Crustum\Stimulus\Test\TestCase;
use stdClass;

/**
 * Tests for the filesystem manager refreshing listener.
 *
 * The listener keeps its pipeline shape without resetting any state and the test only proves
 * the operation passes through untouched.
 */
class GiveNewApplicationInstanceToFilesystemManagerTest extends TestCase
{
    /**
     * Received operations pass through with their instances intact.
     *
     * @return void
     */
    public function testReceivedOperationPassesThrough(): void
    {
        $request = new ServerRequest(['url' => '/']);
        $event = new RequestReceivedEvent(new stdClass(), new stdClass(), $request);

        (new GiveNewApplicationInstanceToFilesystemManager())->handleRequestReceived($event);

        $this->assertSame($request, $event->getRequest());
    }
}
