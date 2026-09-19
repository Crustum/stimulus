<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Support;

/**
 * In-memory Swoole server recording task calls.
 *
 * Stands in for the Swoole HTTP server in extension-gated dispatcher tests
 * so the suites load and skip cleanly where the extension is absent.
 */
class FakeTaskServer
{
    /**
     * Results returned from task waits, false on timeout.
     *
     * @var mixed
     */
    public mixed $taskResults = [];

    /**
     * Payloads received through dispatch.
     *
     * @var list<mixed>
     */
    public array $dispatched = [];

    /**
     * Payloads received through task waits.
     *
     * @var list<array<int|string, mixed>>
     */
    public array $waited = [];

    /**
     * Waits for background tasks and returns the canned results.
     *
     * @param array<int|string, mixed> $tasks Task payloads.
     * @param float $timeout Seconds to wait for the results.
     * @return mixed Canned task results.
     */
    public function taskWaitMulti(array $tasks, float $timeout): mixed
    {
        $this->waited[] = $tasks;

        return $this->taskResults;
    }

    /**
     * Records a dispatched background task.
     *
     * @param mixed $task Task payload.
     * @return void
     */
    public function task(mixed $task): void
    {
        $this->dispatched[] = $task;
    }
}
