<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Listener;

use Cake\Datasource\ConnectionManager;
use Cake\Http\ServerRequest;
use Crustum\Stimulus\Event\RequestReceivedEvent;
use Crustum\Stimulus\Listener\FlushDatabaseQueryLog;
use Crustum\Stimulus\Test\TestCase;
use Psr\Log\AbstractLogger;
use stdClass;
use Stringable;

/**
 * Tests for the database query log flushing listener.
 */
class FlushDatabaseQueryLogTest extends TestCase
{
    /**
     * Query logger buffers exposing clear are cleared for known connections.
     *
     * @return void
     */
    public function testQueryLoggerBuffersAreCleared(): void
    {
        ConnectionManager::setConfig('stimulus_test_querylog', ['url' => 'sqlite:///:memory:']);

        $connection = ConnectionManager::get('stimulus_test_querylog');
        $logger = new class extends AbstractLogger {
            /**
             * Number of times the buffer was cleared.
             *
             * @var int
             */
            public int $clears = 0;

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
             * Records a buffer clear.
             *
             * @return void
             */
            public function clear(): void
            {
                $this->clears++;
            }
        };

        $connection->getDriver()->setLogger($logger);
        $connection->getDriver()->enableQueryLogging();

        $event = new RequestReceivedEvent(new stdClass(), new stdClass(), new ServerRequest(['url' => '/']));

        (new FlushDatabaseQueryLog(['stimulus_test_querylog', 'stimulus_test_missing']))->handleRequestReceived($event);

        $this->assertSame(1, $logger->clears);
    }

    /**
     * Core drivers without a clearable logger are left untouched.
     *
     * @return void
     */
    public function testCoreDriversWithoutClearableLoggerAreUntouched(): void
    {
        ConnectionManager::setConfig('stimulus_test_querylog_plain', ['url' => 'sqlite:///:memory:']);

        $connection = ConnectionManager::get('stimulus_test_querylog_plain');
        $connection->execute('SELECT 1');

        $event = new RequestReceivedEvent(new stdClass(), new stdClass(), new ServerRequest(['url' => '/']));

        (new FlushDatabaseQueryLog(['stimulus_test_querylog_plain']))->handleRequestReceived($event);

        $this->assertTrue($connection->getDriver()->isConnected());
    }
}
