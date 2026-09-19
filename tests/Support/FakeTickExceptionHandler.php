<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Support;

use Throwable;

/**
 * Exception handler fake collecting reported throwables.
 *
 * Stands in for framework exception handlers in extension-gated tick tests
 * so the suites load and skip cleanly where the extension is absent.
 */
class FakeTickExceptionHandler
{
    /**
     * Reported throwables.
     *
     * @var list<\Throwable>
     */
    public array $reported = [];

    /**
     * Reports the given throwable.
     *
     * @param \Throwable $throwable Throwable raised by the tick listener.
     * @return void
     */
    public function report(Throwable $throwable): void
    {
        $this->reported[] = $throwable;
    }
}
