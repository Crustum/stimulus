<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Exception;

use Crustum\Stimulus\Exception\ServerShutdownException;
use Crustum\Stimulus\Test\TestCase;
use Exception;

/**
 * Tests for the server shutdown exception.
 */
class ServerShutdownExceptionTest extends TestCase
{
    /**
     * Exception loads and behaves as a plain exception.
     *
     * @return void
     */
    public function testExceptionLoadsAndBehaves(): void
    {
        $exception = new ServerShutdownException('shutting down', 7);

        $this->assertInstanceOf(Exception::class, $exception);
        $this->assertSame('shutting down', $exception->getMessage());
        $this->assertSame(7, $exception->getCode());
    }
}
