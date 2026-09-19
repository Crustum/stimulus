<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Support;

use Cake\Collection\Collection;
use Crustum\Stimulus\Http\RequestContext;
use Crustum\Stimulus\Worker\Worker;

/**
 * In-memory test double for the long-lived worker.
 *
 * Extends the real worker so request, task, and tick replays exercise the
 * production boot, sandbox, gateway, and event paths. The fake client feeds
 * raw entries, which the replay helpers marshal exactly like the server
 * bridge clients do before delegating to the real handlers.
 *
 * @see \Crustum\Stimulus\Contracts\Worker
 */
class FakeWorker extends Worker
{
    /**
     * Replays every client request through the real request handler.
     *
     * Iterates imperatively over the client requests instead of collecting them.
     *
     * @return void
     */
    public function run(): void
    {
        $client = $this->client;

        assert($client instanceof FakeClient);

        foreach ($client->requests as $request) {
            [$marshalled, $context] = $client->marshalRequest(
                new RequestContext(['request' => $request]),
            );

            $this->handle($marshalled, $context);
        }
    }

    /**
     * Runs every client task payload through the real task handler.
     *
     * Maps every payload through the task handler and materializes the results.
     *
     * @return array<int|string, mixed> Task results or exception snapshots keyed by their given keys.
     */
    public function runTasks(): array
    {
        $client = $this->client;

        assert($client instanceof FakeClient);

        return (new Collection($client->requests))
            ->map(fn($data): mixed => $this->handleTask($data))
            ->toArray();
    }

    /**
     * Replays a tick for every client entry through the real tick handler.
     *
     * Maps every entry through the tick handler and materializes the results.
     *
     * @return array<int|string, null> Null tick results keyed by their given keys.
     */
    public function runTicks(): array
    {
        $client = $this->client;

        assert($client instanceof FakeClient);

        return (new Collection($client->requests))
            ->map(fn() => $this->handleTick())
            ->toArray();
    }
}
