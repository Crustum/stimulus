<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Listener;

use Cake\Http\ServerRequest;
use Cake\Log\Engine\ArrayLog;
use Cake\Log\Log;
use Crustum\Stimulus\Event\RequestReceivedEvent;
use Crustum\Stimulus\Listener\GiveNewApplicationInstanceToLogManager;
use Crustum\Stimulus\Test\TestCase;
use stdClass;

/**
 * Tests for the log manager refreshing listener.
 *
 * Covers the log manager state scenario: resolved engines are forgotten
 * while their configuration survives for the rebuild.
 */
class GiveNewApplicationInstanceToLogManagerTest extends TestCase
{
    /**
     * Resolved engines are forgotten while their configuration survives.
     *
     * @return void
     */
    public function testResolvedEnginesAreForgotten(): void
    {
        Log::setConfig('stimulus_test_log', ['className' => ArrayLog::class]);

        $before = Log::engine('stimulus_test_log');
        Log::write('error', 'stale entry');

        try {
            $event = new RequestReceivedEvent(new stdClass(), new stdClass(), new ServerRequest(['url' => '/']));

            (new GiveNewApplicationInstanceToLogManager(['stimulus_test_log']))->handleRequestReceived($event);

            $this->assertContains('stimulus_test_log', Log::configured());

            $after = Log::engine('stimulus_test_log');

            $this->assertInstanceOf(ArrayLog::class, $after);
            $this->assertNotSame($before, $after);
            $this->assertSame([], $after->read());
        } finally {
            Log::drop('stimulus_test_log');
        }
    }

    /**
     * Missing engines are skipped without touching the configured ones.
     *
     * @return void
     */
    public function testMissingEnginesAreSkipped(): void
    {
        Log::setConfig('stimulus_test_log_kept', ['className' => ArrayLog::class]);

        try {
            $event = new RequestReceivedEvent(new stdClass(), new stdClass(), new ServerRequest(['url' => '/']));

            (new GiveNewApplicationInstanceToLogManager(['stimulus_test_missing']))->handleRequestReceived($event);

            $this->assertContains('stimulus_test_log_kept', Log::configured());
        } finally {
            Log::drop('stimulus_test_log_kept');
        }
    }
}
