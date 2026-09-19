<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Support;

/**
 * In-memory Swoole HTTP server for lifecycle handler tests.
 *
 * Stands in for the Swoole HTTP server in extension-gated handler tests so
 * the suites load and skip cleanly where the extension is absent.
 */
class FakeSwooleServer
{
    /**
     * Master process ID.
     *
     * @var int
     */
    public int $master_pid = 111;

    /**
     * Manager process ID.
     *
     * @var int
     */
    public int $manager_pid = 222;

    /**
     * Server settings.
     *
     * @var array<string, mixed>
     */
    public array $setting = ['worker_num' => 4];

    /**
     * Payloads received through tasks.
     *
     * @var list<mixed>
     */
    public array $tasks = [];

    /**
     * Whether the shutdown was requested.
     *
     * @var bool
     */
    public bool $shutdown = false;

    /**
     * Live connection identifiers.
     *
     * @var list<int>
     */
    public array $liveConnections = [];

    /**
     * Dispatches a background task.
     *
     * @param mixed $data Task payload.
     * @return void
     */
    public function task(mixed $data): void
    {
        $this->tasks[] = $data;
    }

    /**
     * Determines whether the given connection exists.
     *
     * @param int $fd Connection file descriptor.
     * @return bool Whether the connection exists.
     */
    public function exists(int $fd): bool
    {
        return in_array($fd, $this->liveConnections, true);
    }

    /**
     * Records the server shutdown.
     *
     * @return void
     */
    public function shutdown(): void
    {
        $this->shutdown = true;
    }
}
