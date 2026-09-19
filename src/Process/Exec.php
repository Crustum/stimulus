<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Process;

/**
 * Thin wrapper around the PHP exec function for shelling out to binaries.
 *
 * Used by server process inspectors and binary finders to probe
 * the host for worker server executables.
 */
class Exec
{
    /**
     * Runs the given command and returns its output lines.
     *
     * @param string $command Shell command to run.
     * @return array<int, string> Output lines produced by the command.
     */
    public function run(string $command): array
    {
        exec($command, $output);

        return $output;
    }
}
