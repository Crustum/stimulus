<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Listener;

use Cake\Core\Configure;
use Cake\Http\ServerRequest;
use Crustum\Stimulus\Event\RequestReceivedEvent;
use Crustum\Stimulus\Listener\EnforceRequestScheme;
use Crustum\Stimulus\Test\TestCase;
use stdClass;

/**
 * Tests for the request scheme enforcement listener.
 *
 * Covers the worker scheme enforcement scenario: the request is marked
 * secure and generated links use HTTPS when the flag is set.
 */
class EnforceRequestSchemeTest extends TestCase
{
    /**
     * Plain requests pass through when HTTPS is not enforced.
     *
     * @return void
     */
    public function testRequestSchemeIsUntouchedByDefault(): void
    {
        $event = new RequestReceivedEvent(new stdClass(), new stdClass(), new ServerRequest(['url' => '/first']));

        (new EnforceRequestScheme())->handleRequestReceived($event);

        $this->assertSame('http', $event->getRequest()->getUri()->getScheme());
        $this->assertNotSame('on', $event->getRequest()->getEnv('HTTPS'));
    }

    /**
     * Requests are forced to HTTPS when the flag is set.
     *
     * @return void
     */
    public function testRequestSchemeIsEnforced(): void
    {
        Configure::write('Stimulus.https', true);

        try {
            $event = new RequestReceivedEvent(new stdClass(), new stdClass(), new ServerRequest(['url' => '/first']));

            (new EnforceRequestScheme())->handleRequestReceived($event);

            $this->assertSame('https', $event->getRequest()->getUri()->getScheme());
            $this->assertSame('on', $event->getRequest()->getEnv('HTTPS'));
        } finally {
            Configure::delete('Stimulus.https');
        }
    }
}
