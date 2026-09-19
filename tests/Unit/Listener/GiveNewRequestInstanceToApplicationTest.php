<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Listener;

use Cake\Http\ServerRequest;
use Crustum\Stimulus\Event\RequestReceivedEvent;
use Crustum\Stimulus\Http\CurrentApplication;
use Crustum\Stimulus\Listener\GiveNewRequestInstanceToApplication;
use Crustum\Stimulus\Test\TestCase;
use stdClass;

/**
 * Tests for the request instance binding listener.
 *
 * Covers the request binding scenario: the current-application seam points
 * at the received request.
 */
class GiveNewRequestInstanceToApplicationTest extends TestCase
{
    /**
     * The current-application seam points at the received request.
     *
     * @return void
     */
    public function testSeamPointsAtReceivedRequest(): void
    {
        $request = new ServerRequest(['url' => '/stimulus']);
        $event = new RequestReceivedEvent(new stdClass(), new stdClass(), $request);

        try {
            (new GiveNewRequestInstanceToApplication())->handleRequestReceived($event);

            $this->assertSame($request, CurrentApplication::getRequest());
        } finally {
            CurrentApplication::reset();
        }
    }
}
