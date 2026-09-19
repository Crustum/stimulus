<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Exception;

use Crustum\Stimulus\Exception\TaskException;
use Crustum\Stimulus\Test\TestCase;
use Exception;

/**
 * Tests for the background task exception.
 */
class TaskExceptionTest extends TestCase
{
    /**
     * Exception carries the original throwable class, file, and line.
     *
     * @return void
     */
    public function testExceptionCarriesOriginalLocation(): void
    {
        $exception = new TaskException('RuntimeException', 'Something went wrong.', 5, '/tmp/task.php', 42);

        $this->assertInstanceOf(Exception::class, $exception);
        $this->assertSame('RuntimeException', $exception->getClass());
        $this->assertSame('Something went wrong.', $exception->getMessage());
        $this->assertSame(5, $exception->getCode());
        $this->assertSame('/tmp/task.php', $exception->getFile());
        $this->assertSame(42, $exception->getLine());
    }
}
