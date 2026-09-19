<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Listener;

use Cake\Collection\Collection;
use Cake\Event\EventListenerInterface;
use Cake\Log\Log;
use Crustum\Stimulus\Event\RequestReceivedEvent;
use Psr\Log\LoggerInterface;

/**
 * Flushes shared log context between worker requests.
 *
 * Core engines are stateless across calls and carry context per
 * write, so there is no core context to reset here. Engines that expose
 * `flushSharedContext` or `withoutContext`, such as bridged
 * log managers with shared context, have those methods invoked in order. Unknown channel names
 * are skipped.
 *
 * The channel names are injected. They are wired to the configured engines
 * by the worker in a later phase.
 *
 * Listened on the request received event.
 */
class FlushLogContext implements EventListenerInterface
{
    /**
     * Log channel names whose context is flushed.
     *
     * @var list<string>
     */
    protected array $channels;

    /**
     * Creates a new log context flushing listener.
     *
     * @param list<string> $channels Log channel names whose context is flushed.
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
     * Flushes the shared and per-request context of every known channel.
     *
     * Applies the shared-context hooks to every injected channel through a
     * collection pipeline, since channels cannot be enumerated from the log
     * registry.
     *
     * @param \Crustum\Stimulus\Event\RequestReceivedEvent $event Received request operation.
     * @return void
     */
    public function handleRequestReceived(RequestReceivedEvent $event): void
    {
        (new Collection($this->channels))
            ->map(fn(string $channel): ?LoggerInterface => Log::engine($channel))
            ->filter(fn($engine): bool => $engine instanceof LoggerInterface)
            ->each(fn($engine) => $this->flushEngineContext($engine));
    }

    /**
     * Flushes the shared and per-request context of the given log engine.
     *
     * @param mixed $engine Log engine exposing shared context hooks.
     * @return void
     */
    protected function flushEngineContext(mixed $engine): void
    {
        if (!is_object($engine)) {
            return;
        }

        if (method_exists($engine, 'flushSharedContext')) {
            $engine->flushSharedContext();
        }

        if (method_exists($engine, 'withoutContext')) {
            $engine->withoutContext();
        }
    }
}
