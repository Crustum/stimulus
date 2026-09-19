<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Listener;

use Cake\Http\ServerRequest;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Crustum\Stimulus\Event\RequestReceivedEvent;
use Crustum\Stimulus\Listener\FlushTableLocatorState;
use Crustum\Stimulus\Test\TestCase;
use stdClass;

/**
 * Tests for the table locator flushing listener.
 *
 * Proves cached table objects are dropped from the global locator per
 * operation, so a long-lived worker rebuilds them lazily on next use.
 */
class FlushTableLocatorStateTest extends TestCase
{
    /**
     * Cached table objects are dropped while the locator survives.
     *
     * @return void
     */
    public function testCachedTablesAreDropped(): void
    {
        $locator = TableRegistry::getTableLocator();
        $table = new Table(['alias' => 'StimulusLocatorProbe']);
        $locator->set('StimulusLocatorProbe', $table);

        $this->assertSame($table, $locator->get('StimulusLocatorProbe'));

        try {
            $event = new RequestReceivedEvent(new stdClass(), new stdClass(), new ServerRequest(['url' => '/']));

            (new FlushTableLocatorState())->handleRequestReceived($event);

            $this->assertNotSame($table, $locator->get('StimulusLocatorProbe'));
        } finally {
            $locator->remove('StimulusLocatorProbe');
        }
    }
}
