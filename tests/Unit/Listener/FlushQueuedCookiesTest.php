<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Listener;

use Cake\Http\ServerRequest;
use Crustum\Stimulus\Event\RequestReceivedEvent;
use Crustum\Stimulus\Listener\FlushQueuedCookies;
use Crustum\Stimulus\Test\Support\QueuedCookieTestSandbox;
use Crustum\Stimulus\Test\TestCase;
use stdClass;

/**
 * Tests for the queued cookie flushing listener.
 *
 * Covers the worker queued cookie scenario: sandboxes exposing a shared
 * cookie queue have it flushed, while plain sandboxes pass through.
 */
class FlushQueuedCookiesTest extends TestCase
{
    /**
     * Shared sandbox cookie queues are flushed.
     *
     * @return void
     */
    public function testQueuedCookiesAreFlushed(): void
    {
        $sandbox = new QueuedCookieTestSandbox();
        $event = new RequestReceivedEvent(new stdClass(), $sandbox, new ServerRequest(['url' => '/']));

        (new FlushQueuedCookies())->handleRequestReceived($event);

        $this->assertSame(1, $sandbox->flushes);
    }

    /**
     * Sandboxes without a cookie queue pass through untouched.
     *
     * @return void
     */
    public function testSandboxWithoutQueuePassesThrough(): void
    {
        $event = new RequestReceivedEvent(new stdClass(), new stdClass(), new ServerRequest(['url' => '/']));

        (new FlushQueuedCookies())->handleRequestReceived($event);

        $this->assertInstanceOf(RequestReceivedEvent::class, $event);
    }
}
