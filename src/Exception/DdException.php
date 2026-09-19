<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Exception;

use Cake\Collection\Collection;
use Exception;
use Throwable;

/**
 * Exception thrown to dump debug variables from a worker task.
 *
 * Carries the dumped variables so task dispatchers can rethrow them
 * on the worker side instead of losing the debug output.
 */
class DdException extends Exception
{
    /**
     * Creates a new dump-and-die exception.
     *
     * @param array<int|string, mixed> $vars Variables to dump when rendered.
     * @param string $message Exception message, defaults to the JSON encoded variables.
     * @param int $code Exception code.
     * @param \Throwable|null $previous Previous throwable for chaining.
     */
    public function __construct(
        public array $vars,
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message === '' ? (string)json_encode($vars) : $message, $code, $previous);
    }

    /**
     * Renders the carried variables as an HTML string.
     *
     * Maps each variable through the Cake-local dumper and concatenates the
     * results.
     *
     * @return string HTML dump of the carried variables.
     */
    public function render(): string
    {
        $dump = fn(mixed $var): string => '<pre>' . htmlspecialchars(
            $this->dumpVariable($var),
            ENT_QUOTES,
            'UTF-8',
        ) . '</pre>';

        return (new Collection($this->vars))->map($dump)->implode('');
    }

    /**
     * Exports a single variable to a readable string with bounded depth and length.
     *
     * @param mixed $variable Variable to export.
     * @param int $depth Current recursion depth.
     * @return string Readable export of the variable.
     */
    protected function dumpVariable(mixed $variable, int $depth = 0): string
    {
        if (is_string($variable)) {
            return $this->truncate($variable);
        }

        if (is_array($variable)) {
            return $this->dumpArray($variable, $depth);
        }

        if (is_object($variable)) {
            return $this->dumpObject($variable, $depth);
        }

        return var_export($variable, true);
    }

    /**
     * Exports an array with bounded recursion depth.
     *
     * The entry rendering follows the collection pipeline style used by
     * `render`.
     *
     * @param array<int|string, mixed> $value Array to export.
     * @param int $depth Current recursion depth.
     * @return string Readable export of the array.
     */
    protected function dumpArray(array $value, int $depth): string
    {
        if ($depth >= 3) {
            return '[...]';
        }

        $parts = (new Collection($value))
            ->map(fn(mixed $item, int|string $key): string => var_export($key, true) . ' => ' . $this->dumpVariable($item, $depth + 1))
            ->toList();

        return '[' . implode(', ', $parts) . ']';
    }

    /**
     * Exports an object with bounded recursion depth.
     *
     * The property rendering follows the collection pipeline style used by
     * `render`.
     *
     * @param object $value Object to export.
     * @param int $depth Current recursion depth.
     * @return string Readable export of the object.
     */
    protected function dumpObject(object $value, int $depth): string
    {
        if ($depth >= 3) {
            return $value::class . ' {...}';
        }

        $parts = (new Collection(get_object_vars($value)))
            ->map(fn(mixed $item, int|string $key): string => $key . ': ' . $this->dumpVariable($item, $depth + 1))
            ->toList();

        return $value::class . ' {' . implode(', ', $parts) . '}';
    }

    /**
     * Truncates a string value to the maximum dump length.
     *
     * @param string $value String value to truncate.
     * @return string Truncated string value.
     */
    protected function truncate(string $value): string
    {
        if (strlen($value) > 160) {
            return substr($value, 0, 160) . '...';
        }

        return $value;
    }
}
