<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Listener;

use Cake\Core\Configure;
use Cake\Http\ServerRequest;
use Crustum\Stimulus\Event\RequestReceivedEvent;
use Crustum\Stimulus\Event\TaskReceivedEvent;
use Crustum\Stimulus\Event\TaskTerminatedEvent;
use Crustum\Stimulus\Event\TickReceivedEvent;
use Crustum\Stimulus\Event\TickTerminatedEvent;
use Crustum\Stimulus\Event\WorkerErrorOccurredEvent;
use Crustum\Stimulus\Listener\CreateConfigurationSandbox;
use Crustum\Stimulus\Test\TestCase;
use Exception;
use stdClass;

/**
 * Tests for the configuration sandbox listener.
 *
 * The worker-level configuration sandbox scenario lives in the worker suite,
 * which drives the full boot, snapshot, terminate, and restore flow through
 * the real worker.
 */
class CreateConfigurationSandboxTest extends TestCase
{
    /**
     * Configuration changed after the snapshot is restored.
     *
     * @return void
     */
    public function testConfigIsResetBetweenRequests(): void
    {
        $listener = new CreateConfigurationSandbox();
        $sandbox = new stdClass();
        $event = new RequestReceivedEvent(new stdClass(), $sandbox, new ServerRequest(['url' => '/']));

        $listener->handleRequestReceived($event);

        Configure::write('Stimulus.probe_key', 'changed');
        $this->assertSame('changed', Configure::read('Stimulus.probe_key'));

        $listener->restoreSandboxConfiguration($sandbox);

        $this->assertNull(Configure::read('Stimulus.probe_key'));
    }

    /**
     * Restoring an unknown sandbox leaves configuration untouched.
     *
     * @return void
     */
    public function testUnknownSandboxRestoreIsNoOp(): void
    {
        $listener = new CreateConfigurationSandbox();

        Configure::write('Stimulus.probe_key', 'kept');

        try {
            $listener->restoreSandboxConfiguration(new stdClass());

            $this->assertSame('kept', Configure::read('Stimulus.probe_key'));
        } finally {
            Configure::delete('Stimulus.probe_key');
        }
    }

    /**
     * Terminated operations restore the snapshot through the event.
     *
     * @return void
     */
    public function testTerminatedOperationRestoresSnapshot(): void
    {
        $listener = new CreateConfigurationSandbox();
        $app = new stdClass();
        $sandbox = new stdClass();
        $request = new ServerRequest(['url' => '/']);

        $listener->handleRequestReceived(new RequestReceivedEvent($app, $sandbox, $request));

        Configure::write('Stimulus.probe_key', 'changed');

        $listener->handleOperationTerminated(
            new TaskTerminatedEvent($app, $sandbox, null, null),
        );

        $this->assertNull(Configure::read('Stimulus.probe_key'));
    }

    /**
     * Task and tick operations snapshot and restore through their events.
     *
     * @return void
     */
    public function testTaskAndTickOperationsRestoreSnapshots(): void
    {
        $listener = new CreateConfigurationSandbox();
        $app = new stdClass();

        Configure::write('Stimulus.probe_key', 'original');

        try {
            $taskSandbox = new stdClass();

            $listener->handleTaskReceived(new TaskReceivedEvent($app, $taskSandbox, null));

            Configure::write('Stimulus.probe_key', 'task-changed');

            $listener->handleOperationTerminated(
                new TaskTerminatedEvent($app, $taskSandbox, null, null),
            );

            $this->assertSame('original', Configure::read('Stimulus.probe_key'));

            $tickSandbox = new stdClass();

            $listener->handleTickReceived(new TickReceivedEvent($app, $tickSandbox));

            Configure::write('Stimulus.probe_key', 'tick-changed');

            $listener->handleOperationTerminated(
                new TickTerminatedEvent($app, $tickSandbox),
            );

            $this->assertSame('original', Configure::read('Stimulus.probe_key'));
        } finally {
            Configure::delete('Stimulus.probe_key');
        }
    }

    /**
     * Failed operations restore the snapshot through the error event.
     *
     * @return void
     */
    public function testFailedOperationRestoresSnapshot(): void
    {
        $listener = new CreateConfigurationSandbox();
        $sandbox = new stdClass();

        $listener->handleRequestReceived(
            new RequestReceivedEvent(new stdClass(), $sandbox, new ServerRequest(['url' => '/'])),
        );

        Configure::write('Stimulus.probe_key', 'changed');

        $listener->handleWorkerErrorOccurred(
            new WorkerErrorOccurredEvent(new Exception('Something went wrong.'), $sandbox),
        );

        $this->assertNull(Configure::read('Stimulus.probe_key'));
    }
}
