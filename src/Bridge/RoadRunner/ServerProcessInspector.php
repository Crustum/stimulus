<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Bridge\RoadRunner;

use Crustum\Stimulus\Contracts\ServerProcessInspector as ServerProcessInspectorContract;
use Crustum\Stimulus\Process\SymfonyProcessFactory;
use Crustum\Stimulus\System\ProcessTerminator;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Inspects and controls the RoadRunner server process.
 *
 * Reads the master process ID and RPC connection details from the server
 * state file to report liveness and to reload or stop the server. Liveness
 * and termination go through the cross-platform terminator so the commands
 * keep working where POSIX signalling is unavailable.
 */
class ServerProcessInspector implements ServerProcessInspectorContract
{
    use FindsRoadRunnerBinaryTrait;

    /**
     * Creates a new RoadRunner server process inspector.
     *
     * @param \Crustum\Stimulus\Bridge\RoadRunner\ServerStateFile $serverStateFile State file tracking the server process.
     * @param \Crustum\Stimulus\Process\SymfonyProcessFactory $processFactory Factory building the reload process.
     * @param \Crustum\Stimulus\System\ProcessTerminator $terminator Cross-platform liveness and termination helper.
     */
    public function __construct(
        protected ServerStateFile $serverStateFile,
        protected SymfonyProcessFactory $processFactory,
        protected ProcessTerminator $terminator,
    ) {
    }

    /**
     * Determines if the RoadRunner server process is running.
     *
     * @return bool Whether the master process recorded in the state file is alive.
     */
    public function serverIsRunning(): bool
    {
        [
            'masterProcessId' => $masterProcessId,
        ] = $this->serverStateFile->read();

        return $masterProcessId && $this->terminator->isRunning($masterProcessId);
    }

    /**
     * Reloads the RoadRunner workers.
     *
     * @return void
     * @throws \RuntimeException When the RoadRunner binary reports a reload error.
     */
    public function reloadServer(): void
    {
        [
            'state' => [
                'host' => $host,
                'rpcPort' => $rpcPort,
            ],
        ] = $this->serverStateFile->read();

        $process = $this->processFactory->createProcess([
            $this->findRoadRunnerBinary(),
            'reset',
            '-o',
            'version=3',
            '-o',
            "rpc.listen=tcp://{$host}:{$rpcPort}",
            '-s',
        ], $this->applicationBasePath());

        $process->start();

        $process->waitUntil(function ($type, $buffer): true {
            if ($type === Process::ERR) {
                throw new RuntimeException('Cannot reload RoadRunner: ' . $buffer);
            }

            return true;
        });
    }

    /**
     * Stops the RoadRunner server.
     *
     * @return bool Whether the master process is gone after the stop.
     */
    public function stopServer(): bool
    {
        [
            'masterProcessId' => $masterProcessId,
        ] = $this->serverStateFile->read();

        return $this->terminator->terminate($masterProcessId);
    }
}
