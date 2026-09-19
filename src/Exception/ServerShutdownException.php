<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Exception;

use Exception;

/**
 * Exception thrown to signal that the worker server is shutting down.
 */
class ServerShutdownException extends Exception
{
}
