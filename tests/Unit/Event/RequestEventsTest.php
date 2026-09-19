<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Event;

use Cake\Event\Event;
use Cake\Http\ServerRequest;
use Crustum\Stimulus\Contracts\OperationTerminated;
use Crustum\Stimulus\Event\RequestHandledEvent;
use Crustum\Stimulus\Event\RequestReceivedEvent;
use Crustum\Stimulus\Event\RequestTerminatedEvent;
use Crustum\Stimulus\Event\StimulusEvent;
use Crustum\Stimulus\Test\TestCase;
use Laminas\Diactoros\Response as PsrResponse;
use stdClass;

/**
 * Tests for the worker request lifecycle events.
 */
class RequestEventsTest extends TestCase
{
    /**
     * Request received event carries both applications and the request.
     *
     * @return void
     */
    public function testRequestReceivedEvent(): void
    {
        $app = new stdClass();
        $sandbox = new stdClass();
        $request = new ServerRequest();

        $event = new RequestReceivedEvent($app, $sandbox, $request);

        $this->assertInstanceOf(Event::class, $event);
        $this->assertInstanceOf(StimulusEvent::class, $event);
        $this->assertSame('Stimulus.requestReceived', $event->getName());
        $this->assertSame('Stimulus.requestReceived', RequestReceivedEvent::eventName());
        $this->assertSame($app, $event->app);
        $this->assertSame($sandbox, $event->sandbox);
        $this->assertSame($request, $event->request);
        $this->assertSame($app, $event->getApp());
        $this->assertSame($sandbox, $event->getSandbox());
        $this->assertSame($request, $event->getRequest());
        $this->assertSame($app, $event->getData('app'));
        $this->assertSame($sandbox, $event->getData('sandbox'));
        $this->assertSame($request, $event->getData('request'));
    }

    /**
     * Request handled event carries the sandbox, request, and response.
     *
     * @return void
     */
    public function testRequestHandledEvent(): void
    {
        $sandbox = new stdClass();
        $request = new ServerRequest();
        $response = new PsrResponse();

        $event = new RequestHandledEvent($sandbox, $request, $response);

        $this->assertInstanceOf(Event::class, $event);
        $this->assertInstanceOf(StimulusEvent::class, $event);
        $this->assertSame('Stimulus.requestHandled', $event->getName());
        $this->assertSame('Stimulus.requestHandled', RequestHandledEvent::eventName());
        $this->assertSame($sandbox, $event->sandbox);
        $this->assertSame($request, $event->request);
        $this->assertSame($response, $event->response);
        $this->assertSame($sandbox, $event->getSandbox());
        $this->assertSame($request, $event->getRequest());
        $this->assertSame($response, $event->getResponse());
        $this->assertSame($sandbox, $event->getData('sandbox'));
        $this->assertSame($request, $event->getData('request'));
        $this->assertSame($response, $event->getData('response'));
    }

    /**
     * Request terminated event exposes both applications via the trait.
     *
     * @return void
     */
    public function testRequestTerminatedEvent(): void
    {
        $app = new stdClass();
        $sandbox = new stdClass();
        $request = new ServerRequest();
        $response = new PsrResponse();

        $event = new RequestTerminatedEvent($app, $sandbox, $request, $response);

        $this->assertInstanceOf(OperationTerminated::class, $event);
        $this->assertInstanceOf(StimulusEvent::class, $event);
        $this->assertSame('Stimulus.requestTerminated', $event->getName());
        $this->assertSame('Stimulus.requestTerminated', RequestTerminatedEvent::eventName());
        $this->assertSame($app, $event->app());
        $this->assertSame($sandbox, $event->sandbox());
        $this->assertSame($app, $event->getApp());
        $this->assertSame($sandbox, $event->getSandbox());
        $this->assertSame($request, $event->getRequest());
        $this->assertSame($response, $event->getResponse());
        $this->assertSame($app, $event->getData('app'));
        $this->assertSame($sandbox, $event->getData('sandbox'));
        $this->assertSame($request, $event->getData('request'));
        $this->assertSame($response, $event->getData('response'));
    }

    /**
     * Rewritten requests stay coherent between getters and event data.
     *
     * @return void
     */
    public function testSetRequestKeepsGettersAndDataInSync(): void
    {
        $event = new RequestReceivedEvent(new stdClass(), new stdClass(), new ServerRequest());

        $replacement = new ServerRequest(['url' => '/replaced']);
        $event->setRequest($replacement);

        $this->assertSame($replacement, $event->getRequest());
        $this->assertSame($replacement, $event->request);
        $this->assertSame($replacement, $event->getData('request'));
    }
}
