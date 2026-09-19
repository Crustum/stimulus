<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Bridge\Swoole\Actions;

use Crustum\Stimulus\Bridge\Swoole\SwooleExtension;
use Swoole\Http\Response as SwooleResponse;

/**
 * Kills requests exceeding the maximum execution time.
 *
 * Overdue timer rows lose their slot, vanish from live connections, and have
 * their worker process signalled, then the client connection closes with a
 * timeout status. The Swoole server and response stay untyped and resolve
 * behind capability checks, so this file loads where the extension is
 * absent; the action only runs on extension hosts.
 */
class EnsureRequestsDontExceedMaxExecutionTime
{
    /**
     * Creates a new execution-time guard.
     *
     * @param \Crustum\Stimulus\Bridge\Swoole\SwooleExtension $extension Extension helper sending the kill signals.
     * @param mixed $timerTable Swoole table tracking request start times per worker.
     * @param int $maxExecutionTime Maximum request execution time in seconds.
     * @param mixed $server Swoole HTTP server, or null when no server is available.
     */
    public function __construct(
        protected SwooleExtension $extension,
        protected mixed $timerTable,
        protected int $maxExecutionTime,
        protected mixed $server = null,
    ) {
    }

    /**
     * Invokes the action.
     *
     * Collects overdue rows first, then processes them in a two-pass flow.
     * Both passes iterate imperatively, looping over the timer rows instead
     * of collecting them.
     *
     * @return void
     */
    public function __invoke(): void
    {
        $rows = [];

        foreach ($this->timerTable as $workerId => $row) {
            if (time() - $row['time'] > $this->maxExecutionTime) {
                $rows[$workerId] = $row;
            }
        }

        foreach ($rows as $workerId => $row) {
            $this->killOverdueRequest($workerId, $row);
        }
    }

    /**
     * Kills a single overdue request.
     *
     * Skips rows whose file descriptor changed since collection and rows gone
     * from live connections. Swoole capability
     * checks keep every call safe on hosts without the extension.
     *
     * @param string|int $workerId Worker identifier behind the timer row.
     * @param array{worker_pid: int, time: int, fd: int} $row Timer row captured for the overdue request.
     * @return void
     */
    protected function killOverdueRequest(string|int $workerId, array $row): void
    {
        if (!is_object($this->timerTable) || !method_exists($this->timerTable, 'get')) {
            return;
        }

        if ($this->timerTable->get((string)$workerId, 'fd') !== $row['fd']) {
            return;
        }

        if (method_exists($this->timerTable, 'del')) {
            $this->timerTable->del((string)$workerId);
        }

        if ($this->serverGone($row)) {
            return;
        }

        $this->extension->dispatchProcessSignal((int)$row['worker_pid'], SIGKILL);

        $this->closeConnection($row);
    }

    /**
     * Determines whether the overdue connection already vanished from the server.
     *
     * @param array{worker_pid: int, time: int, fd: int} $row Timer row captured for the overdue request.
     * @return bool Whether the server no longer tracks the connection.
     */
    protected function serverGone(array $row): bool
    {
        return is_object($this->server) &&
            method_exists($this->server, 'exists') &&
            !$this->server->exists($row['fd']);
    }

    /**
     * Closes the overdue connection with a timeout status.
     *
     * Resolves the Swoole response class by name behind the extension guard
     * so the response creation never fatals where the extension is absent.
     *
     * @param array{worker_pid: int, time: int, fd: int} $row Timer row captured for the overdue request.
     * @return void
     */
    protected function closeConnection(array $row): void
    {
        if (!is_object($this->server)) {
            return;
        }

        $responseClass = SwooleResponse::class;

        if (!class_exists($responseClass)) {
            return;
        }

        $response = $responseClass::create($this->server, $row['fd']);

        if ($response) {
            $response->status(408);
            $response->end();
        }
    }
}
