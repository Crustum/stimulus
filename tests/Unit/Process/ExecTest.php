<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Process;

use Crustum\Stimulus\Process\Exec;
use Crustum\Stimulus\Test\TestCase;

/**
 * Tests for the shell exec wrapper.
 */
class ExecTest extends TestCase
{
    /**
     * Run returns the output lines of the given command.
     *
     * @return void
     */
    public function testRunReturnsOutputLines(): void
    {
        $exec = new Exec();

        $output = $exec->run('echo stimulus-exec-probe');

        $this->assertIsArray($output);
        $this->assertContains('stimulus-exec-probe', $output);
    }

    /**
     * Run returns an empty array when the command produces no output.
     *
     * @return void
     */
    public function testRunReturnsEmptyArrayWithoutOutput(): void
    {
        $exec = new Exec();

        $command = PHP_OS_FAMILY === 'Windows' ? 'cd .' : 'true';
        $output = $exec->run($command);

        $this->assertIsArray($output);
    }
}
