<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Bridge\Swoole;

/**
 * Dispatches signals to the Swoole server processes.
 *
 * Pulled into the command phase as a dependency of the Swoole server
 * inspector; the remaining Swoole bridge lands in P9.
 */
class SignalDispatcher
{
    /**
     * Creates a new signal dispatcher.
     *
     * @param \Crustum\Stimulus\Bridge\Swoole\SwooleExtension $extension Extension helper sending the signals.
     */
    public function __construct(protected SwooleExtension $extension)
    {
    }

    /**
     * Determines if the given process ID can be communicated with.
     *
     * @param int $processId ID of the process to probe.
     * @return bool Whether the process answers signals.
     */
    public function canCommunicateWith(int $processId): bool
    {
        return $this->signal($processId, 0);
    }

    /**
     * Sends a SIGTERM signal to the given process.
     *
     * Repeats the signal until the process stops answering or the wait budget
     * runs out.
     *
     * @param int $processId ID of the process to terminate.
     * @param int $wait Seconds to wait for the process to stop.
     * @return bool Whether the process stopped within the wait budget.
     */
    public function terminate(int $processId, int $wait = 0): bool
    {
        $this->extension->dispatchProcessSignal($processId, SIGTERM);

        if ($wait !== 0) {
            $start = time();

            do {
                if (! $this->canCommunicateWith($processId)) {
                    return true;
                }

                $this->extension->dispatchProcessSignal($processId, SIGTERM);

                sleep(1);
            } while (time() < $start + $wait);
        }

        return false;
    }

    /**
     * Sends a signal to the given process.
     *
     * @param int $processId ID of the process to signal.
     * @param int $signal Signal number to send to the process.
     * @return bool Whether the signal was sent successfully.
     */
    public function signal(int $processId, int $signal): bool
    {
        return $this->extension->dispatchProcessSignal($processId, $signal);
    }
}
