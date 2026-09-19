<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Listener;

use Cake\Collection\Collection;
use Cake\Event\EventListenerInterface;
use Cake\Routing\Router;
use Crustum\Stimulus\Event\RequestReceivedEvent;
use ReflectionProperty;

/**
 * Sandboxes URL generation between worker requests.
 *
 * Points the router at the received request and refreshes the cached
 * scheme, host and port, which
 * the router otherwise keeps from the first request it ever saw: its
 * `setRequest` only fills those keys when absent and no public API resets
 * them, so the cached keys are dropped through reflection before the fresh
 * request is pointed. `Router::reload()` was rejected: it wipes every
 * connected route and only a sandbox application could re-register them.
 *
 * Listened on the request received event.
 */
class CreateUrlGeneratorSandbox implements EventListenerInterface
{
    /**
     * Router request context keys refreshed from the incoming request.
     *
     * @var list<string>
     */
    protected const REFRESHED_CONTEXT_KEYS = ['_scheme', '_host', '_port'];

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
     * Points URL generation at the received request.
     *
     * @param \Crustum\Stimulus\Event\RequestReceivedEvent $event Received request operation.
     * @return void
     * @throws \ReflectionException When the router context cannot be inspected.
     */
    public function handleRequestReceived(RequestReceivedEvent $event): void
    {
        $this->refreshRequestContext();

        Router::setRequest($event->getRequest());
    }

    /**
     * Drops the cached scheme, host and port so the router relearns them.
     *
     * Drops the cached router context keys through a collection pipeline.
     *
     * @return void
     * @throws \ReflectionException When the router context cannot be inspected.
     */
    protected function refreshRequestContext(): void
    {
        $property = new ReflectionProperty(Router::class, '_requestContext');
        $context = $property->getValue();

        if (!is_array($context)) {
            return;
        }

        (new Collection(static::REFRESHED_CONTEXT_KEYS))->each(function (string $key) use (&$context): void {
            unset($context[$key]);
        });

        $property->setValue(null, $context);
    }
}
