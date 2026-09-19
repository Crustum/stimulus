<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Http;

use Crustum\Stimulus\Http\Stream;
use Crustum\Stimulus\Test\TestCase;
use RuntimeException;

/**
 * Tests for the worker stream payloads.
 *
 * Stream writes straight to the STDOUT and STDERR file descriptors,
 * so each payload is produced in a child PHP process and decoded back
 * from the captured pipe.
 */
class StreamTest extends TestCase
{
    /**
     * Request streams a JSON line describing the handled request to stdout.
     *
     * @return void
     */
    public function testRequestStreamsJsonLineToStdout(): void
    {
        $payload = $this->runChildProcess(
            Stream::class . '::request(' .
            "'GET', 'https://example.test/path', 200, 0.05);",
        );

        $this->assertSame('request', $payload['type']);
        $this->assertSame('GET', $payload['method']);
        $this->assertSame('https://example.test/path', $payload['url']);
        $this->assertSame(200, $payload['statusCode']);
        $this->assertSame(0.05, $payload['duration']);
        $this->assertArrayHasKey('memory', $payload);
    }

    /**
     * Throwable streams a JSON payload describing the failure to stderr.
     *
     * @return void
     */
    public function testThrowableStreamsJsonPayloadToStderr(): void
    {
        $payload = $this->runChildProcess(
            "try { throw new RuntimeException('stream-boom'); }" .
            (' catch (Throwable $e) { ' . Stream::class . '::throwable($e); }'),
            false,
        );

        $this->assertSame('throwable', $payload['type']);
        $this->assertSame(RuntimeException::class, $payload['class']);
        $this->assertSame('stream-boom', $payload['message']);
        $this->assertArrayHasKey('file', $payload);
        $this->assertArrayHasKey('line', $payload);
        $this->assertArrayHasKey('trace', $payload);
    }

    /**
     * Shutdown streams a JSON payload describing the shutdown failure to stderr.
     *
     * @return void
     */
    public function testShutdownStreamsJsonPayloadToStderr(): void
    {
        $payload = $this->runChildProcess(
            "try { throw new RuntimeException('shutdown-boom'); }" .
            (' catch (Throwable $e) { ' . Stream::class . '::shutdown($e); }'),
            false,
        );

        $this->assertSame('shutdown', $payload['type']);
        $this->assertSame(RuntimeException::class, $payload['class']);
        $this->assertSame('shutdown-boom', $payload['message']);
    }

    /**
     * Wire payloads keep the key order.
     *
     * @return void
     */
    public function testWirePayloadsKeepKeyOrder(): void
    {
        $request = $this->runChildProcess(
            Stream::class . '::request(' .
            "'GET', 'https://example.test/path', 200, 0.05);",
        );

        $this->assertSame(
            ['type', 'method', 'url', 'memory', 'statusCode', 'duration'],
            array_keys($request),
        );

        $throwable = $this->runChildProcess(
            "try { throw new RuntimeException('order-boom'); }" .
            (' catch (Throwable $e) { ' . Stream::class . '::throwable($e); }'),
            false,
        );

        $this->assertSame(
            ['type', 'class', 'code', 'file', 'line', 'message', 'trace'],
            array_keys($throwable),
        );

        $shutdown = $this->runChildProcess(
            "try { throw new RuntimeException('shutdown-order-boom'); }" .
            (' catch (Throwable $e) { ' . Stream::class . '::shutdown($e); }'),
            false,
        );

        $this->assertSame(
            ['type', 'class', 'code', 'file', 'line', 'message', 'trace'],
            array_keys($shutdown),
        );
    }

    /**
     * Runs the given snippet in a child process and decodes the streamed JSON payload.
     *
     * @param string $snippet PHP statements to run after the autoloader is required.
     * @param bool $readStdout Whether to read the payload from stdout instead of stderr.
     * @return array<string, mixed> Decoded stream payload.
     */
    protected function runChildProcess(string $snippet, bool $readStdout = true): array
    {
        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open(
            [PHP_BINARY, '-r', "require 'vendor/autoload.php'; " . $snippet],
            $descriptorSpec,
            $pipes,
            getcwd() ?: null,
        );

        $this->assertIsResource($process);

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        $this->assertSame(0, $exitCode);

        $payload = json_decode(trim($readStdout ? (string)$stdout : (string)$stderr), true);

        $this->assertIsArray($payload);

        return $payload;
    }
}
