<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Command\Trait;

use Cake\Collection\Collection;
use Cake\Console\CommandInterface;
use Cake\Core\Configure;
use Crustum\Stimulus\Contracts\ServerProcessInspector as ServerProcessInspectorContract;
use Crustum\Stimulus\Exception\ServerShutdownException;
use InvalidArgumentException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Runs the worker server process and its file watcher.
 *
 * Configuration values are read through configuration, and the
 * `SignalableCommandInterface` handling is replaced by the
 * `skie/signal_handler` graceful termination: the run loop additionally
 * checks the `serverLoopRunning` flag that `bindGracefulTermination()`
 * callbacks flip, so signals stop the loop without raw process calls. The
 * no-op watcher keeps the anonymous shape; process-only calls are guarded so
 * the stand-in never receives them.
 *
 * @mixin \Crustum\Stimulus\Command\StimulusCommand
 */
trait InteractsWithServersTrait
{
    /**
     * Stops the server.
     *
     * Implemented by each starter command through the stop command for the
     * signal handling and the run loop teardown.
     *
     * @return void
     */
    abstract protected function stopServer(): void;

    /**
     * Runs the given server process.
     *
     * Waits for the process to start, prints the running message, then pumps
     * server output until the process exits or graceful termination flips the
     * loop flag. Restarts the workers when the watcher reports a change and
     * tears the server down in every exit path.
     *
     * @param \Symfony\Component\Process\Process $server Started worker server process.
     * @param \Crustum\Stimulus\Contracts\ServerProcessInspector $inspector Inspector reloading the workers.
     * @param string $type Server driver name used by state handling.
     * @return int Exit code of the server process.
     */
    protected function runServer(
        Process $server,
        ServerProcessInspectorContract $inspector,
        string $type,
    ): int {
        while (! $server->isStarted()) {
            sleep(1);
        }

        $this->writeServerRunningMessage();

        $watcher = $this->startServerWatcher();

        try {
            $usleepBetweenIterations = (int)(
                Configure::read('Stimulus.usleepBetweenWritingServerOutput')
                ?? $_ENV['STIMULUS_USLEEP_BETWEEN_WRITING_SERVER_OUTPUT']
                ?? 10 * 1000
            );

            while ($server->isRunning() && $this->serverLoopRunning()) {
                $this->writeServerOutput($server);

                if (
                    $watcher instanceof Process
                    && $watcher->isRunning()
                    && $watcher->getIncrementalOutput()
                ) {
                    $this->info('Application change detected. Restarting workers…');

                    $inspector->reloadServer();
                } elseif ($watcher instanceof Process && $watcher->isTerminated()) {
                    $this->error(
                        'Watcher process has terminated. Please ensure Node and chokidar are installed.'
                        . PHP_EOL
                        . $watcher->getErrorOutput(),
                    );

                    return CommandInterface::CODE_ERROR;
                }

                usleep($usleepBetweenIterations);
            }

            $this->writeServerOutput($server);
        } catch (ServerShutdownException) {
            return CommandInterface::CODE_ERROR;
        } finally {
            $this->stopServer();
        }

        return $server->getExitCode() ?? CommandInterface::CODE_SUCCESS;
    }

    /**
     * Starts the watcher process for the server.
     *
     * Returns a no-op stand-in exposing the four watcher calls the run loop
     * makes when watching is disabled; the `instanceof` guards keep
     * process-only behavior on real processes either way. Watch paths resolve
     * from the `Stimulus.watch` configuration value through a collection
     * pipeline.
     *
     * @return object Watcher process or the no-op stand-in.
     * @throws \InvalidArgumentException When watching is enabled without configured paths.
     */
    protected function startServerWatcher(): object
    {
        if (! $this->serverOption('watch')) {
            return new class {
                /**
                 * Reports the disabled watcher as stopped.
                 *
                 * @return bool False because the watcher never runs.
                 */
                public function isRunning(): bool
                {
                    return false;
                }

                /**
                 * Reports no watcher output while disabled.
                 *
                 * @return string Empty watcher output.
                 */
                public function getIncrementalOutput(): string
                {
                    return '';
                }

                /**
                 * Reports the disabled watcher as alive.
                 *
                 * @return bool False because the watcher never terminates.
                 */
                public function isTerminated(): bool
                {
                    return false;
                }

                /**
                 * Reports no watcher error output while disabled.
                 *
                 * @return string Empty watcher error output.
                 */
                public function getErrorOutput(): string
                {
                    return '';
                }
            };
        }

        $watched = Configure::read('Stimulus.watch');

        if (empty($watched)) {
            throw new InvalidArgumentException(
                'List of directories/files to watch not found. Please update your "config/stimulus.php" configuration file.',
            );
        }

        $paths = (new Collection((array)$watched))
            ->map(
                fn($path): string => $this->applicationBasePath() . DIRECTORY_SEPARATOR . $path,
            )
            ->toList();

        $process = new Process(
            [
                (new ExecutableFinder())->find('node'),
                'file-watcher.cjs',
                (string)json_encode($paths),
                (string)$this->serverOption('poll'),
            ],
            $this->pluginBinPath(),
            null,
            null,
            null,
        );

        $process->start();

        return $process;
    }

    /**
     * Writes the server start message to the console.
     *
     * Includes the HTTPS scheme switch for drivers exposing the option.
     *
     * @return void
     */
    protected function writeServerRunningMessage(): void
    {
        $this->info('Server running…');

        $scheme = $this->serverOption('https') ? 'https://' : 'http://';

        $this->io->out([
            '',
            '  Local: <info>' . $scheme . $this->getHost() . ':' . $this->getPort() . '</info> ',
            '',
            '  <warning>Press Ctrl+C to stop the server</warning>',
            '',
        ]);
    }

    /**
     * Retrieves the given server output and flushes it.
     *
     * @param \Symfony\Component\Process\Process $server Running worker server process.
     * @return array{0: string, 1: string} Standard and error output captured since the last call.
     */
    protected function getServerOutput(Process $server): array
    {
        $output = [
            $server->getIncrementalOutput(),
            $server->getIncrementalErrorOutput(),
        ];

        $server->clearOutput()->clearErrorOutput();

        return $output;
    }

    /**
     * Ensures the worker HTTP server port is available.
     *
     * Probes the port silently so a free port never surfaces a connection
     * warning.
     *
     * @return void
     * @throws \InvalidArgumentException When the host port already accepts connections.
     */
    protected function ensurePortIsAvailable(): void
    {
        $host = $this->getHost();

        $port = $this->getPort();

        set_error_handler(static fn(): bool => true);

        try {
            $connection = fsockopen($host, (int)$port, $errorNumber, $errorMessage);
        } finally {
            restore_error_handler();
        }

        if (is_resource($connection)) {
            fclose($connection);

            throw new InvalidArgumentException("Unable to start server. Port {$port} is already in use.");
        }
    }
}
