<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Listener;

use Cake\Http\ServerRequest;
use Crustum\Stimulus\Event\RequestReceivedEvent;
use Crustum\Stimulus\Listener\GiveNewApplicationInstanceToSessionManager;
use Crustum\Stimulus\Test\TestCase;
use stdClass;

/**
 * Tests for the session manager refreshing listener.
 *
 * Every marshalled request already carries a fresh session object, so the
 * listener leaves it untouched: session data written for the operation
 * survives the received event.
 */
class GiveNewApplicationInstanceToSessionManagerTest extends TestCase
{
    /**
     * Session data survives the received event.
     *
     * @return void
     */
    public function testSessionIsKeptForOperation(): void
    {
        $request = new ServerRequest(['url' => '/']);
        $request->getSession()->write('color', 'blue');
        $event = new RequestReceivedEvent(new stdClass(), new stdClass(), $request);

        (new GiveNewApplicationInstanceToSessionManager())->handleRequestReceived($event);

        $this->assertSame('blue', $request->getSession()->read('color'));
    }
}
