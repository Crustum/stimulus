<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Command\Trait;

use Cake\Collection\Collection;
use Cake\Console\ConsoleIo;
use Crustum\Stimulus\Exception\DdException;
use Crustum\Stimulus\Exception\ServerShutdownException;

/**
 * Formats worker and request output for the Stimulus console commands.
 *
 * Output writes go through the `ConsoleIo` instance
 * stashed on the base command and maps the background/foreground labels onto
 * named output styles. The `parseVerbosity` helper comes from the
 * framework console layer, so it is defined here against the output
 * levels instead.
 *
 * @mixin \Crustum\Stimulus\Command\StimulusCommand
 */
trait InteractsWithIoTrait
{
    use InteractsWithTerminalTrait;

    /**
     * A list of error messages that should be ignored.
     *
     * @var array<int, string>
     */
    protected array $ignoreMessages = [
        'destroy signal received',
        'req-resp mode',
        'scan command',
        'sending stop request to the worker',
        'stop signal received, grace timeout is: ',
        'exit forced',
        'worker allocated',
        'worker is allocated',
        'worker constructed',
        'worker destructed',
        'worker destroyed',
        '[INFO] RoadRunner server started; version:',
        '[INFO] sdnotify: not notified',
        'exiting; byeee!!',
        'storage cleaning happened too recently',
        'unable to determine directory for user configuration; falling back to current directory',
        '$HOME environment variable is empty',
        'unable to get instance ID',
    ];

    /**
     * Writes a string as raw output.
     *
     * Messages starting with an ignored prefix are swallowed. Output goes to
     * the error stream.
     *
     * @param string $string Raw output line to write.
     * @return void
     */
    public function raw(string $string): void
    {
        if (! $this->shouldIgnoreMessage($string)) {
            $this->io->err($string);
        }
    }

    /**
     * Writes a string as information output.
     *
     * @param string $string Message to write.
     * @param mixed $verbosity Verbosity accepted by parseVerbosity.
     * @return void
     */
    public function info(string $string, mixed $verbosity = null): void
    {
        $this->label($string, $verbosity, 'INFO', 'blue', 'white');
    }

    /**
     * Writes a string as error output.
     *
     * @param string $string Message to write.
     * @param mixed $verbosity Verbosity accepted by parseVerbosity.
     * @return void
     */
    public function error(string $string, mixed $verbosity = null): void
    {
        $this->label($string, $verbosity, 'ERROR', 'red', 'white');
    }

    /**
     * Writes a string as warning output.
     *
     * @param string $string Message to write.
     * @param mixed $verbosity Verbosity accepted by parseVerbosity.
     * @return void
     */
    public function warn(string $string, mixed $verbosity = null): void
    {
        $this->label($string, $verbosity, 'WARN', 'yellow', 'black');
    }

    /**
     * Writes a string as label output.
     *
     * The level selects the named style carrying the same intent.
     *
     * @param string $string Message to write.
     * @param mixed $verbosity Verbosity accepted by parseVerbosity.
     * @param string $level Label level shown before the message.
     * @param string $background Background color, kept for signature parity.
     * @param string $foreground Foreground color, kept for signature parity.
     * @return void
     */
    public function label(
        string $string,
        mixed $verbosity,
        string $level,
        string $background,
        string $foreground,
    ): void {
        if ($string !== '' && ! $this->shouldIgnoreMessage($string)) {
            $style = match ($level) {
                'ERROR' => 'error',
                'WARN' => 'warning',
                default => 'info',
            };

            $this->io->out([
                '',
                "  <{$style}> {$level} </{$style}> {$string}",
            ], 1, $this->parseVerbosity($verbosity));
        }
    }

    /**
     * Writes information about a request to the console.
     *
     * The dotting, truncation, and memory math render the request line; only
     * the Symfony color tags become named styles.
     *
     * @param array<string, mixed> $request Request method, URL, status code, duration, and optional memory.
     * @param mixed $verbosity Verbosity accepted by parseVerbosity.
     * @return void
     */
    public function requestInfo(array $request, mixed $verbosity = null): void
    {
        $terminalWidth = $this->getTerminalWidth();

        $url = parse_url((string)$request['url'], PHP_URL_PATH) ?: '/';
        $duration = number_format(round((float)$request['duration'], 2), 2, '.', '');

        $memory = isset($request['memory'])
            ? number_format((float)$request['memory'] / 1024 / 1024, 2, '.', '') . ' mb '
            : '';

        ['method' => $method, 'statusCode' => $statusCode] = $request;

        $dots = str_repeat('.', max($terminalWidth - strlen($method . $url . $duration . $memory) - 16, 0));

        if ($dots === '' && $this->io->level() !== ConsoleIo::VERBOSE) {
            $url = substr(
                $url,
                0,
                $terminalWidth - strlen($method . $duration . $memory) - 15 - 3,
            ) . '...';
        } else {
            $dots .= ' ';
        }

        $this->io->out(sprintf(
            '  <%s>%s </%s>   <info>%s</info> <info>%s</info> %s%s%s ms',
            match (true) {
                $statusCode >= 500 => 'error',
                $statusCode >= 400 => 'warning',
                $statusCode >= 300 => 'comment',
                $statusCode >= 100 => 'success',
                default => 'info',
            },
            $statusCode,
            match (true) {
                $statusCode >= 500 => 'error',
                $statusCode >= 400 => 'warning',
                $statusCode >= 300 => 'comment',
                $statusCode >= 100 => 'success',
                default => 'info',
            },
            $method,
            $url,
            $dots,
            $memory,
            $duration,
        ), 1, $this->parseVerbosity($verbosity));
    }

