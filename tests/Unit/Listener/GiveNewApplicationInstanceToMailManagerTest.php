<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Listener;

use Cake\Http\ServerRequest;
use Cake\Mailer\Transport\DebugTransport;
use Cake\Mailer\TransportFactory;
use Crustum\Stimulus\Event\RequestReceivedEvent;
use Crustum\Stimulus\Listener\GiveNewApplicationInstanceToMailManager;
use Crustum\Stimulus\Test\TestCase;
use stdClass;

/**
 * Tests for the mail manager refreshing listener.
 *
 * Covers the mail manager state scenario: resolved transports are forgotten
 * while their configuration survives for the rebuild.
 */
class GiveNewApplicationInstanceToMailManagerTest extends TestCase
{
    /**
     * Resolved transports are forgotten while their configuration survives.
     *
     * @return void
     */
    public function testResolvedTransportsAreForgotten(): void
    {
        TransportFactory::setConfig('stimulus_test_mail', ['className' => DebugTransport::class]);

        $before = TransportFactory::get('stimulus_test_mail');

        try {
            $event = new RequestReceivedEvent(new stdClass(), new stdClass(), new ServerRequest(['url' => '/']));

            (new GiveNewApplicationInstanceToMailManager(['stimulus_test_mail']))->handleRequestReceived($event);

            $this->assertContains('stimulus_test_mail', TransportFactory::configured());
            $this->assertNotSame($before, TransportFactory::get('stimulus_test_mail'));
        } finally {
            TransportFactory::drop('stimulus_test_mail');
        }
    }
}
