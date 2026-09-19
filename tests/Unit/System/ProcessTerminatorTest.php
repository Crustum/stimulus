<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\System;

use Crustum\Stimulus\System\PosixExtension;
use Crustum\Stimulus\System\ProcessTerminator;
use Crustum\Stimulus\Test\TestCase;
use Symfony\Component\Process\Process;

/**
 * Tests for the cross-platform process terminator.
 *
 * Exercises real processes on the host: the current process is always alive,
 * an absurd process ID is never alive, and a spawned sleeper can be
 * terminated. Both branches (POSIX and Windows) run wherever they are
 * native, so no skips are needed.
 */
class ProcessTerminatorTest extends TestCase
{
    /**
     * The current process answers the liveness probe.
     *
     * @return void
     */
    public function testCurrentProcessIsRunning(): void
    {
        $this->assertTrue($this->terminator()->isRunning(getmypid()));
    }

    /**
     * A non-positive process ID is never running.
     *
     * @return void
     */
    public function testNonPositiveProcessIdIsNotRunning(): void
    {
        $terminator = $this->terminator();

        $this->assertFalse($terminator->isRunning(0));
        $this->assertFalse($terminator->isRunning(-1));
        $this->assertFalse($terminator->terminate(0));
    }

    /**
     * An absurd process ID answers no liveness probe.
     *
     * @return void
     */
    public function testAbsentProcessIsNotRunning(): void
    {
        $this->assertFalse($this->terminator()->isRunning(2147483647));
    }

    /**
     * A spawned sleeper terminates and stays gone.
     *
     * @return void
     */
    public function testSpawnedProcessTerminates(): void
    {
        $process = new Process([PHP_BINARY, '-r', 'sleep(60);']);

        $process->start();

        $processId = $process->getPid();

        $this->assertIsInt($processId);

        try {
            $this->assertTrue($this->terminator()->terminate($processId, 10));
            $this->assertFalse($this->terminator()->isRunning($processId));
        } finally {
            if ($process->isRunning()) {
                $process->stop(3);
            }
        }
    }

    /**
     * Creates a terminator with the real POSIX wrapper.
     *
     * @return \Crustum\Stimulus\System\ProcessTerminator Terminator under test.
     */
    protected function terminator(): ProcessTerminator
    {
        return new ProcessTerminator(new PosixExtension());
    }
}
