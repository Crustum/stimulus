<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Exception;

use Crustum\Stimulus\Exception\TaskTimeoutException;
use Crustum\Stimulus\Test\TestCase;
use Exception;

/**
 * Tests for the task timeout exception.
 */
class TaskTimeoutExceptionTest extends TestCase
{
    /**
     * Factory builds an exception naming the allowed milliseconds.
     *
     * @return void
     */
    public function testAfterNamesMilliseconds(): void
    {
        $exception = TaskTimeoutException::after(3000);

        $this->assertInstanceOf(Exception::class, $exception);
        $this->assertSame('Task timed out after 3000 milliseconds.', $exception->getMessage());
    }
}
