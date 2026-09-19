<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Contracts;

/**
 * Contract for server bridge clients able to stop the underlying server.
 */
interface StoppableClient extends Client
{
    /**
     * Stop the underlying server / worker.
     *
     * @return void
     */
    public function stop(): void;
}
