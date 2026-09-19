<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Exception;

use InvalidArgumentException;

/**
 * Exception thrown when a value exceeds the size of its table column.
 */
class ValueTooLargeForColumnException extends InvalidArgumentException
{
}
