<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Trait;

use Crustum\Stimulus\Listener\CreateConfigurationSandbox;
use Crustum\Stimulus\Listener\FlushLocaleState;
use Crustum\Stimulus\Listener\FlushMonologState;
use Crustum\Stimulus\Listener\FlushOnce;
use Crustum\Stimulus\Listener\FlushStrCache;
use Crustum\Stimulus\Listener\GiveNewRequestInstanceToApplication;
use Crustum\Stimulus\Listener\PrepareExploratorForNextOperation;
use Crustum\Stimulus\Test\Support\ConfigurationOptionsTraitHost;
use Crustum\Stimulus\Test\TestCase;

/**
 * Tests for the default configuration options trait.
 *
 * Asserts the listener lists and warm service identifiers.
 */
class ProvidesDefaultConfigurationOptionsTraitTest extends TestCase
{
    /**
     * Request listeners prepare the sandbox for the next request.
     *
     * @return void
     */
    public function testPrepareApplicationForNextRequest(): void
    {
        $listeners = ConfigurationOptionsTraitHost::prepareApplicationForNextRequest();

        $this->assertCount(7, $listeners);
        $this->assertSame(
            FlushLocaleState::class,
            $listeners[0],
        );
        $this->assertSame(
            GiveNewRequestInstanceToApplication::class,
            $listeners[6],
        );

        foreach ($listeners as $listener) {
            $this->assertStringStartsWith('Crustum\Stimulus\Listener\\', $listener);
            $this->assertTrue(class_exists($listener));
        }
    }

    /**
     * Operation listeners prepare the sandbox for the next operation.
     *
     * @return void
     */
    public function testPrepareApplicationForNextOperation(): void
    {
        $listeners = ConfigurationOptionsTraitHost::prepareApplicationForNextOperation();

        $this->assertCount(26, $listeners);
        $this->assertSame(
            CreateConfigurationSandbox::class,
            $listeners[0],
        );
        $this->assertSame(
            FlushMonologState::class,
            $listeners[21],
        );
        $this->assertSame(
            FlushOnce::class,
            $listeners[22],
        );
        $this->assertSame(
            FlushStrCache::class,
            $listeners[23],
        );
        $this->assertSame(
            PrepareExploratorForNextOperation::class,
            $listeners[25],
        );

        foreach ($listeners as $listener) {
            $this->assertStringStartsWith('Crustum\Stimulus\Listener\\', $listener);
            $this->assertTrue(class_exists($listener));
        }
    }

    /**
     * Warm services list the container bindings pre-resolved by default.
     *
     * @return void
     */
    public function testDefaultServicesToWarm(): void
    {
        $services = ConfigurationOptionsTraitHost::defaultServicesToWarm();

        $this->assertCount(18, $services);
        $this->assertSame('auth', $services[0]);
        $this->assertSame('view', $services[17]);
    }
}
