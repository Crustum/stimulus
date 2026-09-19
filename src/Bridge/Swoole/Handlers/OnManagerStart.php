<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Bridge\Swoole\Handlers;

use Crustum\Stimulus\Bridge\Swoole\SwooleExtension;

/**
 * Handles the Swoole manager process start event.
 *
 * It names the manager process unless disabled.
 */
class OnManagerStart
{
    /**
     * Creates a new manager-start handler.
     *
     * @param \Crustum\Stimulus\Bridge\Swoole\SwooleExtension $extension Extension helper naming the process.
     * @param string $appName Application name shown in the process title.
     * @param bool $shouldSetProcessName Whether the manager process is renamed.
     */
    public function __construct(
        protected SwooleExtension $extension,
        protected string $appName,
        protected bool $shouldSetProcessName = true,
    ) {
    }

    /**
     * Handles the manager-start Swoole event.
     *
     * @return void
     */
    public function __invoke(): void
    {
        if ($this->shouldSetProcessName) {
            $this->extension->setProcessName($this->appName, 'manager process');
        }
    }
}
