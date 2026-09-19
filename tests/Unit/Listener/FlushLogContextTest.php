<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Listener;

use Cake\Http\ServerRequest;
use Cake\Log\Log;
use Crustum\Stimulus\Event\RequestReceivedEvent;
use Crustum\Stimulus\Listener\FlushLogContext;
use Crustum\Stimulus\Test\TestCase;
use Psr\Log\AbstractLogger;
use stdClass;
use Stringable;

/**
 * Tests for the log context flushing listener.
 *
 * Engines exposing shared context hooks have them flushed while unknown
 * channels are skipped.
 */
class FlushLogContextTest extends TestCase
{
    /**
     * Shared and per-request context hooks are flushed in order.
     *
     * @return void
     */
    public function testSharedContextIsFlushed(): void
    {
        $logger = new class extends AbstractLogger {
            /**
             * Number of shared context flushes.
             *
             * @var int
             */
            public int $sharedFlushes = 0;

            /**
             * Number of context drops.
             *
             * @var int
             */
            public int $contextDrops = 0;

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
             * Records a shared context flush.
             *
             * @return void
             */
            public function flushSharedContext(): void
            {
                $this->sharedFlushes++;
            }

            /**
             * Records a context drop.
             *
             * @return void
             */
            public function withoutContext(): void
            {
                $this->contextDrops++;
            }
        };

        Log::setConfig('stimulus_test_logcontext', $logger);

        try {
            $event = new RequestReceivedEvent(new stdClass(), new stdClass(), new ServerRequest(['url' => '/']));

            (new FlushLogContext(['stimulus_test_logcontext', 'stimulus_test_missing']))->handleRequestReceived($event);

            $this->assertSame(1, $logger->sharedFlushes);
            $this->assertSame(1, $logger->contextDrops);
        } finally {
            Log::reset();
        }
    }
}
