<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Worker;

use Cake\Core\Configure;
use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Crustum\Stimulus\Event\RequestTerminatedEvent;
use Crustum\Stimulus\Listener\FlushTemporaryContainerInstances;
use Crustum\Stimulus\Test\TestCase;
use stdClass;

/**
 * Tests for temporary container instance flushing between operations.
 *
 * The listener drops the cached resolved instance of each configured binding
 * so the next operation resolves it fresh while the definition stays
 * registered.
 */
class TemporaryContainerInstanceFlushingTest extends TestCase
{
    /**
     * Flushed bindings resolve fresh on the next operation.
     *
     * @return void
     */
    public function testTemporaryContainerBindingsAreFlushed(): void
    {
        $previousFlush = Configure::read('Stimulus.flush');

        Configure::write('Stimulus.flush', ['probe-binding']);

        try {
            $app = $this->createApplication();
            $counter = 0;

            $app->getContainer()->addShared('probe-binding', function () use (&$counter): stdClass {
                $counter++;

                return new stdClass();
            });

            $first = $app->getContainer()->get('probe-binding');

            $event = new RequestTerminatedEvent(
                $app,
                $app,
                new ServerRequest(['url' => '/first']),
                new Response(),
            );

            (new FlushTemporaryContainerInstances())->handleOperationTerminated($event);

            $second = $app->getContainer()->get('probe-binding');

            $this->assertNotSame($first, $second);
            $this->assertSame(2, $counter);
        } finally {
            $this->restoreFlushConfig($previousFlush);
        }
    }

    /**
     * Unknown bindings pass through untouched.
     *
     * @return void
     */
    public function testUnknownBindingsAreIgnored(): void
    {
        $previousFlush = Configure::read('Stimulus.flush');

        Configure::write('Stimulus.flush', ['missing-binding', '', 42]);

        try {
            $app = $this->createApplication();
            $kept = new stdClass();

            $app->getContainer()->addShared('kept-binding', $kept);

            $event = new RequestTerminatedEvent(
                $app,
                $app,
                new ServerRequest(['url' => '/first']),
                new Response(),
            );

            (new FlushTemporaryContainerInstances())->handleOperationTerminated($event);

            $this->assertSame($kept, $app->getContainer()->get('kept-binding'));
        } finally {
            $this->restoreFlushConfig($previousFlush);
        }
    }

    /**
     * Restores the flush configuration from before the test.
     *
     * @param mixed $previousFlush Flush configuration to restore.
     * @return void
     */
    protected function restoreFlushConfig(mixed $previousFlush): void
    {
        if ($previousFlush === null) {
            Configure::delete('Stimulus.flush');
        } else {
            Configure::write('Stimulus.flush', $previousFlush);
        }
    }
}
