<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Command\Trait;

use Cake\Collection\Collection;

/**
 * Forgets the application environment variables before spawning servers.
 *
 * The phpdotenv parser is not installed, so variable names are parsed from
 * the env file lines directly. Cleared names are removed from the process
 * environment and the PHP super-globals. The env file path resolves through
 * the base command.
 *
 * @mixin \Crustum\Stimulus\Command\StimulusCommand
 */
trait InteractsWithEnvironmentVariablesTrait
{
    /**
     * Forgets the current process environment variables.
     *
     * Collects the variable names declared in the env file as a collection
     * pipeline, then clears each name from the process. A missing env file
     * yields an empty pipeline.
     *
     * @return void
     */
    public function forgetEnvironmentVariables(): void
    {
        (new Collection($this->environmentVariableNames()))
            ->each(function (string $name): void {
                putenv($name);

                unset($_ENV[$name], $_SERVER[$name]);
            });
    }

    /**
     * Gets the variable names declared in the application env file.
     *
     * Blank lines, comments, and lines without an assignment are filtered out
     * of the pipeline before the names are returned.
     *
     * @return array<int, string> Environment variable names from the env file.
     */
    protected function environmentVariableNames(): array
    {
        $path = $this->environmentFilePath();

        if (! is_readable($path)) {
            return [];
        }

        return (new Collection(explode("\n", (string)file_get_contents($path))))
            ->map(fn(string $line): string => trim($line))
            ->filter(
                fn(string $line): bool => $line !== ''
                    && ! str_starts_with($line, '#')
                    && str_contains($line, '='),
            )
            ->map(fn(string $line): string => trim((string)strtok($line, '=')))
            ->filter(
                fn(string $name): bool => preg_match('/\A[A-Za-z_]\w*\z/', $name) === 1,
            )
            ->toList();
    }
}
