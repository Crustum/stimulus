<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Listener;

use Cake\Cache\Cache;
use Cake\Collection\Collection;
use Cake\Event\EventListenerInterface;
use Cake\Queue\QueueManager as CakeQueueManager;
use Crustum\Stimulus\Event\RequestReceivedEvent;
use Crustum\Stimulus\Trait\RefreshesStaticConfigTrait;

/**
 * Gives the queue manager fresh clients per worker operation.
 *
 * Drops every resolved queue client and re-registers its configuration against the soft `cakephp/queue` dependency, so clients
 * rebuild against the sandbox on next push while their configuration
 * survives. The queue refresh validates through the shared
 * static-configuration contract before touching anything. The unique-job
 * cache pool drops ahead of the queue re-registration on purpose: the queue
 * `setConfig` re-registers that pool itself as a side effect, which would
 * collide with the still-registered pool. Hosts without the queue package
 * keep their state untouched.
 *
 * Listened on the request received event. Worker proof of the sandbox swap
 * lands with the worker in a later phase.
 */
class GiveNewApplicationInstanceToQueueManager implements EventListenerInterface
{
    use RefreshesStaticConfigTrait;

    /**
     * Queue names whose clients are refreshed, null refreshes all configured queues.
     *
     * @var list<string>|null
     */
    protected ?array $queues;

    /**
     * Creates a new queue manager refreshing listener.
     *
     * @param list<string>|null $queues Queue names whose clients are refreshed, null refreshes all configured queues.
     */
    public function __construct(?array $queues = null)
    {
        $this->queues = $queues;
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
     * Refreshes the client of every known queue.
     *
     * Queues without rebuildable configuration are skipped. The queues
     * iterate through a collection pipeline.
     *
     * @param \Crustum\Stimulus\Event\RequestReceivedEvent $event Received request operation.
     * @return void
     */
    public function handleRequestReceived(RequestReceivedEvent $event): void
    {
        if (!class_exists(CakeQueueManager::class)) {
            return;
        }

        (new Collection($this->queues ?? CakeQueueManager::configured()))
            ->filter(fn(string $name): bool => CakeQueueManager::getConfig($name) !== null)
            ->each(fn(string $name): bool => $this->refreshClient($name));
    }

    /**
     * Drops the resolved client of the given queue and re-registers its configuration.
     *
     * Validates through the shared static-configuration contract before
     * touching anything, so a failed validation leaves the queue exactly as
     * found. The unique-job cache pool drops between the queue drop and the
     * queue re-registration because the re-registration recreates that pool
     * itself.
     *
     * @param string $name Queue name whose client is refreshed.
     * @return bool Whether the queue configuration survived the refresh.
     */
    protected function refreshClient(string $name): bool
    {
        $config = CakeQueueManager::getConfig($name);

        if (!$this->isRebuildableConfig(CakeQueueManager::class, $config)) {
            return false;
        }

        CakeQueueManager::drop($name);

        if (isset($config['uniqueCacheKey']) && is_string($config['uniqueCacheKey'])) {
            Cache::drop($config['uniqueCacheKey']);
        }

        CakeQueueManager::setConfig($name, $config);

        return true;
    }
}
