<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Exception;

use Cake\Collection\Collection;
use Laravel\SerializableClosure\Support\ClosureStream;
use Throwable;

/**
 * Serializable snapshot of a failed background task throwable.
 *
 * Transports the throwable class, message, code, file, and line across
 * process boundaries so the worker can rebuild the original exception.
 *
 * The class is final so the `static` named constructor always resolves to
 * this snapshot type.
 */
final class TaskExceptionResult
{
    /**
     * Creates a new task exception result.
     *
     * @param string $class Original throwable class name.
     * @param string $message Original exception message.
     * @param int $code Original exception code.
     * @param string $file Original exception file.
     * @param int $line Original exception line.
     */
    public function __construct(
        protected string $class,
        protected string $message,
        protected int $code,
        protected string $file,
        protected int $line,
    ) {
    }

    /**
     * Creates a new task exception result from the given throwable.
     *
     * Locates the fallback trace frame for serialized closures through a
     * collection pipeline filtering frames that carry a file.
     *
     * @param \Throwable $throwable Throwable raised by the failed task.
     * @return static Snapshot of the given throwable.
     */
    public static function from(Throwable $throwable): static
    {
        $fallbackTrace = str_starts_with($throwable->getFile(), ClosureStream::STREAM_PROTO . '://')
            ? (new Collection($throwable->getTrace()))->filter(fn(array $frame): bool => isset($frame['file']))->first()
            : null;

        return new self(
            $throwable::class,
            $throwable->getMessage(),
            (int)$throwable->getCode(),
            $fallbackTrace['file'] ?? $throwable->getFile(),
            $fallbackTrace['line'] ?? $throwable->getLine(),
        );
    }

    /**
     * Gets the original throwable.
     *
     * @return \Crustum\Stimulus\Exception\TaskException|\Crustum\Stimulus\Exception\DdException Rebuilt original throwable.
     */
    public function getOriginal(): TaskException|DdException
    {
        if ($this->class === DdException::class) {
            $vars = json_decode($this->message, true);

            return new DdException(is_array($vars) ? $vars : [$vars]);
        }

        return new TaskException(
            $this->class,
            $this->message,
            $this->code,
            $this->file,
            $this->line,
        );
    }
}
