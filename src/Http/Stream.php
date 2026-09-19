<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Http;

use Cake\Collection\Collection;
use Laravel\SerializableClosure\Support\ClosureStream;
use Throwable;

/**
 * Streams worker request and error payloads to the process output streams.
 *
 * The server binary consumes these newline-delimited JSON payloads
 * to report handled requests, throwables, and shutdown failures.
 *
 * Wire payloads are built through the `message` builder, which passes the
 * ordered field map through a `Cake\Collection\Collection` pipeline stage
 * before JSON encoding. The pipeline keeps key insertion order exactly, so
 * the encoded wire format preserves key order while
 * giving future field transforms a single functional seam.
 */
class Stream
{
    /**
     * Streams the given request information to stdout.
     *
     * @param string $method HTTP method of the handled request.
     * @param string $url URL of the handled request.
     * @param int $statusCode Response status code sent to the client.
     * @param float $duration Seconds spent handling the request.
     * @return void
     */
    public static function request(string $method, string $url, int $statusCode, float $duration): void
    {
        fwrite(STDOUT, static::message([
            'type' => 'request',
            'method' => $method,
            'url' => $url,
            'memory' => memory_get_usage(),
            'statusCode' => $statusCode,
            'duration' => $duration,
        ]) . "\n");
    }

    /**
     * Streams the given throwable to stderr.
     *
     * Locates the fallback trace frame for serialized closures through a
     * collection pipeline filtering frames that carry a file.
     *
     * @param \Throwable $throwable Throwable raised while handling the request.
     * @return void
     */
    public static function throwable(Throwable $throwable): void
    {
        $fallbackTrace = str_starts_with($throwable->getFile(), ClosureStream::STREAM_PROTO . '://')
            ? (new Collection($throwable->getTrace()))->filter(fn(array $frame): bool => isset($frame['file']))->first()
            : null;

        static::writeError(static::message([
            'type' => 'throwable',
            'class' => $throwable::class,
            'code' => $throwable->getCode(),
            'file' => $fallbackTrace['file'] ?? $throwable->getFile(),
            'line' => $fallbackTrace['line'] ?? $throwable->getLine(),
            'message' => $throwable->getMessage(),
            'trace' => array_slice($throwable->getTrace(), 0, 2),
        ]));
    }

    /**
     * Streams the given shutdown throwable to stderr.
     *
     * @param \Throwable $throwable Throwable raised during worker shutdown.
     * @return void
     */
    public static function shutdown(Throwable $throwable): void
    {
        static::writeError(static::message([
            'type' => 'shutdown',
            'class' => $throwable::class,
            'code' => $throwable->getCode(),
            'file' => $throwable->getFile(),
            'line' => $throwable->getLine(),
            'message' => $throwable->getMessage(),
            'trace' => array_slice($throwable->getTrace(), 0, 2),
        ]));
    }

    /**
     * Encodes the given ordered field map into a wire payload.
     *
     * Runs the fields through a collection pipeline that preserves key
     * insertion order in the encoded object.
     *
     * @param array<string, mixed> $payload Ordered wire fields.
     * @return string JSON-encoded wire payload.
     */
    protected static function message(array $payload): string
    {
        return (string)json_encode((new Collection($payload))->toArray());
    }

    /**
     * Writes an error message to STDERR or to the SAPI logger if not in CLI mode.
     *
     * @param string $message Error payload to write.
     * @return void
     */
    protected static function writeError(string $message): void
    {
        if (defined('STDERR')) {
            fwrite(STDERR, $message . PHP_EOL);

            return;
        }

        error_log($message, 4);
    }
}