    /**
     * Writes information about a dd call to the console.
     *
     * The dumper needs symfony/var-dumper, which is not installed, so each
     * dumped variable is exported through the console instead.
     *
     * @param array<string, mixed> $throwable Throwable payload carrying the dumped variables as JSON.
     * @param mixed $verbosity Verbosity accepted by parseVerbosity.
     * @return void
     */
    public function ddInfo(array $throwable, mixed $verbosity = null): void
    {
        $decoded = json_decode((string)$throwable['message'], true);

        (new Collection(is_array($decoded) ? $decoded : []))
            ->each(fn($var) => $this->io->out(var_export($var, true), 1, $this->parseVerbosity($verbosity)));
    }

    /**
     * Writes information about a throwable to the console.
     *
     * Only the fallback branch is ported: the Collision writer and the worker
     * exception inspector are not installed, the inspector landing with the
     * P8 worker core, so throwables render as a label plus trace.
     *
     * @param array<string, mixed> $throwable Throwable class, message, file, line, and trace.
     * @param mixed $verbosity Verbosity accepted by parseVerbosity.
     * @return void
     */
    public function throwableInfo(array $throwable, mixed $verbosity = null): void
    {
        if ($throwable['class'] == DdException::class) {
            $this->ddInfo($throwable, $verbosity);

            return;
        }

        $this->label((string)$throwable['message'], $verbosity, (string)$throwable['class'], 'red', 'white');

        $this->io->out('');

        $outputTrace = function (array $trace, $number): void {
            $number++;

            if (isset($trace['line'])) {
                ['line' => $line, 'file' => $file] = $trace;

                $this->io->out("  <warning>{$number}</warning>   {$file}:{$line}");
            }
        };

        $outputTrace($throwable, -1);

        (new Collection((array)$throwable['trace']))->each($outputTrace);
    }

    /**
     * Writes information about a shutdown throwable to the console.
     *
     * Renders the throwable like any other failure, then raises the shutdown
     * signal the server loop catches to stop the process.
     *
     * @param array<string, mixed> $throwable Shutdown payload rendered as throwable information.
     * @param mixed $verbosity Verbosity accepted by parseVerbosity.
     * @return void
     * @throws \Crustum\Stimulus\Exception\ServerShutdownException Always thrown to stop the server loop.
     */
    public function shutdownInfo(array $throwable, mixed $verbosity = null): void
    {
        $this->throwableInfo($throwable, $verbosity);

        throw new ServerShutdownException('Stimulus server is shutting down.');
    }

    /**
     * Handles stream information from the worker.
     *
     * @param array<string, mixed> $stream Stream payload with a type of request, throwable, shutdown, or raw.
     * @param mixed $verbosity Verbosity accepted by parseVerbosity.
     * @return void
     * @throws \Crustum\Stimulus\Exception\ServerShutdownException When the stream carries a shutdown payload.
     */
    public function handleStream(array $stream, mixed $verbosity = null): void
    {
        match ($stream['type'] ?? null) {
            'request' => $this->requestInfo($stream, $verbosity),
            'throwable' => $this->throwableInfo($stream, $verbosity),
            'shutdown' => $this->shutdownInfo($stream, $verbosity),
            'raw' => $this->raw((string)json_encode($stream)),
            default => $this->info((string)json_encode($stream), $verbosity),
        };
    }

    /**
     * Maps the given verbosity onto a console output level, accepting
     * the same loose verbosity values.
     *
     * @param mixed $verbosity Loose verbosity flag from the IO helpers.
     * @return int Console output level for the message.
     */
    protected function parseVerbosity(mixed $verbosity = null): int
    {
        if (
            in_array($verbosity, [ConsoleIo::VERBOSE, 'v', 'verbose', '-v'], true)
        ) {
            return ConsoleIo::VERBOSE;
        }

        if (
            in_array($verbosity, [ConsoleIo::QUIET, 'q', 'quiet', '-q'], true)
        ) {
            return ConsoleIo::QUIET;
        }

        return ConsoleIo::NORMAL;
    }

    /**
     * Determines whether the given message matches the ignore list.
     *
     * Checks the prefixes as a collection pipeline over the ignore list.
     *
     * @param string $string Message to check against the ignore list.
     * @return bool Whether the message should be swallowed.
     */
    protected function shouldIgnoreMessage(string $string): bool
    {
        return (new Collection($this->ignoreMessages))
            ->some(fn(string $ignored): bool => str_starts_with($string, $ignored));
    }
}
