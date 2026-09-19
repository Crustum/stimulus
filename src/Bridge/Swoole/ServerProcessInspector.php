<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Bridge\Swoole;

use Crustum\Stimulus\Contracts\ServerProcessInspector as ServerProcessInspectorContract;
use Crustum\Stimulus\Process\Exec;

/**
 * Inspects and controls the Swoole server process.
 *
 * Pulled into the command phase as a dependency of the Swoole lifecycle
 * commands; the remaining Swoole bridge lands in P9. The `Exec` helper maps
 * onto the ported process wrapper, and worker IDs resolve through the same
 * manager-first fallback.
 */
class ServerProcessInspector implements ServerProcessInspectorContract
{
    /**
     * Creates a new Swoole server process inspector.
     *
     * @param \Crustum\Stimulus\Bridge\Swoole\SignalDispatcher $dispatcher Dispatcher signalling the server processes.
     * @param \Crustum\Stimulus\Bridge\Swoole\ServerStateFile $serverStateFile State file tracking the server processes.
     * @param \Crustum\Stimulus\Process\Exec $exec Shell wrapper listing the worker processes.
     */
    public function __construct(
        protected SignalDispatcher $dispatcher,
        protected ServerStateFile $serverStateFile,
        protected Exec $exec,
    ) {
    }

    /**
     * Determines if the Swoole server process is running.
     *
     * Probes the manager process when recorded, otherwise the master process,
     * without the compound boolean.
     *
     * @return bool Whether the recorded server process answers signals.
     */
    public function serverIsRunning(): bool
    {
        [
            'masterProcessId' => $masterProcessId,
            'managerProcessId' => $managerProcessId,
        ] = $this->serverStateFile->read();

        if ($managerProcessId) {
            if (! $masterProcessId) {
                return false;
            }

            return $this->dispatcher->canCommunicateWith((int)$managerProcessId);
        }

        if (! $masterProcessId) {
            return false;
        }

        return $this->dispatcher->canCommunicateWith((int)$masterProcessId);
    }

    /**
     * Reloads the Swoole workers.
     *
     * Signals the master process to reload.
     *
     * @return void
     */
    public function reloadServer(): void
    {
        [
            'masterProcessId' => $masterProcessId,
        ] = $this->serverStateFile->read();

        $this->dispatcher->signal((int)$masterProcessId, SIGUSR1);
    }

    /**
     * Stops the Swoole server.
     *
     * Signals the master, manager, and worker processes to stop, discovering
     * workers through `pgrep`. The loop stays imperative while iterating the
     * process IDs.
     *
     * @return bool Whether the stop signals were dispatched.
     */
    public function stopServer(): bool
    {
        [
            'masterProcessId' => $masterProcessId,
            'managerProcessId' => $managerProcessId,
        ] = $this->serverStateFile->read();

        $workerProcessIds = $this->exec->run('pgrep -P ' . (int)$managerProcessId);

        foreach ([$masterProcessId, $managerProcessId, ...$workerProcessIds] as $processId) {
            $this->dispatcher->signal((int)$processId, SIGKILL);
        }

        return true;
    }
}
