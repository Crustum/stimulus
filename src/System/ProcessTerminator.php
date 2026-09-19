<?php
declare(strict_types=1);

namespace Crustum\Stimulus\System;

use Symfony\Component\Process\Process;

/**
 * Cross-platform server process liveness and termination.
 *
 * POSIX signalling does not exist on Windows, so server commands cannot rely
 * on `PosixExtension` alone: liveness probes use `tasklist` and termination
 * uses `taskkill` there.
 * The POSIX branch sends TERM, then KILL after the timeout; the Windows branch kills
 * the process tree, waits for the exit, then forces the tree. Signal numbers fall back to their
 * well-known values where the pcntl constants are undefined.
 */
class ProcessTerminator
{
    /**
     * Seconds between liveness polls while waiting for termination.
     *
     * @var float
     */
    protected const POLL_INTERVAL = 0.5;

    /**
     * Creates a new process terminator.
     *
     * @param \Crustum\Stimulus\System\PosixExtension $posix POSIX wrapper used on non-Windows systems.
     */
    public function __construct(protected PosixExtension $posix)
    {
    }

    /**
     * Determines whether the given process is running.
     *
     * A terminated child that its parent has not reaped yet (zombie) still
     * answers POSIX signal zero, so on Linux the `/proc` state field breaks
     * the tie: zombies count as gone.
     *
     * @param int $processId ID of the process to probe.
     * @return bool Whether the process answers the liveness probe.
     */
    public function isRunning(int $processId): bool
    {
        if ($processId <= 0) {
            return false;
        }

        if (function_exists('posix_kill')) {
            return $this->posix->kill($processId, 0) && !$this->isZombie($processId);
        }

        return str_contains(
            $this->runProcess(['tasklist', '/FI', "PID eq {$processId}", '/FO', 'CSV', '/NH']),
            "\"{$processId}\"",
        );
    }

    /**
     * Terminates the given process, waiting up to the timeout for its exit.
     *
     * @param int $processId ID of the process to terminate.
     * @param int $timeout Seconds to wait for a graceful exit before forcing termination.
     * @return bool Whether the process is gone after the call.
     */
    public function terminate(int $processId, int $timeout = 10): bool
    {
        if ($processId <= 0) {
            return false;
        }

        if (function_exists('posix_kill')) {
            $this->posix->kill($processId, defined('SIGTERM') ? SIGTERM : 15);

            if ($this->waitForExit($processId, $timeout)) {
                return true;
            }

            $this->posix->kill($processId, defined('SIGKILL') ? SIGKILL : 9);

            return $this->waitForExit($processId, $timeout);
        }

        $this->runProcess(['taskkill', '/PID', (string)$processId, '/T']);

        if ($this->waitForExit($processId, $timeout)) {
            return true;
        }

        $this->runProcess(['taskkill', '/PID', (string)$processId, '/F', '/T']);

        return $this->waitForExit($processId, $timeout);
    }

    /**
     * Waits up to the timeout for the given process to exit.
     *
     * Polls imperatively like the supervisor loops it serves: each pass
     * sleeps half a second and re-probes until the deadline passes.
     *
     * @param int $processId ID of the process to wait for.
     * @param int $timeout Seconds to wait before giving up.
     * @return bool Whether the process exited in time.
     */
    protected function waitForExit(int $processId, int $timeout): bool
    {
        $deadline = time() + max(0, $timeout);

        while (time() <= $deadline) {
            if (!$this->isRunning($processId)) {
                return true;
            }

            usleep((int)(static::POLL_INTERVAL * 1000000));
        }

        return !$this->isRunning($processId);
    }

    /**
     * Determines whether the given process is an unreaped zombie.
     *
     * Reads the Linux `/proc` state field, which is the only reliable way to tell
     * a zombie apart through signal zero. Anything unreadable or malformed
     * counts as not-a-zombie so the signal-zero answer stands.
     *
     * @param int $processId ID of the process to inspect.
     * @return bool Whether the process is a zombie.
     */
    protected function isZombie(int $processId): bool
    {
        if (PHP_OS_FAMILY !== 'Linux') {
            return false;
        }

        $statFile = "/proc/{$processId}/stat";

        if (!is_readable($statFile)) {
            return false;
        }

        $stat = file_get_contents($statFile);

        if (!is_string($stat)) {
            return false;
        }

        $afterComm = strrchr($stat, ')');

        if ($afterComm === false) {
            return false;
        }

        $fields = preg_split('/\s+/', trim(substr($afterComm, 1)));

        return is_array($fields) && ($fields[0] ?? '') === 'Z';
    }

    /**
     * Runs the given system command and returns its combined output.
     *
     * Failures are reported through the output string so liveness parsing
     * stays total: a missing binary or a dead target simply yields no match.
     *
     * @param list<string> $command Command and arguments to run without a shell.
     * @return string Combined process output.
     */
    protected function runProcess(array $command): string
    {
        $process = new Process($command);

        $process->run();

        return $process->getOutput() . $process->getErrorOutput();
    }
}
