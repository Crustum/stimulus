<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Listener;

use Cake\Cache\Cache;
use Cake\Cache\Engine\ArrayEngine;
use Cake\Collection\Collection;
use Cake\Event\EventListenerInterface;
use Crustum\Stimulus\Event\RequestReceivedEvent;
use Crustum\Stimulus\Trait\RefreshesStaticConfigTrait;

/**
 * Flushes in-memory array caches between worker requests.
 *
 * Clears every configured cache backed by the array engine so values
 * remembered during one request never leak into the next, while leaving persistent
 * stores such as file or database caches untouched. Pools without
 * rebuildable configuration are skipped through the shared
 * static-configuration contract: host bootstraps may register partial
 * entries the engine builder rejects, and the worker must not fail the
 * whole operation over them.
 *
 * Listened on the request received event.
 */
class FlushArrayCache implements EventListenerInterface
{
    use RefreshesStaticConfigTrait;

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
     * Clears every array-engine cache pool.
     *
     * Clears every configured pool backed by the array engine through a
     * collection pipeline, since configured pools cannot be filtered by engine
     * without inspecting each one.
     *
     * @param \Crustum\Stimulus\Event\RequestReceivedEvent $event Received request operation.
     * @return void
     */
    public function handleRequestReceived(RequestReceivedEvent $event): void
    {
        (new Collection(Cache::configured()))
            ->filter(fn(string $name): bool => $this->isArrayEnginePool($name))
            ->each(fn(string $name): bool => Cache::clear($name));
    }

    /**
     * Determines whether the given pool runs on the array engine.
     *
     * Pools without rebuildable configuration never reach the engine
     * builder, so stillborn host entries stay silent instead of failing the
     * operation.
     *
     * @param string $name Cache pool name under inspection.
     * @return bool Whether the pool runs on the array engine.
     */
    protected function isArrayEnginePool(string $name): bool
    {
        if (!$this->isRebuildableConfig(Cache::class, Cache::getConfig($name))) {
            return false;
        }

        return Cache::pool($name) instanceof ArrayEngine;
    }
}
