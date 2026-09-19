<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Bridge\Swoole\Handlers;

use Cake\Collection\Collection;
use Cake\Core\Configure;
use Cake\Http\BaseApplication;
use Cake\Http\ServerRequest;
use Crustum\Stimulus\Bridge\Swoole\SwooleClient;
use Crustum\Stimulus\Bridge\Swoole\SwooleExtension;
use Crustum\Stimulus\Bridge\Swoole\WorkerState;
use Crustum\Stimulus\Http\Stream;
use Crustum\Stimulus\Worker\ApplicationFactory;
use Crustum\Stimulus\Worker\Worker;
use Psr\Http\Message\ResponseInterface;
use Swoole\Http\Server as SwooleServer;
use Throwable;

/**
 * Handles the Swoole worker start event.
 *
 * It clears the opcode cache unless disabled, records the worker state, boots the Stimulus worker
 * with the Swoole client plus the server tables, streams handled requests to
 * the console in debug mode, and names the worker process unless disabled.
 * The application factory needs an application class and a config
 * directory, so both resolve from the server state with host-application
 * fallbacks. The per-second tick dispatch stays empty; the master-side
 * timer in `OnServerStart` drives worker ticks instead.
 */
class OnWorkerStart
{
    /**
     * Creates a new worker-start handler.
     *
     * @param \Crustum\Stimulus\Bridge\Swoole\SwooleExtension $extension Extension helper naming the process.
     * @param string $basePath Base path of the host application.
     * @param array<string, mixed> $serverState Server state booting the worker.
     * @param \Crustum\Stimulus\Bridge\Swoole\WorkerState $workerState State shared between server callbacks.
     * @param bool $shouldSetProcessName Whether the worker process is renamed.
     */
    public function __construct(
        protected SwooleExtension $extension,
        protected string $basePath,
        protected array $serverState,
        protected WorkerState $workerState,
        protected bool $shouldSetProcessName = true,
    ) {
    }

    /**
     * Handles the worker-start Swoole event.
     *
     * @param mixed $server Swoole HTTP server that started the worker.
     * @param int $workerId ID of the started worker.
     * @return void
     */
    public function __invoke(mixed $server, int $workerId): void
    {
        if ($this->shouldClearOpcodeCache()) {
            $this->clearOpcodeCache();
        }

        $pid = function_exists('posix_getpid') ? posix_getpid() : getmypid();

        $this->workerState->server = $server;
        $this->workerState->workerId = $workerId;
        $this->workerState->workerPid = is_int($pid) ? $pid : null;
        $this->workerState->worker = $this->bootWorker($server);

        $this->dispatchServerTickTaskEverySecond($server);
        $this->streamRequestsToConsole($server);

        if ($this->shouldSetProcessName) {
            $isTaskWorker = is_object($server) &&
                isset($server->setting['worker_num']) &&
                $workerId >= $server->setting['worker_num'];

            $this->extension->setProcessName(
                (string)($this->serverState['appName'] ?? 'Stimulus'),
                $isTaskWorker ? 'task worker process' : 'worker process',
            );
        }
    }

    /**
     * Boots the Stimulus worker and application.
     *
     * Shares the cache table, the Swoole server, and the worker state with
     * the application factory, binds the server tables onto the worker
     * manager to activate `StimulusManager::table()`, and shuts the server
     * down when the boot fails.
     *
     * @param mixed $server Swoole HTTP server that started the worker.
     * @return \Crustum\Stimulus\Worker\Worker|null Booted worker, or null when the boot failed.
     */
    protected function bootWorker(mixed $server): ?Worker
    {
        try {
            $worker = new Worker(
                new ApplicationFactory($this->applicationClass(), $this->configDir()),
                $this->workerState->client = new SwooleClient(),
            );

            $worker->boot([
                'stimulus.cacheTable' => $this->workerState->cacheTable,
                SwooleServer::class => $server,
                WorkerState::class => $this->workerState,
            ]);

            $worker->getManager()->setTables($this->workerState->tables);

            return $worker;
        } catch (Throwable $throwable) {
            Stream::shutdown($throwable);

            if (is_object($server) && method_exists($server, 'shutdown')) {
                $server->shutdown();
            }

            return null;
        }
    }

    /**
     * Starts the Stimulus server tick dispatching the tick task every second.
     *
     * This hook stays empty; the master-side per-second timer registered by
     * `OnServerStart` drives worker ticks, so the port keeps the same
     * empty hook for parity.
     *
     * @param mixed $server Swoole HTTP server that started the worker.
     * @return void
     */
    protected function dispatchServerTickTaskEverySecond(mixed $server): void
    {
    }

    /**
     * Registers the request handled listener streaming request information per request.
     *
     * Streams handled requests only when the debug flag is enabled. The guarded worker call keeps the file loadable
     * without the extension.
     *
     * @param mixed $server Swoole HTTP server that started the worker.
     * @return void
     */
    protected function streamRequestsToConsole(mixed $server): void
    {
        $worker = $this->workerState->worker;

        if (!is_object($worker) || !method_exists($worker, 'onRequestHandled')) {
            return;
        }

        $worker->onRequestHandled(function (
            ServerRequest $request,
            ResponseInterface $response,
            BaseApplication $sandbox,
        ): void {
            if (!(bool)Configure::read('debug')) {
                return;
            }

            Stream::request(
                $request->getMethod(),
                (string)$request->getUri(),
                $response->getStatusCode(),
                (microtime(true) - ($this->workerState->lastRequestTime ?? microtime(true))) * 1000,
            );
        });
    }

    /**
     * Determines if the opcode cache should be cleared.
     *
     * @return bool Whether the opcode cache is cleared on worker start.
     */
    protected function shouldClearOpcodeCache(): bool
    {
        return (bool)($this->serverState['stimulusConfig']['swoole']['clear_opcache'] ?? true);
    }

    /**
     * Clears the APCu and opcode caches.
     *
     * Runs the clear functions through a collection pipeline as a functional
     * iteration. Capability-checked
     * names only reach the invocation, so missing functions never fatal.
     *
     * @return void
     */
    protected function clearOpcodeCache(): void
    {
        (new Collection(['apcu_clear_cache', 'opcache_reset']))
            ->each(function (string $function): void {
                if (is_callable($function)) {
                    call_user_func($function);
                }
            });
    }

    /**
     * Resolves the application class booting the worker application.
     *
     * Falls back from the server state written by the starter command
     * through the host environment to the conventional application class.
     * The state file carries the configured `Stimulus.appClass` value. The
     * resolved name is validated before use so unknown classes fall back
     * to the base application class instead of fataling inside the
     * factory, reporting misconfiguration clearly.
     *
     * @return class-string<\Cake\Http\BaseApplication> Host application class name.
     */
    protected function applicationClass(): string
    {
        $appClass = $this->serverState['appClass']
            ?? $_SERVER['STIMULUS_APP_CLASS']
            ?? $_ENV['STIMULUS_APP_CLASS']
            ?? getenv('STIMULUS_APP_CLASS');

        if ($appClass === false) {
            $appClass = 'App\\Application';
        }

        if (is_string($appClass) && is_subclass_of($appClass, BaseApplication::class)) {
            return $appClass;
        }

        return BaseApplication::class;
    }

    /**
     * Resolves the config directory booting the worker application.
     *
     * @return string Absolute path to the host application config directory.
     */
    protected function configDir(): string
    {
        return $this->basePath . DIRECTORY_SEPARATOR . 'config';
    }
}
