<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Listener;

use Cake\Cache\Cache;
use Cake\Collection\Collection;
use Cake\Event\EventListenerInterface;
use Crustum\Stimulus\Event\RequestReceivedEvent;
use Crustum\Stimulus\Trait\RefreshesStaticConfigTrait;

/**
 * Gives the cache manager fresh engines per worker operation.
 *
 * Drops every resolved cache engine and re-registers its configuration, so engines rebuild lazily against the sandbox on next use
 * while their configuration survives. The refresh runs through the shared
 * static-configuration contract, which validates rebuildability before the
 * drop: entries no worker can rebuild stay untouched instead of dying for
 * the rest of the process. `Cache::clear()` was rejected: it wipes data but
 * keeps the stale engine behind the pool.
 *
 * Listened on the request received event. Worker proof of the sandbox swap
 * lands with the worker in a later phase.
 */
class GiveNewApplicationInstanceToCacheManager implements EventListenerInterface
{
    use RefreshesStaticConfigTrait;

    /**
     * Cache pool names whose engines are refreshed, null refreshes all configured pools.
     *
     * @var list<string>|null
     */
    protected ?array $pools;

    /**
     * Creates a new cache manager refreshing listener.
     *
     * @param list<string>|null $pools Cache pool names whose engines are refreshed, null refreshes all configured pools.
     */
    public function __construct(?array $pools = null)
    {
        $this->pools = $pools;
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
     * Refreshes the engine of every known cache pool.
     *
     * Pools without rebuildable configuration are skipped. The pools iterate
     * through a collection pipeline.
     *
     * @param \Crustum\Stimulus\Event\RequestReceivedEvent $event Received request operation.
     * @return void
     */
    public function handleRequestReceived(RequestReceivedEvent $event): void
    {
        (new Collection($this->pools ?? Cache::configured()))
            ->filter(fn(string $name): bool => Cache::getConfig($name) !== null)
            ->each(fn(string $name): bool => $this->refreshStaticConfig(Cache::class, $name));
    }
}
