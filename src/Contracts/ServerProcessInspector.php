<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Contracts;

/**
 * Contract for inspectors controlling the worker server process.
 */
interface ServerProcessInspector
{
    /**
     * Determine if the server process is running.
     *
     * @return bool Whether the server process is running.
     */
    public function serverIsRunning(): bool;

    /**
     * Reload the workers.
     *
     * @return void
     */
    public function reloadServer(): void;

    /**
     * Stop the server.
     *
     * @return bool Whether the server was stopped.
     */
    public function stopServer(): bool;
}
