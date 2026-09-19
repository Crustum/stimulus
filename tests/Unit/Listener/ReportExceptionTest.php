<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Listener;

use Cake\Log\Engine\ArrayLog;
use Cake\Log\Log;
use Crustum\Stimulus\Event\WorkerErrorOccurredEvent;
use Crustum\Stimulus\Exception\DdException;
use Crustum\Stimulus\Listener\ReportException;
use Crustum\Stimulus\Test\TestCase;
use RuntimeException;
use stdClass;
use Throwable;

/**
 * Tests for the worker exception reporting listener.
 *
 * Covers the exception reporting scenario: operation exceptions are logged
 * and forwarded to the sandbox hook while dump-and-die exceptions stay
 * silent.
 */
class ReportExceptionTest extends TestCase
{
    /**
     * Operation exceptions are logged and forwarded to the sandbox hook.
     *
     * @return void
     */
    public function testExceptionIsLoggedAndForwarded(): void
    {
        Log::setConfig('stimulus_test_report', ['className' => ArrayLog::class]);

        $sandbox = new class {
            /**
             * Exceptions forwarded through the worker hook.
             *
             * @var list<\Throwable>
             */
            public array $reported = [];

            /**
             * Records the reported exception.
             *
             * @param \Throwable $exception Throwable caught by the worker.
             * @return void
             */
            public function reportException(Throwable $exception): void
            {
                $this->reported[] = $exception;
            }
        };

        try {
            $exception = new RuntimeException('worker failed');
            $event = new WorkerErrorOccurredEvent($exception, $sandbox);

            (new ReportException())->handleWorkerErrorOccurred($event);

            $this->assertSame([$exception], $sandbox->reported);

            $engine = Log::engine('stimulus_test_report');

            $this->assertInstanceOf(ArrayLog::class, $engine);
            $this->assertNotEmpty($engine->read());
            $this->assertStringContainsString('worker failed', implode("\n", $engine->read()));
        } finally {
            Log::drop('stimulus_test_report');
        }
    }

    /**
     * Dump-and-die exceptions are neither logged nor forwarded.
     *
     * @return void
     */
    public function testDdExceptionStaysSilent(): void
    {
        Log::setConfig('stimulus_test_report_silent', ['className' => ArrayLog::class]);

        $sandbox = new class {
            /**
             * Exceptions forwarded through the worker hook.
             *
             * @var list<\Throwable>
             */
            public array $reported = [];

            /**
             * Records the reported exception.
             *
             * @param \Throwable $exception Throwable caught by the worker.
             * @return void
             */
            public function reportException(Throwable $exception): void
            {
                $this->reported[] = $exception;
            }
        };

        try {
            $event = new WorkerErrorOccurredEvent(new DdException(['dumped']), $sandbox);

            (new ReportException())->handleWorkerErrorOccurred($event);

            $this->assertSame([], $sandbox->reported);

            $engine = Log::engine('stimulus_test_report_silent');

            $this->assertInstanceOf(ArrayLog::class, $engine);
            $this->assertSame([], $engine->read());
        } finally {
            Log::drop('stimulus_test_report_silent');
        }
    }

    /**
     * Sandboxes without the worker hook only get the log entry.
     *
     * @return void
     */
    public function testSandboxWithoutHookOnlyLogs(): void
    {
        $event = new WorkerErrorOccurredEvent(new RuntimeException('worker failed'), new stdClass());

        (new ReportException())->handleWorkerErrorOccurred($event);

        $this->assertInstanceOf(WorkerErrorOccurredEvent::class, $event);
    }
}
