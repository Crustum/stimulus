<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Listener;

use Cake\Log\Log;
use Crustum\Stimulus\Event\WorkerStoppingEvent;
use Crustum\Stimulus\Listener\CloseMonologHandlers;
use Crustum\Stimulus\Test\TestCase;
use Psr\Log\AbstractLogger;
use stdClass;
use Stringable;

/**
 * Tests for the log handler closing listener.
 *
 * Engines exposing a close hook have it closed once the worker stops.
 */
class CloseMonologHandlersTest extends TestCase
{
    /**
     * Handlers are closed after worker termination.
     *
     * @return void
     */
    public function testHandlersAreClosedAfterWorkerTermination(): void
    {
        $logger = new class extends AbstractLogger {
            /**
             * Number of closes.
             *
             * @var int
             */
            public int $closes = 0;

            /**
             * Records the logged message.
             *
             * @param mixed $level Log level.
             * @param \Stringable|string $message Log message.
             * @param array<string, mixed> $context Log context.
             * @return void
             */
            public function log($level, string|Stringable $message, array $context = []): void
            {
            }

            /**
             * Records a close.
             *
             * @return void
             */
            public function close(): void
            {
                $this->closes++;
            }
        };

        Log::setConfig('stimulus_test_close', $logger);

        try {
            $event = new WorkerStoppingEvent(new stdClass());

            (new CloseMonologHandlers(['stimulus_test_close', 'stimulus_test_missing']))->handleWorkerStopping($event);

            $this->assertSame(1, $logger->closes);
        } finally {
            Log::reset();
        }
    }
}
