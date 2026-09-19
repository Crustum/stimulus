<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Exception;

use Crustum\Stimulus\Exception\WorkerException;
use Crustum\Stimulus\Test\TestCase;
use Exception;

/**
 * Tests for the worker exception.
 */
class WorkerExceptionTest extends TestCase
{
    /**
     * Exception carries the originating file and line.
     *
     * @return void
     */
    public function testExceptionCarriesOriginatingLocation(): void
    {
        $exception = new WorkerException('Worker failed to boot.', 2, '/tmp/worker.php', 11);

        $this->assertInstanceOf(Exception::class, $exception);
        $this->assertSame('Worker failed to boot.', $exception->getMessage());
        $this->assertSame(2, $exception->getCode());
        $this->assertSame('/tmp/worker.php', $exception->getFile());
        $this->assertSame(11, $exception->getLine());
    }
}
