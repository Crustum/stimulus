<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Listener;

use Cake\Datasource\ConnectionManager;
use Cake\Http\ServerRequest;
use Crustum\Stimulus\Event\RequestReceivedEvent;
use Crustum\Stimulus\Listener\FlushDatabaseRecordModificationState;
use Crustum\Stimulus\Test\Support\RecordModificationStateTestConnection;
use Crustum\Stimulus\Test\TestCase;
use stdClass;

/**
 * Tests for the record modification state flushing listener.
 */
class FlushDatabaseRecordModificationStateTest extends TestCase
{
    /**
     * Connections exposing modification state have it forgotten.
     *
     * @return void
     */
    public function testModificationStateIsForgotten(): void
    {
        $connection = new RecordModificationStateTestConnection();
        ConnectionManager::setConfig('stimulus_test_record', $connection);

        $event = new RequestReceivedEvent(new stdClass(), new stdClass(), new ServerRequest(['url' => '/']));

        (new FlushDatabaseRecordModificationState(['stimulus_test_record', 'stimulus_test_missing']))->handleRequestReceived($event);

        $this->assertSame(1, $connection->forgets);
    }

    /**
     * Core connections without modification state are left untouched.
     *
     * @return void
     */
    public function testCoreConnectionsWithoutModificationStateAreUntouched(): void
    {
        ConnectionManager::setConfig('stimulus_test_record_plain', ['url' => 'sqlite:///:memory:']);

        $connection = ConnectionManager::get('stimulus_test_record_plain');
        $connection->execute('SELECT 1');

        $event = new RequestReceivedEvent(new stdClass(), new stdClass(), new ServerRequest(['url' => '/']));

        (new FlushDatabaseRecordModificationState(['stimulus_test_record_plain']))->handleRequestReceived($event);

        $this->assertTrue($connection->getDriver()->isConnected());
    }
}
