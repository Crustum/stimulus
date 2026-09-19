<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Listener;

use Cake\Collection\Collection;
use Cake\Event\EventListenerInterface;
use Cake\Log\Log;
use Crustum\Stimulus\Event\RequestReceivedEvent;
use Psr\Log\LoggerInterface;

/**
 * Resets stateful loggers between worker requests.
 *
 * Resets engines that expose a `reset` method and clears engines that
 * expose a `clear` method, which covers both Monolog-backed engines and
 * the core in-memory array engine. Unknown channel names are skipped.
 *
 * The channel names are injected. They are wired to the configured engines
 * by the worker in a later phase.
 *
 * Listened on the request received event.
 */
class FlushMonologState implements EventListenerInterface
{
    /**
     * Log channel names whose loggers are reset.
     *
     * @var list<string>
     */
    protected array $channels;

    /**
     * Creates a new logger state flushing listener.
     *
     * @param list<string> $channels Log channel names whose loggers are reset.
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
            'Stimulus.requestReceived' => 'handleRequestReceived',
        ];
    }

    /**
     * Resets the logger of every known channel.
     *
     * Runs a collection pipeline over the log engines behind the
     * injected channel names. Engines exposing a `clear` hook, such as the core in-memory array engine,
     * have it cleared as well.
     *
     * @param \Crustum\Stimulus\Event\RequestReceivedEvent $event Received request operation.
     * @return void
     */
    public function handleRequestReceived(RequestReceivedEvent $event): void
    {
        (new Collection($this->channels))
            ->map(fn(string $channel): ?LoggerInterface => Log::engine($channel))
            ->filter(fn($engine): bool => $engine instanceof LoggerInterface)
            ->each(fn($engine) => $this->resetEngine($engine));
    }

    /**
     * Resets the state of the given log engine.
     *
     * @param mixed $engine Log engine exposing reset or clear hooks.
     * @return void
     */
    protected function resetEngine(mixed $engine): void
    {
        if (!is_object($engine)) {
            return;
        }

        if (method_exists($engine, 'reset')) {
            $engine->reset();
        }

        if (method_exists($engine, 'clear')) {
            $engine->clear();
        }
    }
}
