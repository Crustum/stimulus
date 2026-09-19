<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Contracts;

/**
 * Contract for worker operation events carrying both application instances.
 *
 * Marks events dispatched when a worker operation terminates so listeners
 * can flush the sandbox while still reaching the base application.
 */
interface OperationTerminated
{
    /**
     * Get the base application instance.
     *
     * @return object Base application instance.
     */
    public function app(): object;

    /**
     * Get the sandbox version of the application instance.
     *
     * @return object Sandbox application instance.
     */
    public function sandbox(): object;
}
