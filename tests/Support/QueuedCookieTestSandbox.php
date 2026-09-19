<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Support;

/**
 * Sandbox double exposing a shared cookie queue.
 *
 * Supports listeners that flush cookies queued during an operation.
 */
class QueuedCookieTestSandbox
{
    /**
     * Number of times the queue was flushed.
     *
     * @var int
     */
    public int $flushes = 0;

    /**
     * Records a queue flush.
     *
     * @return void
     */
    public function flushQueuedCookies(): void
    {
        $this->flushes++;
    }
}
