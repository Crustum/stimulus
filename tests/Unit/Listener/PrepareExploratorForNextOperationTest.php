<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Listener;

use Cake\Http\ServerRequest;
use Crustum\Explorator\Engine\NullEngine;
use Crustum\Explorator\EngineManager;
use Crustum\Stimulus\Event\RequestReceivedEvent;
use Crustum\Stimulus\Listener\PrepareExploratorForNextOperation;
use Crustum\Stimulus\Test\TestCase;
use stdClass;

/**
 * Tests for the search preparation listener.
 *
 * Covers the Explorator state scenario: resolved engines are forgotten while the
 * custom driver registrations stay registered.
 */
class PrepareExploratorForNextOperationTest extends TestCase
{
    /**
     * Resolved engines are forgotten while registrations stay registered.
     *
     * @return void
     */
    public function testResolvedEnginesAreForgotten(): void
    {
        $manager = new EngineManager();
        $manager->extend('stimulus_test', fn(): NullEngine => new NullEngine());

        $before = $manager->driver('stimulus_test');
        $event = new RequestReceivedEvent(new stdClass(), new stdClass(), new ServerRequest(['url' => '/']));

        (new PrepareExploratorForNextOperation($manager))->handleRequestReceived($event);

        $after = $manager->driver('stimulus_test');

        $this->assertInstanceOf(NullEngine::class, $after);
        $this->assertNotSame($before, $after);
    }

    /**
     * Without a manager every operation passes through untouched.
     *
     * @return void
     */
    public function testWithoutManagerOperationPassesThrough(): void
    {
        $request = new ServerRequest(['url' => '/']);
        $event = new RequestReceivedEvent(new stdClass(), new stdClass(), $request);

        (new PrepareExploratorForNextOperation())->handleRequestReceived($event);

        $this->assertSame($request, $event->getRequest());
    }
}
