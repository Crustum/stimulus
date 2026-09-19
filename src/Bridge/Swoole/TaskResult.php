<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Bridge\Swoole;

/**
 * Value wrapper for Swoole task worker results.
 *
 * Task workers return this wrapper so the dispatcher distinguishes finished results from
 * unfinished `false` slots when mapping results back onto task keys.
 */
class TaskResult
{
    /**
     * Creates a new task result.
     *
     * @param mixed $result Result produced by the task worker.
     */
    public function __construct(public mixed $result)
    {
    }
}
