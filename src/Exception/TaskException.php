<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Exception;

use Exception;

/**
 * Exception rethrown on the worker side when a background task fails.
 *
 * Carries the original throwable class name plus the original
 * file and line so failures stay debuggable across processes.
 */
class TaskException extends Exception
{
    /**
     * The original throwable class name.
     *
     * @var string
     */
    protected string $class;

    /**
     * Creates a new task exception.
     *
     * @param string $class Original throwable class name.
     * @param string $message Original exception message.
     * @param int $code Original exception code.
     * @param string $file Original exception file.
     * @param int $line Original exception line.
     */
    public function __construct(string $class, string $message, int $code, string $file, int $line)
    {
        parent::__construct($message, $code);

        $this->class = $class;
        $this->file = $file;
        $this->line = $line;
    }

    /**
     * Returns the original throwable class name.
     *
     * @return string Original throwable class name.
     */
    public function getClass(): string
    {
        return $this->class;
    }
}
