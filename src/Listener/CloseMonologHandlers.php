<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Listener;

use Cake\Collection\Collection;
use Cake\Event\EventListenerInterface;
use Cake\Log\Log;
use Crustum\Stimulus\Event\WorkerStoppingEvent;
use Psr\Log\LoggerInterface;

/**
 * Closes log handlers when the worker stops.
 *
 * Closes engines that expose a `close` method so worker shutdown releases
 * file handles and sockets. Core engines open their resources
 * per write and need no closing, so the hook serves engines holding
 * persistent handles. Unknown channel names are skipped.
 *
 * The channel names are injected. They are wired to the configured engines
 * by the worker in a later phase.
 *
 * Listened on the worker stopping event.
 */
class CloseMonologHandlers implements EventListenerInterface
{
    /**
     * Log channel names whose handlers are closed.
     *
     * @var list<string>
     */
    protected array $channels;

    /**
     * Creates a new log handler closing listener.
     *
     * @param list<string> $channels Log channel names whose handlers are closed.
     */
    public function __construct(array $channels = [])
    {
        $this->channels = $channels;
    }

    /**
     * Returns the events this listener handles.
     *
     * @return array<string, mixed> Event names mapped to handler methods.
     */
    public function implementedEvents(): array
    {
        return [
            'Stimulus.workerStopping' => 'handleWorkerStopping',
        ];
    }

    /**
     * Closes the handler of every known channel.
     *
     * Runs a collection pipeline over the log engines behind the
     * injected channel names.
     *
     * @param \Crustum\Stimulus\Event\WorkerStoppingEvent $event Stopping worker operation.
     * @return void
     */
    public function handleWorkerStopping(WorkerStoppingEvent $event): void
    {
        (new Collection($this->channels))
            ->map(fn(string $channel): ?LoggerInterface => Log::engine($channel))
            ->filter(fn($engine): bool => $engine instanceof LoggerInterface && method_exists($engine, 'close'))
            ->each(fn($engine) => $this->closeEngine($engine));
    }

    /**
     * Closes the handler of the given log engine.
     *
     * @param mixed $engine Log engine exposing a close hook.
     * @return void
     */
    protected function closeEngine(mixed $engine): void
    {
        if (!is_object($engine) || !method_exists($engine, 'close')) {
            return;
        }

        $engine->close();
    }
}
