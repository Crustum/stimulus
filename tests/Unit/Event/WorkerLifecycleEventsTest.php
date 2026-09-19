<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Event;

use Cake\Event\Event;
use Crustum\Stimulus\Event\StimulusEvent;
use Crustum\Stimulus\Event\WorkerErrorOccurredEvent;
use Crustum\Stimulus\Event\WorkerStartingEvent;
use Crustum\Stimulus\Event\WorkerStoppingEvent;
use Crustum\Stimulus\Test\TestCase;
use RuntimeException;
use stdClass;

/**
 * Tests for the worker lifecycle events.
 */
class WorkerLifecycleEventsTest extends TestCase
{
    /**
     * Worker starting event carries the base application.
     *
     * @return void
     */
    public function testWorkerStartingEvent(): void
    {
        $app = new stdClass();

        $event = new WorkerStartingEvent($app);

        $this->assertInstanceOf(Event::class, $event);
        $this->assertInstanceOf(StimulusEvent::class, $event);
        $this->assertSame('Stimulus.workerStarting', $event->getName());
        $this->assertSame('Stimulus.workerStarting', WorkerStartingEvent::eventName());
        $this->assertSame($app, $event->app);
        $this->assertSame($app, $event->getApp());
        $this->assertSame($app, $event->getData('app'));
    }

    /**
     * Worker stopping event carries the base application.
     *
     * @return void
     */
    public function testWorkerStoppingEvent(): void
    {
        $app = new stdClass();

        $event = new WorkerStoppingEvent($app);

        $this->assertInstanceOf(Event::class, $event);
        $this->assertInstanceOf(StimulusEvent::class, $event);
        $this->assertSame('Stimulus.workerStopping', $event->getName());
        $this->assertSame('Stimulus.workerStopping', WorkerStoppingEvent::eventName());
        $this->assertSame($app, $event->app);
        $this->assertSame($app, $event->getApp());
        $this->assertSame($app, $event->getData('app'));
    }

    /**
     * Worker error event carries the throwable and the sandbox.
     *
     * @return void
     */
    public function testWorkerErrorOccurredEvent(): void
    {
        $exception = new RuntimeException('Boom.');
        $sandbox = new stdClass();

        $event = new WorkerErrorOccurredEvent($exception, $sandbox);

        $this->assertInstanceOf(Event::class, $event);
        $this->assertInstanceOf(StimulusEvent::class, $event);
        $this->assertSame('Stimulus.workerErrorOccurred', $event->getName());
        $this->assertSame('Stimulus.workerErrorOccurred', WorkerErrorOccurredEvent::eventName());
        $this->assertSame($exception, $event->exception);
        $this->assertSame($sandbox, $event->sandbox);
        $this->assertSame($exception, $event->getException());
        $this->assertSame($sandbox, $event->getSandbox());
        $this->assertSame($exception, $event->getData('exception'));
        $this->assertSame($sandbox, $event->getData('sandbox'));
    }
}
