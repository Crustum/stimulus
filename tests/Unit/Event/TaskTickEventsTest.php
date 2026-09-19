<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Event;

use Cake\Event\Event;
use Crustum\Stimulus\Contracts\OperationTerminated;
use Crustum\Stimulus\Event\StimulusEvent;
use Crustum\Stimulus\Event\TaskReceivedEvent;
use Crustum\Stimulus\Event\TaskTerminatedEvent;
use Crustum\Stimulus\Event\TickReceivedEvent;
use Crustum\Stimulus\Event\TickTerminatedEvent;
use Crustum\Stimulus\Test\TestCase;
use stdClass;

/**
 * Tests for the worker task and tick events.
 */
class TaskTickEventsTest extends TestCase
{
    /**
     * Task received event carries both applications and the payload.
     *
     * @return void
     */
    public function testTaskReceivedEvent(): void
    {
        $app = new stdClass();
        $sandbox = new stdClass();

        $event = new TaskReceivedEvent($app, $sandbox, 'payload');

        $this->assertInstanceOf(Event::class, $event);
        $this->assertInstanceOf(StimulusEvent::class, $event);
        $this->assertSame('Stimulus.taskReceived', $event->getName());
        $this->assertSame('Stimulus.taskReceived', TaskReceivedEvent::eventName());
        $this->assertSame($app, $event->app);
        $this->assertSame($sandbox, $event->sandbox);
        $this->assertSame('payload', $event->data);
        $this->assertSame($app, $event->getApp());
        $this->assertSame($sandbox, $event->getSandbox());
        $this->assertSame('payload', $event->getPayload());
        $this->assertSame($app, $event->getData('app'));
        $this->assertSame($sandbox, $event->getData('sandbox'));
        $this->assertSame('payload', $event->getData('data'));
    }

    /**
     * Task terminated event exposes both applications via the trait.
     *
     * @return void
     */
    public function testTaskTerminatedEvent(): void
    {
        $app = new stdClass();
        $sandbox = new stdClass();

        $event = new TaskTerminatedEvent($app, $sandbox, 'payload', 'result');

        $this->assertInstanceOf(OperationTerminated::class, $event);
        $this->assertInstanceOf(StimulusEvent::class, $event);
        $this->assertSame('Stimulus.taskTerminated', $event->getName());
        $this->assertSame('Stimulus.taskTerminated', TaskTerminatedEvent::eventName());
        $this->assertSame($app, $event->app());
        $this->assertSame($sandbox, $event->sandbox());
        $this->assertSame('payload', $event->data);
        $this->assertSame('result', $event->result);
        $this->assertSame($app, $event->getApp());
        $this->assertSame($sandbox, $event->getSandbox());
        $this->assertSame('payload', $event->getPayload());
        $this->assertSame('result', $event->getTaskResult());
        $this->assertSame('payload', $event->getData('data'));
        $this->assertSame('result', $event->getData('result'));
    }

    /**
     * Tick received event carries both applications.
     *
     * @return void
     */
    public function testTickReceivedEvent(): void
    {
        $app = new stdClass();
        $sandbox = new stdClass();

        $event = new TickReceivedEvent($app, $sandbox);

        $this->assertInstanceOf(Event::class, $event);
        $this->assertInstanceOf(StimulusEvent::class, $event);
        $this->assertSame('Stimulus.tickReceived', $event->getName());
        $this->assertSame('Stimulus.tickReceived', TickReceivedEvent::eventName());
        $this->assertSame($app, $event->app);
        $this->assertSame($sandbox, $event->sandbox);
        $this->assertSame($app, $event->getApp());
        $this->assertSame($sandbox, $event->getSandbox());
        $this->assertSame($app, $event->getData('app'));
        $this->assertSame($sandbox, $event->getData('sandbox'));
    }

    /**
     * Tick terminated event exposes both applications via the trait.
     *
     * @return void
     */
    public function testTickTerminatedEvent(): void
    {
        $app = new stdClass();
        $sandbox = new stdClass();

        $event = new TickTerminatedEvent($app, $sandbox);

        $this->assertInstanceOf(OperationTerminated::class, $event);
        $this->assertInstanceOf(StimulusEvent::class, $event);
        $this->assertSame('Stimulus.tickTerminated', $event->getName());
        $this->assertSame('Stimulus.tickTerminated', TickTerminatedEvent::eventName());
        $this->assertSame($app, $event->app());
        $this->assertSame($sandbox, $event->sandbox());
        $this->assertSame($app, $event->getApp());
        $this->assertSame($sandbox, $event->getSandbox());
        $this->assertSame($app, $event->getData('app'));
        $this->assertSame($sandbox, $event->getData('sandbox'));
    }
}
