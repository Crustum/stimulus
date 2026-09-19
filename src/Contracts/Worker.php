<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Contracts;

use Cake\Http\ServerRequest;
use Crustum\Stimulus\Http\RequestContext;

/**
 * Contract for long-lived worker instances serving requests, tasks, and ticks.
 */
interface Worker
{
    /**
     * Boot / initialize the worker.
     *
     * @return void
     */
    public function boot(): void;

    /**
     * Handle an incoming request and send the response to the client.
     *
     * @param \Cake\Http\ServerRequest $request Incoming request.
     * @param \Crustum\Stimulus\Http\RequestContext $context Raw request context from the server.
     * @return void
     */
    public function handle(ServerRequest $request, RequestContext $context): void;

    /**
     * Handle an incoming task.
     *
     * @param mixed $data Task payload.
     * @return mixed Task result.
     */
    public function handleTask(mixed $data): mixed;

    /**
     * Terminate the worker.
     *
     * @return void
     */
    public function terminate(): void;
}
