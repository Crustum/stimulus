<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Listener;

use Cake\Http\ServerRequest;
use Cake\Log\Engine\ArrayLog;
use Cake\Log\Log;
use Crustum\Stimulus\Event\RequestReceivedEvent;
use Crustum\Stimulus\Listener\FlushMonologState;
use Crustum\Stimulus\Test\TestCase;
use Psr\Log\AbstractLogger;
use stdClass;
use Stringable;

/**
 * Tests for the logger state flushing listener.
 *
 * Resettable loggers are reset and the core in-memory array logger is cleared.
 */
class FlushMonologStateTest extends TestCase
{
    /**
     * Resettable loggers are reset for known channels.
     *
     * @return void
     */
    public function testLoggersAreReset(): void
    {
        $logger = new class extends AbstractLogger {
            /**
             * Number of resets.
             *
             * @var int
             */
            public int $resets = 0;

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
             * Records a reset.
             *
             * @return void
             */
            public function reset(): void
            {
                $this->resets++;
            }
        };

        Log::setConfig('stimulus_test_monolog', $logger);

        try {
            $event = new RequestReceivedEvent(new stdClass(), new stdClass(), new ServerRequest(['url' => '/']));

            (new FlushMonologState(['stimulus_test_monolog', 'stimulus_test_missing']))->handleRequestReceived($event);

            $this->assertSame(1, $logger->resets);
        } finally {
            Log::reset();
        }
    }

    /**
     * The core in-memory array logger is cleared.
     *
     * @return void
     */
    public function testArrayLoggerIsCleared(): void
    {
        Log::setConfig('stimulus_test_arraylog', ['className' => ArrayLog::class]);

        try {
            $engine = Log::engine('stimulus_test_arraylog');
            $this->assertInstanceOf(ArrayLog::class, $engine);

            $engine->log('debug', 'stale-message');

            $this->assertNotEmpty($engine->read());

            $event = new RequestReceivedEvent(new stdClass(), new stdClass(), new ServerRequest(['url' => '/']));

            (new FlushMonologState(['stimulus_test_arraylog']))->handleRequestReceived($event);

            $this->assertSame([], $engine->read());
        } finally {
            Log::reset();
        }
    }
}
