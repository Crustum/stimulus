<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Listener;

use Cake\Http\ServerRequest;
use Cake\Routing\Router;
use Crustum\Stimulus\Event\RequestReceivedEvent;
use Crustum\Stimulus\Listener\GiveNewApplicationInstanceToRouter;
use Crustum\Stimulus\Test\TestCase;
use stdClass;

/**
 * Tests for the router refreshing listener.
 *
 * Covers the router state scenario: the router points at the current
 * operation request while the connected routes stay intact.
 */
class GiveNewApplicationInstanceToRouterTest extends TestCase
{
    /**
     * The router points at the received request.
     *
     * @return void
     */
    public function testRouterPointsAtReceivedRequest(): void
    {
        $previous = Router::getRequest();
        $request = new ServerRequest(['url' => '/stimulus']);
        $event = new RequestReceivedEvent(new stdClass(), new stdClass(), $request);

        try {
            (new GiveNewApplicationInstanceToRouter())->handleRequestReceived($event);

            $this->assertSame($request, Router::getRequest());
        } finally {
            if ($previous instanceof ServerRequest) {
                Router::setRequest($previous);
            }
        }
    }
}
