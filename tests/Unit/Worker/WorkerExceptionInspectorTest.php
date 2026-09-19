<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Worker;

use Crustum\Stimulus\Test\TestCase;
use Crustum\Stimulus\Worker\WorkerExceptionInspector;
use Exception;

/**
 * Tests for the worker exception inspector.
 *
 * The inspector replays remote task-exception identity for client display
 * without the Whoops dependency, so this suite asserts the replayed class
 * and trace win over the local throwable identity.
 */
class WorkerExceptionInspectorTest extends TestCase
{
    /**
     * The inspector replays the original class and trace.
     *
     * @return void
     */
    public function testReplaysOriginalIdentity(): void
    {
        $throwable = new Exception('Something went wrong.');
        $trace = [['file' => '/remote/task.php', 'line' => 42]];

        $inspector = new WorkerExceptionInspector($throwable, 'Remote\\Failure', $trace);

        $this->assertSame($throwable, $inspector->getThrowable());
        $this->assertSame('Remote\\Failure', $inspector->getExceptionName());
        $this->assertSame($trace, $inspector->getTrace());
        $this->assertSame($trace, $inspector->getTrace($throwable));
    }
}
