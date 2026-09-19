<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Listener;

use Cake\Database\Log\QueryLogger;
use Cake\Datasource\ConnectionManager;
use Cake\Http\ServerRequest;
use Crustum\Stimulus\Event\RequestReceivedEvent;
use Crustum\Stimulus\Listener\RefreshQueryDurationHandling;
use Crustum\Stimulus\Test\TestCase;
use stdClass;

/**
 * Tests for the query duration refreshing listener.
 *
 * Covers the query duration scenario: logging connections get a fresh query
 * logger while silent connections and missing names pass through untouched.
 */
class RefreshQueryDurationHandlingTest extends TestCase
{
    /**
     * Logging connections get a fresh query logger.
     *
     * @return void
     */
    public function testLoggingConnectionsGetFreshLogger(): void
    {
        ConnectionManager::setConfig('stimulus_test_durations', ['url' => 'sqlite:///:memory:']);

        $connection = ConnectionManager::get('stimulus_test_durations');
        $connection->getDriver()->setLogger(new QueryLogger());
        $connection->execute('SELECT 1');

        $before = $connection->getDriver()->getLogger();

        try {
            $event = new RequestReceivedEvent(new stdClass(), new stdClass(), new ServerRequest(['url' => '/']));

            $listener = new RefreshQueryDurationHandling(
                ['stimulus_test_durations', 'stimulus_test_missing'],
            );
            $listener->handleRequestReceived($event);

            $after = $connection->getDriver()->getLogger();

            $this->assertInstanceOf(QueryLogger::class, $after);
            $this->assertNotSame($before, $after);
        } finally {
            ConnectionManager::drop('stimulus_test_durations');
        }
    }

    /**
     * Connections without a logger keep their driver untouched.
     *
     * @return void
     */
    public function testSilentConnectionsAreUntouched(): void
    {
        ConnectionManager::setConfig('stimulus_test_silent', ['url' => 'sqlite:///:memory:']);

        $connection = ConnectionManager::get('stimulus_test_silent');
        $connection->execute('SELECT 1');

        try {
            $event = new RequestReceivedEvent(new stdClass(), new stdClass(), new ServerRequest(['url' => '/']));

            (new RefreshQueryDurationHandling(['stimulus_test_silent']))->handleRequestReceived($event);

            $this->assertNull($connection->getDriver()->getLogger());
        } finally {
            ConnectionManager::drop('stimulus_test_silent');
        }
    }
}
