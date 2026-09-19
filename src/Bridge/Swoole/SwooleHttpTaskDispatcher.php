<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Bridge\Swoole;

use Cake\Collection\Collection;
use Cake\Http\Client as HttpClient;
use Closure;
use Crustum\Stimulus\Contracts\DispatchesTasks;
use Crustum\Stimulus\Exception\TaskExceptionResult;
use Crustum\Stimulus\Exception\TaskTimeoutException;
use Exception;
use Laravel\SerializableClosure\SerializableClosure;
use Throwable;

/**
 * Swoole HTTP task dispatcher.
 *
 * Tasks-over-HTTP fallback path. An injected HTTP callable defaulting to the
 * HTTP client sends the requests, and payloads travel base64-encoded
 * since no shared encryption service is in scope; the
 * worker routes live at `/stimulus/resolve-tasks` and `/stimulus/dispatch-tasks`.
 */
class SwooleHttpTaskDispatcher implements DispatchesTasks
{
    /**
     * Creates a new Swoole HTTP task dispatcher.
     *
     * @param string $host Task server host.
     * @param string $port Task server port.
     * @param \Crustum\Stimulus\Contracts\DispatchesTasks $fallbackDispatcher Dispatcher used when the task server is unreachable.
     * @param mixed $httpClient Callable posting payloads and returning status and body pairs, or null for the HTTP client.
     */
    public function __construct(
        protected string $host,
        protected string $port,
        protected DispatchesTasks $fallbackDispatcher,
        protected mixed $httpClient = null,
    ) {
    }

    /**
     * Concurrently resolves the given callbacks via background tasks, returning the results.
     *
     * Results will be keyed by their given keys - if a task did not finish, the tasks value will be "false".
     *
     * @param array<int|string, callable> $tasks Callbacks to resolve concurrently.
     * @param int $waitMilliseconds Milliseconds to wait for the tasks to finish.
     * @return array<int|string, mixed> Resolved task results keyed by their given keys.
     * @throws \Crustum\Stimulus\Exception\TaskException When a background task raises an exception.
     * @throws \Crustum\Stimulus\Exception\TaskTimeoutException When a background task exceeds its time limit.
     */
    public function resolve(array $tasks, int $waitMilliseconds = 3000): array
    {
        $payload = (new Collection($tasks))
            ->map(fn(mixed $task): mixed => $task instanceof Closure ? new SerializableClosure($task) : $task)
            ->toArray();

        try {
            $response = $this->post("http://{$this->host}:{$this->port}/stimulus/resolve-tasks", [
                'tasks' => base64_encode(serialize($payload)),
                'wait' => $waitMilliseconds,
            ]);
        } catch (Throwable) {
            return $this->fallbackDispatcher->resolve($tasks, $waitMilliseconds);
        }

        return match ($response['status']) {
            200 => $this->resolved($response['body'], $tasks, $waitMilliseconds),
            504 => throw TaskTimeoutException::after($waitMilliseconds),
            default => throw TaskExceptionResult::from(
                new Exception('Invalid response from task server.'),
            )->getOriginal(),
        };
    }

    /**
     * Concurrently dispatches the given callbacks via background tasks.
     *
     * @param array<int|string, callable> $tasks Callbacks to dispatch concurrently.
     * @return void
     */
    public function dispatch(array $tasks): void
    {
        $payload = (new Collection($tasks))
            ->map(fn(mixed $task): mixed => $task instanceof Closure ? new SerializableClosure($task) : $task)
            ->toArray();

        try {
            $this->post("http://{$this->host}:{$this->port}/stimulus/dispatch-tasks", [
                'tasks' => base64_encode(serialize($payload)),
            ]);
        } catch (Throwable) {
            $this->fallbackDispatcher->dispatch($tasks);
        }
    }

    /**
     * Decodes a successful task server response body.
     *
     * Falls back to the sequential dispatcher when the body carries no task
     * map, keeping non-array payloads from escaping the array contract.
     *
     * @param string $body Response body from the task server.
     * @param array<int|string, callable> $tasks Callbacks sent to the task server.
     * @param int $waitMilliseconds Milliseconds to wait for the tasks to finish.
     * @return array<int|string, mixed> Resolved task results keyed by their given keys.
     */
    protected function resolved(string $body, array $tasks, int $waitMilliseconds): array
    {
        $results = unserialize($body);

        if (!is_array($results)) {
            return $this->fallbackDispatcher->resolve($tasks, $waitMilliseconds);
        }

        return $results;
    }

    /**
     * Posts the given payload through the HTTP client.
     *
     * Normalizes the callable result into a status and body pair so custom
     * test doubles stay free to return partial shapes.
     *
     * @param string $url Task server endpoint.
     * @param array<string, mixed> $data Payload posted to the task server.
     * @return array{status: int, body: string} Response status and body pair.
     */
    protected function post(string $url, array $data): array
    {
        $client = $this->httpClient;

        if (!is_callable($client)) {
            $client = $this->defaultHttpClient();
        }

        $response = $client($url, $data);

        if (!is_array($response)) {
            return ['status' => 0, 'body' => ''];
        }

        return [
            'status' => (int)($response['status'] ?? 0),
            'body' => (string)($response['body'] ?? ''),
        ];
    }

    /**
     * Builds the default HTTP client.
     *
     * Transport failures throw so callers fall back to the sequential
     * dispatcher.
     *
     * @return callable Callable posting payloads and returning status and body pairs.
     */
    protected function defaultHttpClient(): callable
    {
        return function (string $url, array $data): array {
            $response = (new HttpClient())->post($url, $data);

            return [
                'status' => $response->getStatusCode(),
                'body' => (string)$response->getBody(),
            ];
        };
    }
}
