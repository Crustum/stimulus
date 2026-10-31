<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Command\Trait;

use Cake\Console\ConsoleIo;
use Cake\Console\TestSuite\StubConsoleOutput;
use Crustum\Stimulus\Command\StimulusCommand;
use Crustum\Stimulus\Test\TestCase;

/**
 * Tests for the console IO formatting trait.
 *
 * The Symfony output becomes a stub output pair, and the color tags
 * become named styles, so the assertions cover the same visible layout.
 */
class InteractsWithIoTraitTest extends TestCase
{
    /**
     * Information messages render the label layout.
     *
     * @return void
     */
    public function testInfo(): void
    {
        [$output, $command] = $this->command();

        $command->info('foo');

        $this->assertSame("\n  <info> INFO </info> foo", $output->output());
    }

    /**
     * Error messages render the label layout.
     *
     * @return void
     */
    public function testError(): void
    {
        [$output, $command] = $this->command();

        $command->error('bar');

        $this->assertSame("\n  <error> ERROR </error> bar", $output->output());
    }

    /**
     * Warning messages render the label layout.
     *
     * @return void
     */
    public function testWarn(): void
    {
        [$output, $command] = $this->command();

        $command->warn('beta period');

        $this->assertSame("\n  <warning> WARN </warning> beta period", $output->output());
    }

    /**
     * Request lines keep the dotting and truncation math.
     *
     * @return void
     */
    public function testRequest(): void
    {
        [$output, $command] = $this->command();

        $command->requestInfo([
            'method' => 'GET',
            'url' => 'http://127.0.0.1/welcome',
            'statusCode' => '200',
            'memory' => 17393560,
            'duration' => 10,
        ]);

        $command->requestInfo([
            'method' => 'POST',
            'url' => 'http://127.0.0.1:8080',
            'statusCode' => '404',
            'memory' => 20393560,
            'duration' => 1234,
        ]);

        $command->requestInfo([
            'method' => 'POST',
            'url' => 'http://127.0.0.1:8080/' . str_repeat('foo', 100),
            'statusCode' => 500,
            'memory' => 30393560,
            'duration' => 4567854,
        ]);

        $this->assertSame(
            '  <success>200 </success>   <info>GET</info> <info>/welcome</info> ......... 16.59 mb 10.00 ms'
            . "\n" . '  <warning>404 </warning>   <info>POST</info> <info>/</info> ............. 19.45 mb 1234.00 ms'
            . "\n" . '  <error>500 </error>   <info>POST</info> <info>/foofoofo...</info> 28.99 mb 4567854.00 ms',
            $output->output(),
        );
    }

    /**
     * Ignored server noise never reaches the error stream.
     *
     * @return void
     */
    public function testRawIgnoresNoise(): void
    {
        [$output, $command] = $this->commandWithError($error);

        $command->raw('worker allocated');

        $command->raw('real failure');

        $this->assertSame('', $output->output());
        $this->assertSame('real failure', $error->output());
    }

    /**
     * Throwable payloads render the label plus trace lines.
     *
     * @return void
     */
    public function testThrowableInfo(): void
    {
        [$output, $command] = $this->command();

        $command->throwableInfo([
            'class' => 'RuntimeException',
            'message' => 'boom',
            'code' => 0,
            'file' => '/app/worker.php',
            'line' => 12,
            'trace' => [
                ['file' => '/app/server.php', 'line' => 34],
            ],
        ]);

        $messages = $output->output();

        $this->assertStringContainsString('RuntimeException', $messages);
        $this->assertStringContainsString('boom', $messages);
        $this->assertStringContainsString('/app/server.php:34', $messages);
    }

    /**
     * Builds a command bound to stub output with a fixed terminal width.
     *
     * The terminal width stays at 50 columns so the request line layout is deterministic.
     *
     * @return array{0: \Cake\Console\TestSuite\StubConsoleOutput, 1: \Crustum\Stimulus\Command\StimulusCommand} Stub output and command pair.
     */
    protected function command(): array
    {
        [$output, $command] = $this->commandWithError($error);

        return [$output, $command];
    }

    /**
     * Builds a command bound to separate stub output and error streams.
     *
     * @param \Cake\Console\TestSuite\StubConsoleOutput|null $error Error stream filled by the helper.
     * @return array{0: \Cake\Console\TestSuite\StubConsoleOutput, 1: \Crustum\Stimulus\Command\StimulusCommand} Stub output and command pair.
     */
    protected function commandWithError(?StubConsoleOutput &$error = null): array
    {
        $output = new StubConsoleOutput();

        $error = new StubConsoleOutput();

        $command = new class extends StimulusCommand {
            /**
             * Executes the command.
             *
             * @param \Cake\Console\Arguments $args The command arguments.
             * @param \Cake\Console\ConsoleIo $io The console io.
             * @return int The exit code or null for success.
             */
            public function execute(): int
            {
                return self::CODE_SUCCESS;
            }

            /**
             * Fixes the terminal width for deterministic request lines.
             *
             * @return int Terminal width in columns.
             */
            protected function getTerminalWidth(): int
            {
                return 50;
            }
        };

        $command->run([], new ConsoleIo($output, $error));

        return [$output, $command];
    }
}
