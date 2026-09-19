<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Listener;

use Cake\Http\ServerRequest;
use Crustum\Stimulus\Event\RequestReceivedEvent;
use Crustum\Stimulus\Listener\FlushAuthenticationState;
use Crustum\Stimulus\Test\TestCase;
use stdClass;

/**
 * Tests for the authentication state flushing listener.
 *
 * The in-memory request identity is dropped so each request
 * re-authenticates, while the persisted session identity survives so
 * logins and form protection tokens stay valid across requests.
 */
class FlushAuthenticationStateTest extends TestCase
{
    /**
     * Request identity is removed but the persisted session identity survives.
     *
     * @return void
     */
    public function testRequestIdentityIsFlushedButSessionIdentitySurvives(): void
    {
        $request = (new ServerRequest(['url' => '/']))->withAttribute('identity', new stdClass());
        $request->getSession()->write('Auth', ['id' => 1]);

        $event = new RequestReceivedEvent(new stdClass(), new stdClass(), $request);

        (new FlushAuthenticationState())->handleRequestReceived($event);

        $this->assertNull($event->getRequest()->getAttribute('identity'));
        $this->assertSame(['id' => 1], $request->getSession()->read('Auth'));
    }

    /**
     * Requests without identity state pass through untouched.
     *
     * @return void
     */
    public function testAnonymousRequestPassesThrough(): void
    {
        $event = new RequestReceivedEvent(new stdClass(), new stdClass(), new ServerRequest(['url' => '/']));

        (new FlushAuthenticationState())->handleRequestReceived($event);

        $this->assertNull($event->getRequest()->getAttribute('identity'));
        $this->assertNull($event->getRequest()->getSession()->read('Auth'));
    }
}
