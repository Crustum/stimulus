<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Support;

use Cake\Http\Client;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Spawns live server processes for execution-proof tests.
 *
 * Starts real server entries on ephemeral ports, polls their TCP sockets
 * until they accept connections, fetches HTTP bodies through the HTTP
 * client, and stops every tracked process. Live tests call the stop and
 * directory cleanup helpers in a `finally` block so no server outlives its
 * test even on assertion failures.
 */
trait SpawnsServersTrait
{
    /**
     * Live server processes started by the test.
     *
     * @var list<\Symfony\Component\Process\Process>
     */
    protected array $liveServerProcesses = [];

    /**
     * Live server directories created by the test.
     *
     * @var list<string>
     */
    protected array $liveServerDirectories = [];

    /**
     * Starts a live server process and tracks it for teardown.
     *
     * @param list<string> $command Process command vector.
     * @param string $cwd Working directory running the process.
     * @param array<string, string> $env Environment variables for the process.
     * @return \Symfony\Component\Process\Process Started server process.
     */
    protected function startLiveServerProcess(array $command, string $cwd, array $env): Process
    {
        $process = new Process($command, $cwd, $env);

        $process->start();

        $this->liveServerProcesses[] = $process;

        return $process;
    }

    /**
     * Waits until the given TCP port accepts connections.
     *
     * Polls the socket in short slices until the deadline so slow server
     * boots still pass without stretching every suite run.
     *
     * @param string $host Host accepting connections.
     * @param int $port Port accepting connections.
     * @param int $timeoutSeconds Seconds to wait for the port.
     * @return bool Whether the port accepted a connection in time.
     */
    protected function waitForLiveServerPort(string $host, int $port, int $timeoutSeconds = 20): bool
    {
        $deadline = microtime(true) + $timeoutSeconds;

        set_error_handler(static fn(): bool => true);

        try {
            do {
                $socket = stream_socket_client("tcp://{$host}:{$port}", $code, $message, 1);

                if (is_resource($socket)) {
                    fclose($socket);

                    return true;
                }

                usleep(200000);
            } while (microtime(true) < $deadline);
        } finally {
            restore_error_handler();
        }

        return false;
    }

    /**
     * Fetches an HTTP response body from a live server.
     *
     * @param string $url URL served by the live server.
     * @param int $timeoutSeconds Seconds to wait for the response.
     * @return array{status: int, body: string} Response status and body.
     */
    protected function fetchLiveServerBody(string $url, int $timeoutSeconds = 10): array
    {
        $response = (new Client(['timeout' => $timeoutSeconds]))->get($url);

        return [
            'status' => $response->getStatusCode(),
            'body' => (string)$response->getBody(),
        ];
    }

    /**
     * Finds an ephemeral TCP port for a live server.
     *
     * Binds port zero, reads the assigned port back, and releases the
     * socket so the server binds it next.
     *
     * @return int Free TCP port number.
     */
    protected function freeLiveServerPort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $code, $message);

        if (!is_resource($socket)) {
            $this->fail('Could not allocate an ephemeral port for the live server.');
        }

        $name = stream_socket_get_name($socket, false);

        fclose($socket);

        return (int)substr((string)$name, (int)strrpos((string)$name, ':') + 1);
    }

    /**
     * Resolves the plugin root directory.
     *
     * Walks up from this support file so live tests address entries and
     * the test application without relative-path guessing.
     *
     * @return string Absolute path to the plugin root.
     */
    protected function pluginRootDirectory(): string
    {
        return dirname(__DIR__, 2);
    }

    /**
     * Creates a tracked directory for live server state files.
     *
     * @param string $prefix Directory name prefix.
     * @return string Absolute path to the created directory.
     */
    protected function makeLiveServerDirectory(string $prefix): string
    {
        $directory = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . $prefix . getmypid() . '-' . count($this->liveServerDirectories);

        mkdir($directory, 0777, true);

        $this->liveServerDirectories[] = $directory;

        return $directory;
    }

    /**
     * Stops every tracked live server process.
     *
     * @return void
     */
    protected function stopLiveServerProcesses(): void
    {
        foreach ($this->liveServerProcesses as $process) {
            if ($process->isRunning()) {
                $process->stop(5);
            }
        }

        $this->liveServerProcesses = [];
    }

    /**
     * Determines whether a server binary answers its version probe.
     *
     * Runs the probe with a short timeout and matches its output so live
     * tests skip where the binary is missing or unrelated instead of
     * failing on the spawn.
     *
     * @param string $binary Absolute path to the server binary.
     * @param string $probeArgument Version probe argument.
     * @param string $needle Output fragment proving the binary identity.
     * @return bool Whether the binary answered the probe.
     */
    protected function serverBinaryMeetsProbe(string $binary, string $probeArgument, string $needle): bool
    {
        try {
            $process = new Process([$binary, $probeArgument], $this->pluginRootDirectory());

            $process->setTimeout(15);
            $process->run();

            return str_contains($process->getOutput(), $needle);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Removes every tracked live server directory with its files.
     *
     * @return void
     */
    protected function removeLiveServerDirectories(): void
    {
        foreach ($this->liveServerDirectories as $directory) {
            $this->removeLiveServerDirectory($directory);
        }

        $this->liveServerDirectories = [];
    }

    /**
     * Removes a live server directory with nested files.
     *
     * Walks dotfiles as well as visible entries so RoadRunner configuration
     * files inside live directories clean up together with their state files,
     * and recurses into nested directories so published worker files inside
     * public directories clean up together with their state files.
     *
     * @param string $directory Absolute path to the directory.
     * @return void
     */
    protected function removeLiveServerDirectory(string $directory): void
    {
        $visible = glob($directory . DIRECTORY_SEPARATOR . '*');

        $dotted = glob($directory . DIRECTORY_SEPARATOR . '.*');

        $files = array_merge(
            is_array($visible) ? $visible : [],
            is_array($dotted) ? $dotted : [],
        );

        foreach ($files as $file) {
            if (basename($file) === '.') {
                continue;
            }

            if (basename($file) === '..') {
                continue;
            }

            if (is_file($file) || is_link($file)) {
                unlink($file);

                continue;
            }

            if (is_dir($file)) {
                $this->removeLiveServerDirectory($file);
            }
        }

        if (is_dir($directory)) {
            rmdir($directory);
        }
    }
}
