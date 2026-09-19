<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Exception;

use Exception;

/**
 * Exception thrown when a background task exceeds its time limit.
 *
 * The class is final so the `static` named constructor always resolves to
 * this exception type.
 */
final class TaskTimeoutException extends Exception
{
    /**
     * Creates a new task timeout exception with the given milliseconds.
     *
     * @param int $milliseconds Milliseconds the task was allowed to run.
     * @return static New task timeout exception instance.
     */
    public static function after(int $milliseconds): static
    {
        return new self("Task timed out after {$milliseconds} milliseconds.");
    }
}
