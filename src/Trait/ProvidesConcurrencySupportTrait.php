<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Trait;

use Crustum\Stimulus\Contracts\DispatchesTasks;
use Crustum\Stimulus\Dispatch\SequentialTaskDispatcher;

/**
 * Resolves callbacks concurrently via background task dispatchers.
 *
 * Container-bound Swoole dispatchers become an injected dispatcher with the
 * sequential dispatcher as the fallback. There is no container, so Swoole
 * dispatchers activate only through explicit injection by worker entries
 * holding the server, which keeps resolution outside a server context on the
 * sequential path instead of throwing.
 */
trait ProvidesConcurrencySupportTrait
{
    /**
     * Dispatcher resolving background tasks, null for the default resolution.
     *
     * @var \Crustum\Stimulus\Contracts\DispatchesTasks|null
     */
    protected ?DispatchesTasks $dispatcher = null;

    /**
     * Concurrently resolves the given callbacks via background tasks, returning the results.
     *
     * Results will be keyed by their given keys - if a task did not finish, the tasks value will be "false".
     *
     * Returns early for empty task lists instead of involving a dispatcher.
     *
     * @param array<int|string, callable> $tasks Callbacks to resolve concurrently.
     * @param int $waitMilliseconds Milliseconds to wait for the tasks to finish.
     * @return array<int|string, mixed> Resolved task results keyed by their given keys.
     * @throws \Crustum\Stimulus\Exception\TaskException When a background task raises an exception.
     * @throws \Crustum\Stimulus\Exception\TaskTimeoutException When a background task exceeds its time limit.
     */
    public function concurrently(array $tasks, int $waitMilliseconds = 3000): array
    {
        if ($tasks === []) {
            return [];
        }

        return $this->tasks()->resolve($tasks, $waitMilliseconds);
    }

    /**
     * Gets the task dispatcher.
     *
     * An injected dispatcher wins; otherwise resolution falls back to the
     * sequential dispatcher. Server-bound Swoole dispatchers arrive through
     * injection from the Swoole worker entries.
     *
     * @return \Crustum\Stimulus\Contracts\DispatchesTasks Dispatcher resolving background tasks.
     */
    public function tasks(): DispatchesTasks
    {
        if ($this->dispatcher !== null) {
            return $this->dispatcher;
        }

        return new SequentialTaskDispatcher();
    }
}
