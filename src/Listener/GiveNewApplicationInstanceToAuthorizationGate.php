<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Listener;

use Authorization\AuthorizationService;
use Authorization\AuthorizationServiceInterface;
use Authorization\AuthorizationServiceProviderInterface;
use Cake\Event\EventListenerInterface;
use Cake\Http\ServerRequest;
use Crustum\Stimulus\Event\RequestReceivedEvent;

/**
 * Gives the authorization service a fresh instance per worker operation.
 *
 * Replaces the `authorization` request attribute, where the authorization
 * middleware stores the service, with a sandbox-fresh instance built by the
 * configured provider so policies resolve against the current application.
 * The replacement resets the per-request `authorizationChecked`
 * state so one operation never inherits the check flag of the previous one.
 * There is no authentication listener: identity stays request-scoped and the
 * request itself is fresh every operation.
 *
 * Listened on the request received event. Worker proof of the sandbox swap
 * lands with the worker in a later phase.
 */
class GiveNewApplicationInstanceToAuthorizationGate implements EventListenerInterface
{
    /**
     * Provider building the sandbox-fresh authorization service.
     *
     * @var \Authorization\AuthorizationServiceProviderInterface|callable|null
     */
    protected mixed $provider;

    /**
     * Creates a new authorization gate refreshing listener.
     *
     * @param \Authorization\AuthorizationServiceProviderInterface|callable|null $provider Provider or factory building the fresh service per operation. Null leaves the current service untouched.
     */
    public function __construct(AuthorizationServiceProviderInterface|callable|null $provider = null)
    {
        $this->provider = $provider;
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
     * Replaces the request authorization service with a fresh instance.
     *
     * Hosts without the authorization package keep their request untouched.
     * Requests carrying a foreign `authorization` attribute or no configured
     * provider also pass through, since no fresh service can be built for
     * them.
     *
     * @param \Crustum\Stimulus\Event\RequestReceivedEvent $event Received request operation.
     * @return void
     */
    public function handleRequestReceived(RequestReceivedEvent $event): void
    {
        if (!class_exists(AuthorizationService::class)) {
            return;
        }

        $request = $event->getRequest();
        $current = $request->getAttribute('authorization');

        if ($current !== null && !$current instanceof AuthorizationServiceInterface) {
            return;
        }

        $service = $this->resolveService($request);

        if (!$service instanceof AuthorizationServiceInterface) {
            return;
        }

        $event->setRequest($request->withAttribute('authorization', $service));
    }

    /**
     * Builds the fresh authorization service for the given request.
     *
     * @param \Cake\Http\ServerRequest $request Received request operation.
     * @return \Authorization\AuthorizationServiceInterface|null Fresh service, null when no provider is configured.
     */
    protected function resolveService(ServerRequest $request): ?AuthorizationServiceInterface
    {
        if ($this->provider instanceof AuthorizationServiceProviderInterface) {
            return $this->provider->getAuthorizationService($request);
        }

        if (is_callable($this->provider)) {
            $service = ($this->provider)($request);

            return $service instanceof AuthorizationServiceInterface ? $service : null;
        }

        return null;
    }
}
