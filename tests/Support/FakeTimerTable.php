<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Support;

use ArrayObject;

/**
 * In-memory timer table tracking request start times per worker.
 *
 * Stands in for the Swoole timer table in extension-gated execution-time
 * tests so the suites load and skip cleanly where the extension is absent.
 *
 * @extends \ArrayObject<string|int, array{worker_pid: int, time: int, fd: int}>
 */
class FakeTimerTable extends ArrayObject
{
    /**
     * Worker identifiers removed through deletes.
     *
     * @var list<string|int>
     */
    public array $deleted = [];

    /**
     * File descriptor returned from field reads.
     *
     * @var int
     */
    public int $fdValue = 1;

    /**
     * Deletes the timer row behind the given worker.
     *
     * @param string|int $workerId Worker identifier.
     * @return void
     */
    public function del(string|int $workerId): void
    {
        $this->deleted[] = $workerId;
    }

    /**
     * Reads a timer row field.
     *
     * @param string|int $workerId Worker identifier.
     * @param string|null $field Field name, or null for the whole row.
     * @return mixed Field value.
     */
    public function get(string|int $workerId, ?string $field = null): mixed
    {
        return $this->fdValue;
    }
}
