<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Bridge\Swoole;

use Cake\Collection\Collection;
use Closure;
use Crustum\Stimulus\Contracts\DispatchesTasks;
use Crustum\Stimulus\Dispatch\SequentialTaskDispatcher;
use Crustum\Stimulus\Exception\TaskExceptionResult;
use Crustum\Stimulus\Exception\TaskTimeoutException;
use InvalidArgumentException;
use Laravel\SerializableClosure\SerializableClosure;

/**
 * Swoole task-worker dispatcher.
 *
 * Worker entries hand the dispatcher their Swoole server instance via injection.
 * Closures serialize through
 * `SerializableClosure`, and task payloads map through a
 * `Cake\Collection\Collection` pipeline. Without the extension the dispatcher
 * delegates to the sequential task dispatcher.
 */
class SwooleTaskDispatcher implements DispatchesTasks
{
    /**
     * Creates a new Swoole task dispatcher.
     *
     * @param mixed $server Swoole HTTP server or a callback resolving it, or null outside a server context.
     */
    public function __construct(protected mixed $server = null)
    {
    }

    /**
     * Concurrently resolves the given callbacks via background tasks, returning the results.
     *
     * Results will be keyed by their given keys - if a task did not finish, the tasks value will be "false".
     *
     * Maps results back onto task keys imperatively, looping over the tasks
     * with a positional cursor instead of collecting them.
     *
     * @param array<int|string, callable> $tasks Callbacks to resolve concurrently.
     * @param int $waitMilliseconds Milliseconds to wait for the tasks to finish.
     * @return array<int|string, mixed> Resolved task results keyed by their given keys.
     * @throws \Crustum\Stimulus\Exception\TaskException When a background task raises an exception.
     * @throws \Crustum\Stimulus\Exception\TaskTimeoutException When a background task exceeds its time limit.
     * @throws \InvalidArgumentException When tasks resolve outside a Swoole server context.
     */
    public function resolve(array $tasks, int $waitMilliseconds = 3000): array
    {
        if (!$this->swooleAvailable()) {
            return (new SequentialTaskDispatcher())->resolve($tasks, $waitMilliseconds);
        }

        $server = $this->server();

        if (!is_object($server) || !method_exists($server, 'taskWaitMulti')) {
            throw new InvalidArgumentException('Tasks can only be resolved within a Swoole server context / web request.');
        }

        $payload = (new Collection($tasks))
            ->map(fn(mixed $task): mixed => $task instanceof Closure ? new SerializableClosure($task) : $task)
            ->toArray();

        $results = $server->taskWaitMulti($payload, $waitMilliseconds / 1000);

        if ($results === false) {
            throw TaskTimeoutException::after($waitMilliseconds);
        }

        $i = 0;

        foreach (array_keys($tasks) as $key) {
            if (isset($results[$i])) {
                if ($results[$i] instanceof TaskExceptionResult) {
                    throw $results[$i]->getOriginal();
                }

                $tasks[$key] = $results[$i]->result;
            } else {
                $tasks[$key] = false;
            }

            $i++;
        }

        return $tasks;
    }

    /**
     * Concurrently dispatches the given callbacks via background tasks.
     *
     * @param array<int|string, callable> $tasks Callbacks to dispatch concurrently.
     * @return void
     * @throws \InvalidArgumentException When tasks dispatch outside a Swoole server context.
     */
    public function dispatch(array $tasks): void
    {
        if (!$this->swooleAvailable()) {
            (new SequentialTaskDispatcher())->dispatch($tasks);

            return;
        }

        $server = $this->server();

        if (!is_object($server) || !method_exists($server, 'task')) {
            throw new InvalidArgumentException('Tasks can only be dispatched within a Swoole server context / web request.');
        }

        $payload = (new Collection($tasks))
            ->map(fn(mixed $task): mixed => $task instanceof Closure ? new SerializableClosure($task) : $task)
            ->toArray();

        (new Collection($payload))
            ->each(function (mixed $task) use ($server): void {
                $server->task($task);
            });
    }

    /**
     * Resolves the Swoole server behind the dispatcher.
     *
     * Unwraps the server callback when one was injected, so worker entries
     * can defer the lookup until dispatch time.
     *
     * @return mixed Swoole HTTP server or null outside a server context.
     */
    protected function server(): mixed
    {
        if ($this->server instanceof Closure) {
            return ($this->server)();
        }

        return $this->server;
    }

    /**
     * Determines whether the Swoole runtime is available.
     *
     * @return bool Whether the Swoole or OpenSwoole extension is loaded.
     */
    protected function swooleAvailable(): bool
    {
        return extension_loaded('swoole') || extension_loaded('openswoole');
    }
}
