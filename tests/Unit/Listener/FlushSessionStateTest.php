<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Listener;

use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Crustum\Stimulus\Event\RequestReceivedEvent;
use Crustum\Stimulus\Event\RequestTerminatedEvent;
use Crustum\Stimulus\Listener\FlushSessionState;
use Crustum\Stimulus\Test\TestCase;
use stdClass;

/**
 * Tests for the session state flushing listener.
 *
 * The listener resets process-global session leftovers around each
 * operation while the persisted session data itself survives: data written
 * while handling a request is still there afterwards, and nothing leaks
 * into the next request.
 */
class FlushSessionStateTest extends TestCase
{
    /**
     * Session data written during a request survives the received listener.
     *
     * @return void
     */
    public function testSessionDataSurvivesRequestReceived(): void
    {
        $request = new ServerRequest(['url' => '/']);
        $event = new RequestReceivedEvent(new stdClass(), new stdClass(), $request);

        (new FlushSessionState())->handleRequestReceived($event);

        $event->getRequest()->getSession()->write('color', 'blue');

        $this->assertSame('blue', $event->getRequest()->getSession()->read('color'));
    }

    /**
     * Session state does not leak into the next request.
     *
     * @return void
     */
    public function testSessionStateDoesNotLeakIntoNextRequest(): void
    {
        $request = new ServerRequest(['url' => '/']);
        $request->getSession()->write('color', 'blue');

        $terminated = new RequestTerminatedEvent(
            new stdClass(),
            new stdClass(),
            $request,
            new Response(),
        );

        (new FlushSessionState())->handleRequestTerminated($terminated);

        $nextRequest = new ServerRequest(['url' => '/']);
        $received = new RequestReceivedEvent(new stdClass(), new stdClass(), $nextRequest);

        (new FlushSessionState())->handleRequestReceived($received);

        $this->assertNull($received->getRequest()->getSession()->read('color'));
        $this->assertSame([], $_SESSION);
    }

    /**
     * Received request points the session at the request cookie.
     *
     * @return void
     */
    public function testReceivedPointsSessionAtRequestCookie(): void
    {
        $previous = session_id();

        try {
            $request = new ServerRequest([
                'url' => '/',
                'cookies' => [session_name() => 'stimulus-probe-session'],
            ]);
            $event = new RequestReceivedEvent(new stdClass(), new stdClass(), $request);

            (new FlushSessionState())->handleRequestReceived($event);

            $this->assertSame('stimulus-probe-session', session_id());
        } finally {
            session_id($previous);
        }
    }
}
