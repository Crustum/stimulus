<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Support;

use Crustum\Stimulus\Contracts\ServerProcessInspector;

/**
 * Controllable server process inspector for command tests.
 *
 * Stands in for the per-driver inspectors so the lifecycle commands can be
 * exercised without spawning real servers.
 */
class FakeServerProcessInspector implements ServerProcessInspector
{
    /**
     * Whether the server reports as running.
     *
     * @var bool
     */
    public bool $running = false;

    /**
     * Whether stopping the server reports success.
     *
     * @var bool
     */
    public bool $stopResult = true;

    /**
     * Number of reload calls received.
     *
     * @var int
     */
    public int $reloads = 0;

    /**
     * Number of stop calls received.
     *
     * @var int
     */
    public int $stops = 0;

    /**
     * Determines if the server process is running.
     *
     * @return bool Whether the fake server is running.
     */
    public function serverIsRunning(): bool
    {
        return $this->running;
    }

    /**
     * Reloads the workers.
     *
     * @return void
     */
    public function reloadServer(): void
    {
        $this->reloads++;
    }

    /**
     * Stops the server.
     *
     * @return bool Whether stopping the fake server succeeded.
     */
    public function stopServer(): bool
    {
        $this->stops++;

        return $this->stopResult;
    }
}
