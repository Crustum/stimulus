<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Process;

use Symfony\Component\Process\Process;

/**
 * Factory creating Symfony process instances for server binaries.
 *
 * Centralizes process construction so worker commands can build
 * server processes for RoadRunner, FrankenPHP, and Swoole binaries.
 */
class SymfonyProcessFactory
{
    /**
     * Creates a new Symfony process instance.
     *
     * @param array<int, string> $command Command and arguments to run.
     * @param string|null $cwd Working directory for the process, or null to inherit.
     * @param array<string, string>|null $env Environment variables for the process, or null to inherit.
     * @param mixed $input Input streamed to the process stdin.
     * @param float|null $timeout Seconds before the process is terminated, or null to disable.
     * @return \Symfony\Component\Process\Process New process instance for the given command.
     */
    public function createProcess(
        array $command,
        ?string $cwd = null,
        ?array $env = null,
        mixed $input = null,
        ?float $timeout = 60,
    ): Process {
        return new Process($command, $cwd, $env, $input, $timeout);
    }
}
