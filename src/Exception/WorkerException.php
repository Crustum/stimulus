<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Exception;

use Exception;

/**
 * Exception thrown when the worker itself fails to boot or run.
 *
 * Carries the originating file and line so worker failures stay
 * debuggable across the server bridge boundary.
 */
class WorkerException extends Exception
{
    /**
     * Creates a new worker exception.
     *
     * @param string $message Exception message.
     * @param int $code Exception code.
     * @param string $file Originating file.
     * @param int $line Originating line.
     */
    public function __construct(string $message, int $code, string $file, int $line)
    {
        parent::__construct($message, $code);

        $this->file = $file;
        $this->line = $line;
    }
}
