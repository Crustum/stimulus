<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Listener;

use Cake\Http\ServerRequest;
use Crustum\Stimulus\Event\RequestReceivedEvent;
use Crustum\Stimulus\Listener\GiveNewApplicationInstanceToViewFactory;
use Crustum\Stimulus\Test\TestCase;
use stdClass;

/**
 * Tests for the view factory refreshing listener.
 *
 * A fresh view is built per render, so the listener keeps its
 * pipeline shape without resetting any state and the test only proves the
 * operation passes through untouched.
 */
class GiveNewApplicationInstanceToViewFactoryTest extends TestCase
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

        (new GiveNewApplicationInstanceToViewFactory())->handleRequestReceived($event);

        $this->assertSame($request, $event->getRequest());
    }
}
