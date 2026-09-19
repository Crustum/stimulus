<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Bridge\FrankenPhp;

use RuntimeException;

/**
 * Reads and writes the FrankenPHP server state file.
 *
 * Persists the FrankenPHP master process ID alongside the arbitrary server
 * state written by the worker commands so process inspectors can reload or
 * stop a running FrankenPHP server through its admin endpoint.
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
     * @return array{masterProcessId: int|null, state: array<string, mixed>} Master process ID and stored state.
     */
    public function read(): array
    {
        $state = [];

        if (is_readable($this->path)) {
            $decoded = json_decode((string)file_get_contents($this->path), true);

            $state = is_array($decoded) ? $decoded : [];
        }

        return [
            'masterProcessId' => $state['masterProcessId'] ?? null,
            'state' => $state['state'] ?? [],
        ];
    }

    /**
     * Writes the given process ID to the server state file.
     *
     * @param int $masterProcessId FrankenPHP master process ID.
     * @return void
     * @throws \RuntimeException When neither the state file nor its directory is writable.
     */
    public function writeProcessId(int $masterProcessId): void
    {
        if (! is_writable($this->path) && ! is_writable(dirname($this->path))) {
            throw new RuntimeException('Unable to write to process ID file.');
        }

        file_put_contents($this->path, json_encode(
            array_merge($this->read(), ['masterProcessId' => $masterProcessId]),
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
