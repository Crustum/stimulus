<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Listener;

use Authorization\AuthorizationService;
use Authorization\Policy\MapResolver;
use Cake\Http\ServerRequest;
use Crustum\Stimulus\Event\RequestReceivedEvent;
use Crustum\Stimulus\Listener\GiveNewApplicationInstanceToAuthorizationGate;
use Crustum\Stimulus\Test\TestCase;
use stdClass;

/**
 * Tests for the authorization gate refreshing listener.
 *
 * Covers the gate state scenario: the request authorization service is
 * replaced with a sandbox-fresh instance while foreign attributes and
 * provider-less hosts pass through untouched.
 */
class GiveNewApplicationInstanceToAuthorizationGateTest extends TestCase
{
    /**
     * The stale authorization service is replaced with a fresh instance.
     *
     * @return void
     */
    public function testStaleServiceIsReplaced(): void
    {
        $stale = new AuthorizationService(new MapResolver());
        $stale->skipAuthorization();

        $request = (new ServerRequest(['url' => '/']))->withAttribute('authorization', $stale);
        $event = new RequestReceivedEvent(new stdClass(), new stdClass(), $request);

        $listener = new GiveNewApplicationInstanceToAuthorizationGate(
            fn(): AuthorizationService => new AuthorizationService(new MapResolver()),
        );
        $listener->handleRequestReceived($event);

        $fresh = $event->getRequest()->getAttribute('authorization');

        $this->assertInstanceOf(AuthorizationService::class, $fresh);
        $this->assertNotSame($stale, $fresh);
        $this->assertFalse($fresh->authorizationChecked());
    }

    /**
     * Without a provider the current service is kept.
     *
     * @return void
     */
    public function testWithoutProviderServiceIsKept(): void
    {
        $stale = new AuthorizationService(new MapResolver());

        $request = (new ServerRequest(['url' => '/']))->withAttribute('authorization', $stale);
        $event = new RequestReceivedEvent(new stdClass(), new stdClass(), $request);

        (new GiveNewApplicationInstanceToAuthorizationGate())->handleRequestReceived($event);

        $this->assertSame($stale, $event->getRequest()->getAttribute('authorization'));
    }

    /**
     * Foreign authorization attributes are left alone.
     *
     * @return void
     */
    public function testForeignAttributeIsLeftAlone(): void
    {
        $request = (new ServerRequest(['url' => '/']))->withAttribute('authorization', 'not-a-service');
        $event = new RequestReceivedEvent(new stdClass(), new stdClass(), $request);

        $listener = new GiveNewApplicationInstanceToAuthorizationGate(
            fn(): AuthorizationService => new AuthorizationService(new MapResolver()),
        );
        $listener->handleRequestReceived($event);

        $this->assertSame('not-a-service', $event->getRequest()->getAttribute('authorization'));
    }
}
