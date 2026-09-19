<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Bridge\Swoole\Handlers;

use Crustum\Stimulus\Bridge\Swoole\Actions\EnsureRequestsDontExceedMaxExecutionTime;
use Crustum\Stimulus\Bridge\Swoole\ServerStateFile;
use Crustum\Stimulus\Bridge\Swoole\SwooleExtension;
use OpenSwoole\Timer as OpenSwooleTimer;
use Swoole\Timer;

/**
 * Handles the Swoole server start event.
 *
 * It records the master and manager process IDs, names the master process
 * unless disabled, ticks the task workers every second unless disabled,
 * and guards request execution times unless disabled.
 * The Swoole server stays untyped and the timer resolves by name behind
 * the runtime lookup, so the file loads where both  extensions are absent
 * and OpenSwoole-only hosts tick like Swoole hosts;
 * the handler only runs on extension hosts.
 */
class OnServerStart
{
    /**
     * Creates a new server-start handler.
     *
     * @param \Crustum\Stimulus\Bridge\Swoole\ServerStateFile $serverStateFile State file tracking the server processes.
     * @param \Crustum\Stimulus\Bridge\Swoole\SwooleExtension $extension Extension helper naming the process and signalling workers.
     * @param string $appName Application name shown in the process title.
     * @param int $maxExecutionTime Maximum request execution time in seconds, zero disabling the guard.
     * @param mixed $timerTable Swoole table tracking request start times per worker.
     * @param bool $shouldTick Whether task workers receive the per-second tick.
     * @param bool $shouldSetProcessName Whether the master process is renamed.
     */
    public function __construct(
        protected ServerStateFile $serverStateFile,
        protected SwooleExtension $extension,
        protected string $appName,
        protected int $maxExecutionTime,
        protected mixed $timerTable,
        protected bool $shouldTick = true,
        protected bool $shouldSetProcessName = true,
    ) {
    }

    /**
     * Handles the start Swoole event.
     *
     * @param mixed $server Swoole HTTP server that started.
     * @return void
     */
    public function __invoke(mixed $server): void
    {
        $this->serverStateFile->writeProcessIds(
            (int)$server->master_pid,
            (int)$server->manager_pid,
        );

        if ($this->shouldSetProcessName) {
            $this->extension->setProcessName($this->appName, 'master process');
        }

        $timerClass = $this->timerClass();

        if ($timerClass === null) {
            return;
        }

        if ($this->shouldTick) {
            $timerClass::tick(1000, function () use ($server): void {
                $server->task('stimulus-tick');
            });
        }

        if ($this->maxExecutionTime > 0) {
            $timerClass::tick(1000, function () use ($server): void {
                (new EnsureRequestsDontExceedMaxExecutionTime(
                    $this->extension,
                    $this->timerTable,
                    $this->maxExecutionTime,
                    $server,
                ))();
            });
        }
    }

    /**
     * Resolves the timer class available on the host.
     *
     * Prefers the Swoole timer and falls back to the OpenSwoole timer so
     * ticks and execution guards run on OpenSwoole-only hosts; hosts with
     * neither timer skip both like the missing-class path.
     *
     * @return class-string<\Swoole\Timer>|class-string<\OpenSwoole\Timer>|null Timer class name, or null without a runtime.
     */
    protected function timerClass(): ?string
    {
        if (class_exists(Timer::class)) {
            return Timer::class;
        }

        if (class_exists(OpenSwooleTimer::class)) {
            return OpenSwooleTimer::class;
        }

        return null;
    }
}
