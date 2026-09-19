<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Worker;

use Crustum\Stimulus\Dispatch\SequentialTaskDispatcher;
use Crustum\Stimulus\StimulusManager;
use Crustum\Stimulus\Test\TestCase;
use Exception;

/**
 * Tests for the Stimulus worker manager service.
 *
 * The manager is a dependency-injection service, so this suite asserts route
 * helpers, concurrent task resolution, tick registration, table guards, and
 * exception formatting through the instance.
 */
class StimulusManagerTest extends TestCase
{
    /**
     * Empty task lists resolve without involving a dispatcher.
     *
     * @return void
     */
    public function testConcurrentlyResolvesEmptyTasks(): void
    {
        $this->assertSame([], (new StimulusManager())->concurrently([]));
    }

    /**
     * Tasks resolve through the sequential dispatcher by default.
     *
     * @return void
     */
    public function testConcurrentlyResolvesTasksSequentially(): void
    {
        $manager = new StimulusManager();

        $this->assertInstanceOf(SequentialTaskDispatcher::class, $manager->tasks());
        $this->assertSame(['foo'], $manager->concurrently([fn(): string => 'foo']));
    }

    /**
     * An injected dispatcher wins over the default resolution.
     *
     * @return void
     */
    public function testInjectedDispatcherWins(): void
    {
        $dispatcher = new SequentialTaskDispatcher();
        $manager = new StimulusManager($dispatcher);

        $this->assertSame($dispatcher, $manager->tasks());
    }

    /**
     * Table access raises until the P9 Swoole side activates.
     *
     * @return void
     */
    public function testTableRaisesWithoutSwoole(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Tables may only be accessed when using the Swoole server.');

        (new StimulusManager())->table('example');
    }

    /**
     * Exceptions format for the client with and without debug mode.
     *
     * @return void
     */
    public function testFormatExceptionForClient(): void
    {
        $exception = new Exception('Something went wrong.');

        $this->assertSame('Internal server error.', StimulusManager::formatExceptionForClient($exception));
        $this->assertStringContainsString('Something went wrong.', StimulusManager::formatExceptionForClient($exception, true));
    }

    /**
     * Default sandbox options stay available for configuration and warm lists.
     *
     * @return void
     */
    public function testDefaultSandboxOptions(): void
    {
        $this->assertNotEmpty(StimulusManager::prepareApplicationForNextRequest());
        $this->assertNotEmpty(StimulusManager::prepareApplicationForNextOperation());
        $this->assertNotEmpty(StimulusManager::defaultServicesToWarm());
    }
}
