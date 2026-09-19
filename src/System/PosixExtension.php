<?php
declare(strict_types=1);

namespace Crustum\Stimulus\System;

/**
 * Thin wrapper around the POSIX extension for signalling worker processes.
 *
 * Used to reload or stop server processes managed by the worker commands.
 */
class PosixExtension
{
    /**
     * Sends a signal to a given process using the POSIX extension.
     *
     * @param int $processId ID of the process to signal.
     * @param int $signal Signal number to send to the process.
     * @return bool Whether the signal was sent successfully.
     */
    public function kill(int $processId, int $signal): bool
    {
        return posix_kill($processId, $signal);
    }
}
