<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Trait;

use Cake\Event\EventManager;
use Closure;
use Crustum\Stimulus\Bridge\Swoole\InvokeTickCallable;
use Crustum\Stimulus\Event\TickReceivedEvent;
use Crustum\Stimulus\Test\Support\TickHandlersTraitHost;
use Crustum\Stimulus\Test\TestCase;
use stdClass;

/**
 * Tests for the tick handler registration trait.
 *
 * Without the extension the registration runs callbacks on every received
 * tick; on extension hosts the registration returns the due-checked invoker,
 * so back-to-back ticks invoke an hourly callback only once.
 */
class RegistersTickHandlersTraitTest extends TestCase
{
    /**
     * Registered callbacks run on received ticks.
     *
     * @return void
     */
    public function testTickRunsCallbackOnEveryTick(): void
    {
        $calls = 0;

        $swooleAvailable = extension_loaded('swoole') || extension_loaded('openswoole');

        $listener = (new TickHandlersTraitHost())->tick('probe', function () use (&$calls): void {
            $calls++;
        }, 3600);

        if ($swooleAvailable) {
            $this->assertInstanceOf(InvokeTickCallable::class, $listener);
        } else {
            $this->assertInstanceOf(Closure::class, $listener);
        }

        try {
            $app = new stdClass();
            $sandbox = new stdClass();

            EventManager::instance()->dispatch(new TickReceivedEvent($app, $sandbox));
            EventManager::instance()->dispatch(new TickReceivedEvent($app, $sandbox));

            $this->assertSame($swooleAvailable ? 1 : 2, $calls);
        } finally {
            EventManager::instance()->off(TickReceivedEvent::eventName(), $listener);
        }
    }
}
