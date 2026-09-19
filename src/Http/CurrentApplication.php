<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Http;

use Cake\Http\ServerRequest;

/**
 * Holds the request of the operation the worker currently handles.
 *
 * The long-lived worker keeps a dedicated seam to reach the active request. The request
 * listener points this holder at every received operation and the worker
 * core in a later phase reads it back when it boots the sandbox. The holder
 * keeps static state, hence the worker resets it once the operation
 * terminates so a stale request never leaks into the next operation.
 */
class CurrentApplication
{
    /**
     * Request of the operation currently handled.
     *
     * @var \Cake\Http\ServerRequest|null
     */
    protected static ?ServerRequest $request = null;

    /**
     * Points the holder at the given request.
     *
     * @param \Cake\Http\ServerRequest $request Request of the current operation.
     * @return void
     */
    public static function setRequest(ServerRequest $request): void
    {
        static::$request = $request;
    }

    /**
     * Gets the request of the current operation.
     *
     * @return \Cake\Http\ServerRequest|null Request of the current operation, null when idle.
     */
    public static function getRequest(): ?ServerRequest
    {
        return static::$request;
    }

    /**
     * Forgets the held request.
     *
     * @return void
     */
    public static function reset(): void
    {
        static::$request = null;
    }
}
