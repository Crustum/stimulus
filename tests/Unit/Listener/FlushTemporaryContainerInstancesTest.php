<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Listener;

use Cake\Core\Configure;
use Crustum\Stimulus\Event\TaskTerminatedEvent;
use Crustum\Stimulus\Listener\FlushTemporaryContainerInstances;
use Crustum\Stimulus\Test\TestCase;
use stdClass;

/**
 * Tests for the temporary container instance flushing listener.
 */
class FlushTemporaryContainerInstancesTest extends TestCase
{
    /**
     * Configured bindings resolve fresh after the operation terminates.
     *
     * @return void
     */
    public function testTemporaryInstancesAreFlushed(): void
    {
        $app = $this->createApplication();

        Configure::write('Stimulus.flush', ['auth', 'cache']);

        try {
            $resolved = [];

            foreach (['auth', 'cache'] as $binding) {
                $app->getContainer()->addShared($binding, function () use ($binding, &$resolved): stdClass {
                    $instance = new stdClass();
                    $resolved[$binding][] = $instance;

                    return $instance;
                });
            }

            $app->getContainer()->get('auth');
            $app->getContainer()->get('cache');

            $event = new TaskTerminatedEvent($app, $app, null, null);

            (new FlushTemporaryContainerInstances())->handleOperationTerminated($event);

            $app->getContainer()->get('auth');
            $app->getContainer()->get('cache');

            $this->assertCount(2, $resolved['auth']);
            $this->assertCount(2, $resolved['cache']);
            $this->assertNotSame($resolved['auth'][0], $resolved['auth'][1]);
            $this->assertNotSame($resolved['cache'][0], $resolved['cache'][1]);
        } finally {
            Configure::delete('Stimulus.flush');
        }
    }

    /**
     * Applications without container hooks pass through untouched.
     *
     * @return void
     */
    public function testApplicationWithoutHooksPassesThrough(): void
    {
        $event = new TaskTerminatedEvent(new stdClass(), new stdClass(), null, null);

        (new FlushTemporaryContainerInstances())->handleOperationTerminated($event);

        $this->assertInstanceOf(TaskTerminatedEvent::class, $event);
    }
}
