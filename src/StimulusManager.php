<?php
declare(strict_types=1);

namespace Crustum\Stimulus;

use Crustum\Stimulus\Contracts\DispatchesTasks;
use Crustum\Stimulus\Trait\ProvidesConcurrencySupportTrait;
use Crustum\Stimulus\Trait\ProvidesDefaultConfigurationOptionsTrait;
use Crustum\Stimulus\Trait\ProvidesRoutingTrait;
use Crustum\Stimulus\Trait\RegistersTickHandlersTrait;
use Exception;
use Throwable;

/**
 * Central worker service for the Stimulus long-lived process.
 */
class StimulusManager
{
    use ProvidesConcurrencySupportTrait;
    use ProvidesDefaultConfigurationOptionsTrait;
    use ProvidesRoutingTrait;
    use RegistersTickHandlersTrait;

    /**
     * Swoole tables bound by the worker entries, keyed by table name.
     *
     * The manager holds them directly once the Swoole worker entries bind them through `setTables`.
     *
     * @var array<string, mixed>
     */
    protected array $tables = [];

    /**
     * Creates a new Stimulus manager.
     *
     * @param \Crustum\Stimulus\Contracts\DispatchesTasks|null $dispatcher Dispatcher resolving background tasks, or null for the default resolution.
     */
    public function __construct(?DispatchesTasks $dispatcher = null)
    {
        $this->dispatcher = $dispatcher;
    }

    /**
     * Binds the Swoole tables shared with the worker.
     *
     * Called by the Swoole worker entries after the tables are created.
     *
     * @param array<string, mixed> $tables Swoole tables keyed by table name.
     * @return $this
     */
    public function setTables(array $tables)
    {
        $this->tables = $tables;

        return $this;
    }

    /**
     * Gets a Swoole table instance.
     *
     * Table access without the Swoole server raises, and unconfigured table names raise with their name.
     *
     * @param string $table Table name configured under `Stimulus.tables`.
     * @return mixed Swoole table instance behind the given name.
     * @throws \Exception When tables are accessed without the Swoole server or the table is unconfigured.
     */
    public function table(string $table): mixed
    {
        if ($this->tables === []) {
            throw new Exception('Tables may only be accessed when using the Swoole server.');
        }

        if (!array_key_exists($table, $this->tables)) {
            throw new Exception("Swoole table [{$table}] has not been configured.");
        }

        return $this->tables[$table];
    }

    /**
     * Formats an exception to a string that should be returned to the client.
     *
     * Returns the full throwable only in debug mode and a generic message otherwise.
     *
     * @param \Throwable $e Throwable to format for the client.
     * @param bool $debug Whether debug mode exposes the throwable.
     * @return string Client-safe exception message.
     */
    public static function formatExceptionForClient(Throwable $e, bool $debug = false): string
    {
        return $debug ? (string)$e : 'Internal server error.';
    }

    /**
     * Writes an error message to STDERR or to the SAPI logger if not in CLI mode.
     *
     * Prefers the standard error stream and falls back to the SAPI logger outside CLI mode.
     *
     * @param string $message Error message to write.
     * @return void
     */
    public static function writeError(string $message): void
    {
        if (defined('STDERR')) {
            fwrite(STDERR, $message . PHP_EOL);

            return;
        }

        error_log($message, 4);
    }
}
