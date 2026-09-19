<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Bridge\Swoole;

use RuntimeException;

/**
 * Reads and writes the Swoole server state file.
 *
 * Pulled into the command phase as a dependency of the Swoole lifecycle
 * commands; the remaining Swoole bridge lands in P9.
 */
class ServerStateFile
{
    /**
     * Creates a new server state file instance.
     *
     * @param string $path Absolute path to the JSON state file.
     */
    public function __construct(protected string $path)
    {
    }

    /**
     * Reads the server state from the server state file.
     *
     * @return array{masterProcessId: int|null, managerProcessId: int|null, state: array<string, mixed>} Process IDs and stored state.
     */
    public function read(): array
    {
        $state = is_readable($this->path)
            ? json_decode((string)file_get_contents($this->path), true)
            : [];

        if (! is_array($state)) {
            $state = [];
        }

        return [
            'masterProcessId' => $state['masterProcessId'] ?? null,
            'managerProcessId' => $state['managerProcessId'] ?? null,
            'state' => $state['state'] ?? [],
        ];
    }

    /**
     * Writes the given process IDs to the server state file.
     *
     * @param int $masterProcessId Swoole master process ID.
     * @param int $managerProcessId Swoole manager process ID.
     * @return void
     * @throws \RuntimeException When neither the state file nor its directory is writable.
     */
    public function writeProcessIds(int $masterProcessId, int $managerProcessId): void
    {
        if (! is_writable($this->path) && ! is_writable(dirname($this->path))) {
            throw new RuntimeException('Unable to write to process ID file.');
        }

        file_put_contents($this->path, json_encode(
            array_merge($this->read(), [
                'masterProcessId' => $masterProcessId,
                'managerProcessId' => $managerProcessId,
            ]),
            JSON_PRETTY_PRINT,
        ));
    }

    /**
     * Writes the given state array to the server state file.
     *
     * @param array<string, mixed> $newState Server state to persist.
     * @return void
     * @throws \RuntimeException When neither the state file nor its directory is writable.
     */
    public function writeState(array $newState): void
    {
        if (! is_writable($this->path) && ! is_writable(dirname($this->path))) {
            throw new RuntimeException('Unable to write to process ID file.');
        }

        file_put_contents($this->path, json_encode(
            array_merge($this->read(), ['state' => $newState]),
            JSON_PRETTY_PRINT,
        ));
    }

    /**
     * Deletes the process ID file.
     *
     * @return bool Whether the state file was deleted.
     */
    public function delete(): bool
    {
        if (is_writable($this->path)) {
            return unlink($this->path);
        }

        return false;
    }

    /**
     * Gets the path to the process ID file.
     *
     * @return string Absolute path to the JSON state file.
     */
    public function path(): string
    {
        return $this->path;
    }
}
