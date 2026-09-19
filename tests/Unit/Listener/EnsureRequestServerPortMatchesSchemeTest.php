<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Listener;

use Cake\Http\ServerRequest;
use Crustum\Stimulus\Event\RequestReceivedEvent;
use Crustum\Stimulus\Listener\EnsureRequestServerPortMatchesScheme;
use Crustum\Stimulus\Test\TestCase;
use stdClass;

/**
 * Tests for the server port matching listener.
 *
 * Covers the worker server port scenario: missing ports are derived from
 * the scheme while explicit ports are kept.
 */
class EnsureRequestServerPortMatchesSchemeTest extends TestCase
{
    /**
     * Plain requests without a port use port 80.
     *
     * @return void
     */
    public function testPlainRequestPortIsCorrect(): void
    {
        $event = new RequestReceivedEvent(new stdClass(), new stdClass(), new ServerRequest(['url' => '/port']));

        (new EnsureRequestServerPortMatchesScheme())->handleRequestReceived($event);

        $this->assertSame('80', $event->getRequest()->getEnv('SERVER_PORT'));
    }

    /**
     * Secure requests without a port use port 443.
     *
     * @return void
     */
    public function testSecureRequestPortIsCorrect(): void
    {
        $request = new ServerRequest([
            'url' => '/port',
            'environment' => ['HTTP_HOST' => 'localhost', 'REQUEST_URI' => '/port', 'HTTPS' => 'on'],
        ]);
        $event = new RequestReceivedEvent(new stdClass(), new stdClass(), $request);

        (new EnsureRequestServerPortMatchesScheme())->handleRequestReceived($event);

        $this->assertSame('https', $event->getRequest()->getUri()->getScheme());
        $this->assertSame('443', $event->getRequest()->getEnv('SERVER_PORT'));
    }

    /**
     * Requests with an explicit port keep it.
     *
     * @return void
     */
    public function testExplicitPortIsKept(): void
    {
        $request = new ServerRequest(['url' => '/port']);
        $request = $request->withUri($request->getUri()->withPort(8080));

        $event = new RequestReceivedEvent(new stdClass(), new stdClass(), $request);

        (new EnsureRequestServerPortMatchesScheme())->handleRequestReceived($event);

        $this->assertSame(8080, $event->getRequest()->getUri()->getPort());
        $this->assertNull($event->getRequest()->getEnv('SERVER_PORT'));
    }
}
