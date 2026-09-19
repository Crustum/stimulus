<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Event;

/**
 * Shares the base and sandbox application accessors between worker operation events.
 *
 * Consuming events promote the `app` and `sandbox` instances as public
 * object properties; this trait exposes them through the
 * operation-terminated contract shape.
 *
 * @property object $app Base application instance.
 * @property object $sandbox Sandbox application instance.
 */
trait HasApplicationAndSandboxTrait
{
    /**
     * Get the base application instance.
     *
     * @return object Base application instance.
     */
    public function app(): object
    {
        return $this->app;
    }

    /**
     * Get the sandbox version of the application instance.
     *
     * @return object Sandbox application instance.
     */
    public function sandbox(): object
    {
        return $this->sandbox;
    }
}
