<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Bridge\Swoole;

use Crustum\Stimulus\Bridge\Swoole\Actions\EnsureRequestsDontExceedMaxExecutionTime;
use Crustum\Stimulus\Bridge\Swoole\SwooleExtension;
use Crustum\Stimulus\Test\Support\FakeSwooleServer;
use Crustum\Stimulus\Test\Support\FakeTimerTable;
use Crustum\Stimulus\Test\TestCase;
use Mockery;

/**
 * Tests for the execution-time guard action.
 *
 * Covers the overdue-request scenario on the capability-checked action:
 * overdue workers lose their slot and receive the kill signal while fresh
 * rows, changed descriptors, and vanished connections stay untouched. The
 * timer table and server arrive as in-memory fakes so the suite loads
 * without the extension; every test skips cleanly where the extension is
 * absent.
 */
class EnsureRequestsDontExceedMaxExecutionTimeTest extends TestCase
{
    /**
     * Requires the Swoole extension before each test.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        if (!extension_loaded('swoole') && !extension_loaded('openswoole')) {
            $this->markTestSkipped('The Swoole or OpenSwoole extension is not installed on this host.');
        }
    }

    /**
     * Overdue requests lose their slot and receive the kill signal.
     *
     * @return void
     */
    public function testProcessIsKilledIfCurrentRequestExceedsMaxExecutionTime(): void
    {
        $table = new FakeTimerTable();

        $table['fake-worker-id'] = [
            'worker_pid' => 111,
            'time' => time() - 60,
            'fd' => 1,
        ];

        $action = new EnsureRequestsDontExceedMaxExecutionTime(
            $extension = Mockery::mock(SwooleExtension::class),
            $table,
            30,
        );

        $extension->shouldReceive('dispatchProcessSignal')->once()->with(111, SIGKILL);

        $action();

        $this->assertSame(['fake-worker-id'], $table->deleted);
    }

    /**
     * Fresh requests keep their slot without signals.
     *
     * @return void
     */
    public function testFreshRequestsAreIgnored(): void
    {
        $table = new FakeTimerTable();

        $table['fake-worker-id'] = [
            'worker_pid' => 111,
            'time' => time(),
            'fd' => 1,
        ];

        $action = new EnsureRequestsDontExceedMaxExecutionTime(
            $extension = Mockery::mock(SwooleExtension::class),
            $table,
            30,
        );

        $extension->shouldReceive('dispatchProcessSignal')->never();

        $action();

        $this->assertSame([], $table->deleted);
    }

    /**
     * Rows with changed descriptors keep their slot without signals.
     *
     * @return void
     */
    public function testChangedDescriptorsAreIgnored(): void
    {
        $table = new FakeTimerTable();

        $table['fake-worker-id'] = [
            'worker_pid' => 111,
            'time' => time() - 60,
            'fd' => 1,
        ];
        $table->fdValue = 2;

        $action = new EnsureRequestsDontExceedMaxExecutionTime(
            $extension = Mockery::mock(SwooleExtension::class),
            $table,
            30,
        );

        $extension->shouldReceive('dispatchProcessSignal')->never();

        $action();

        $this->assertSame([], $table->deleted);
    }

    /**
     * Vanished connections skip the kill signal after losing their slot.
     *
     * @return void
     */
    public function testVanishedConnectionsSkipTheKillSignal(): void
    {
        $table = new FakeTimerTable();

        $table['fake-worker-id'] = [
            'worker_pid' => 111,
            'time' => time() - 60,
            'fd' => 1,
        ];

        $action = new EnsureRequestsDontExceedMaxExecutionTime(
            $extension = Mockery::mock(SwooleExtension::class),
            $table,
            30,
            new FakeSwooleServer(),
        );

        $extension->shouldReceive('dispatchProcessSignal')->never();

        $action();

        $this->assertSame(['fake-worker-id'], $table->deleted);
    }
}
