<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Exception;

use Crustum\Stimulus\Exception\DdException;
use Crustum\Stimulus\Exception\TaskException;
use Crustum\Stimulus\Exception\TaskExceptionResult;
use Crustum\Stimulus\Test\TestCase;
use Exception;
use LogicException;

/**
 * Tests for the serializable task exception snapshot.
 */
class TaskExceptionResultTest extends TestCase
{
    /**
     * Snapshot captures the throwable details.
     *
     * @return void
     */
    public function testFromCapturesThrowableDetails(): void
    {
        $result = TaskExceptionResult::from(new Exception('Something went wrong.', 9));

        $original = $result->getOriginal();

        $this->assertInstanceOf(TaskException::class, $original);
        $this->assertSame(Exception::class, $original->getClass());
        $this->assertSame('Something went wrong.', $original->getMessage());
        $this->assertSame(9, $original->getCode());
        $this->assertSame(__FILE__, $original->getFile());
    }

    /**
     * Dump-and-die throwables round-trip back into dump exceptions.
     *
     * @return void
     */
    public function testDdExceptionRoundTrip(): void
    {
        $result = TaskExceptionResult::from(new DdException(['foo' => 'bar']));

        $original = $result->getOriginal();

        $this->assertInstanceOf(DdException::class, $original);
        $this->assertSame(['foo' => 'bar'], $original->vars);
    }

    /**
     * Snapshot preserves custom exception subclasses by class name.
     *
     * @return void
     */
    public function testFromPreservesCustomSubclass(): void
    {
        $result = TaskExceptionResult::from(new LogicException('Custom failure.', 4));

        $original = $result->getOriginal();

        $this->assertInstanceOf(TaskException::class, $original);
        $this->assertSame(LogicException::class, $original->getClass());
        $this->assertSame('Custom failure.', $original->getMessage());
        $this->assertSame(4, $original->getCode());
    }
}
