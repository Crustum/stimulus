<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Listener;

use Cake\Collection\Collection;
use Cake\Event\EventListenerInterface;
use Cake\Mailer\TransportFactory;
use Crustum\Stimulus\Event\RequestReceivedEvent;
use Crustum\Stimulus\Trait\RefreshesStaticConfigTrait;

/**
 * Gives the mail manager fresh transports per worker operation.
 *
 * Drops every resolved mail transport and re-registers its configuration, so transports rebuild
 * lazily against the sandbox on next send while their configuration
 * survives. The refresh runs through the shared static-configuration
 * contract, which validates rebuildability before the drop.
 *
 * Listened on the request received event. Worker proof of the sandbox swap
 * lands with the worker in a later phase.
 */
class GiveNewApplicationInstanceToMailManager implements EventListenerInterface
{
    use RefreshesStaticConfigTrait;

    /**
     * Mail transport names whose instances are refreshed, null refreshes all configured transports.
     *
     * @var list<string>|null
     */
    protected ?array $transports;

    /**
     * Creates a new mail manager refreshing listener.
     *
     * @param list<string>|null $transports Mail transport names whose instances are refreshed, null refreshes all configured transports.
     */
    public function __construct(?array $transports = null)
    {
        $this->transports = $transports;
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
     * Refreshes the instance of every known mail transport.
     *
     * Transports without rebuildable configuration are skipped. The
     * transports iterate through a collection pipeline.
     *
     * @param \Crustum\Stimulus\Event\RequestReceivedEvent $event Received request operation.
     * @return void
     */
    public function handleRequestReceived(RequestReceivedEvent $event): void
    {
        (new Collection($this->transports ?? TransportFactory::configured()))
            ->filter(fn(string $name): bool => TransportFactory::getConfig($name) !== null)
            ->each(fn(string $name): bool => $this->refreshStaticConfig(TransportFactory::class, $name));
    }
}
