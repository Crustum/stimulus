<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\System;

use Crustum\Stimulus\System\PosixExtension;
use Crustum\Stimulus\Test\TestCase;

/**
 * Tests for the POSIX extension wrapper.
 */
class PosixExtensionTest extends TestCase
{
    /**
     * Kill with signal zero reports whether the current process exists.
     *
     * @return void
     */
    public function testKillSignalsCurrentProcess(): void
    {
        if (!function_exists('posix_kill')) {
            $this->markTestSkipped('The POSIX extension is not available.');
        }

        $extension = new PosixExtension();

        $this->assertTrue($extension->kill(getmypid(), 0));
    }
}
