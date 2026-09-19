<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Listener;

use Cake\Datasource\ConnectionManager;
use Crustum\Stimulus\Event\TaskTerminatedEvent;
use Crustum\Stimulus\Listener\DisconnectFromDatabases;
use Crustum\Stimulus\Test\TestCase;
use stdClass;

/**
 * Tests for the database disconnecting listener.
 */
class DisconnectFromDatabasesTest extends TestCase
{
    /**
     * Known connections are disconnected while missing names are skipped.
     *
     * @return void
     */
    public function testConnectionsAreDisconnected(): void
    {
        ConnectionManager::setConfig('stimulus_test_disconnect', ['url' => 'sqlite:///:memory:']);

        $connection = ConnectionManager::get('stimulus_test_disconnect');
        $connection->execute('SELECT 1');

        $this->assertTrue($connection->getDriver()->isConnected());

        $event = new TaskTerminatedEvent(new stdClass(), new stdClass(), null, null);

        (new DisconnectFromDatabases(['stimulus_test_disconnect', 'stimulus_test_missing']))->handleOperationTerminated($event);

        $this->assertFalse($connection->getDriver()->isConnected());
    }
}
