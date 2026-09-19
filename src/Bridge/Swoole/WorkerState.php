<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Bridge\Swoole;

/**
 * Carries the Swoole worker state between server callbacks.
 *
 * It lives beside the Swoole bridge because the P8/P9 Swoole server entries consume
 * it, and the properties stay untyped-friendly mixed values since the Swoole
 * server, worker, client, and table objects only exist on extension hosts.
 */
class WorkerState
{
    /**
     * The Swoole HTTP server instance.
     *
     * @var mixed
     */
    public mixed $server = null;

    /**
     * ID of the current worker.
     *
     * @var int|null
     */
    public ?int $workerId = null;

    /**
     * Process ID of the current worker.
     *
     * @var int|null
     */
    public ?int $workerPid = null;

    /**
     * The Stimulus worker serving requests.
     *
     * @var mixed
     */
    public mixed $worker = null;

    /**
     * The client bridging the server and the worker.
     *
     * @var mixed
     */
    public mixed $client = null;

    /**
     * Table scheduling the tick callbacks.
     *
     * @var mixed
     */
    public mixed $timerTable = null;

    /**
     * Table backing the array cache.
     *
     * @var mixed
     */
    public mixed $cacheTable = null;

    /**
     * Additional Swoole tables shared with the worker.
     *
     * @var array<string, mixed>
     */
    public array $tables = [];

    /**
     * ID of the tick timer driving interval callbacks.
     *
     * @var int|null
     */
    public ?int $tickTimerId = null;

    /**
     * Timestamp of the last served request.
     *
     * @var float|null
     */
    public ?float $lastRequestTime = null;
}
