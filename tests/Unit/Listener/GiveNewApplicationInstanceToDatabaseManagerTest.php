<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Listener;

use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;
use Cake\Http\ServerRequest;
use Crustum\Stimulus\Event\RequestReceivedEvent;
use Crustum\Stimulus\Listener\GiveNewApplicationInstanceToDatabaseManager;
use Crustum\Stimulus\Test\TestCase;
use stdClass;

/**
 * Tests for the database manager refreshing listener.
 *
 * Covers the database manager state scenario: resolved connections are
 * forgotten while their configuration survives for the rebuild.
 */
class GiveNewApplicationInstanceToDatabaseManagerTest extends TestCase
{
    /**
     * Resolved connections are forgotten while their configuration survives.
     *
     * @return void
     */
    public function testResolvedConnectionsAreForgotten(): void
    {
        ConnectionManager::setConfig('stimulus_test_dbmanager', ['url' => 'sqlite:///:memory:']);

        $before = ConnectionManager::get('stimulus_test_dbmanager');
        $before->execute('SELECT 1');

        $this->assertTrue($before->getDriver()->isConnected());

        try {
            $event = new RequestReceivedEvent(new stdClass(), new stdClass(), new ServerRequest(['url' => '/']));

            $listener = new GiveNewApplicationInstanceToDatabaseManager(
                ['stimulus_test_dbmanager', 'stimulus_test_missing'],
            );
            $listener->handleRequestReceived($event);

            $after = ConnectionManager::get('stimulus_test_dbmanager');

            $this->assertNotSame($before, $after);
            $this->assertFalse($after->getDriver()->isConnected());
        } finally {
            ConnectionManager::drop('stimulus_test_dbmanager');
        }
    }

    /**
     * Null connection names fall back to the configured worker connections.
     *
     * @return void
     */
    public function testNullConnectionsFallBackToConfiguredList(): void
    {
        ConnectionManager::setConfig('stimulus_test_dbmanager_cfg', ['url' => 'sqlite:///:memory:']);
        Configure::write('Stimulus.listeners.connections', ['stimulus_test_dbmanager_cfg']);

        $before = ConnectionManager::get('stimulus_test_dbmanager_cfg');
        $before->execute('SELECT 1');

        $this->assertTrue($before->getDriver()->isConnected());

        try {
            $event = new RequestReceivedEvent(new stdClass(), new stdClass(), new ServerRequest(['url' => '/']));

            (new GiveNewApplicationInstanceToDatabaseManager())->handleRequestReceived($event);

            $after = ConnectionManager::get('stimulus_test_dbmanager_cfg');

            $this->assertNotSame($before, $after);
            $this->assertFalse($after->getDriver()->isConnected());
        } finally {
            ConnectionManager::drop('stimulus_test_dbmanager_cfg');
            Configure::delete('Stimulus.listeners.connections');
        }
    }
}
