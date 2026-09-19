<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Exception;

use Crustum\Stimulus\Exception\ValueTooLargeForColumnException;
use Crustum\Stimulus\Test\TestCase;
use InvalidArgumentException;

/**
 * Tests for the oversized column value exception.
 */
class ValueTooLargeForColumnExceptionTest extends TestCase
{
    /**
     * Exception loads and behaves as an invalid argument exception.
     *
     * @return void
     */
    public function testExceptionLoadsAndBehaves(): void
    {
        $exception = new ValueTooLargeForColumnException('Value [abc...] is too large for [name] column.');

        $this->assertInstanceOf(InvalidArgumentException::class, $exception);
        $this->assertSame('Value [abc...] is too large for [name] column.', $exception->getMessage());
    }
}
