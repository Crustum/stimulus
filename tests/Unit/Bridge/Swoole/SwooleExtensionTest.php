<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Bridge\Swoole;

use Crustum\Stimulus\Bridge\Swoole\ServerProcessInspector;
use Crustum\Stimulus\Bridge\Swoole\ServerStateFile;
use Crustum\Stimulus\Bridge\Swoole\SignalDispatcher;
use Crustum\Stimulus\Bridge\Swoole\SwooleExtension;
use Crustum\Stimulus\Bridge\Swoole\WorkerState;
use Crustum\Stimulus\Process\Exec;
use Crustum\Stimulus\Test\TestCase;

/**
 * Tests for the Swoole extension helper and its collaborators.
 *
 * Covers the extension-gated behavior deterministically: CI hosts lack the
 * extension, so signalling reports failure and the CPU count keeps its
 * minimum. The Swoole client, dispatchers, and tables stay in P9 with the
 * extension-gated runtime.
 */
class SwooleExtensionTest extends TestCase
{
    /**
     * Temporary state file paths removed after each test.
     *
     * @var array<int, string>
     */
    protected array $stateFiles = [];

    /**
     * Removes temporary state files after each test.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        foreach ($this->stateFiles as $stateFile) {
            if (file_exists($stateFile)) {
                unlink($stateFile);
            }
        }

        $this->stateFiles = [];

        parent::tearDown();
    }

    /**
     * The installation probe returns a boolean.
     *
     * @return void
     */
    public function testIsInstalledReturnsBool(): void
    {
        $this->assertIsBool((new SwooleExtension())->isInstalled());
    }

    /**
     * The CPU count keeps a minimum of one worker.
     *
     * @return void
     */
    public function testCpuCountKeepsMinimum(): void
    {
        $this->assertGreaterThanOrEqual(1, (new SwooleExtension())->cpuCount());
    }

    /**
     * Container CPU quotas size the workers.
     *
     * @return void
     */
    public function testContainerQuotaSizesWorkers(): void
    {
        $extension = new SwooleExtension(
            static fn(string $path): bool => true,
            static fn(string $path): string|false => "400000 100000\n",
        );

        $this->assertSame(4, $extension->cpuCount());
    }

    /**
     * Signalling without the extension reports failure.
     *
     * @return void
     */
    public function testDispatchWithoutExtensionFails(): void
    {
        if ((new SwooleExtension())->isInstalled()) {
            $this->markTestSkipped('The Swoole extension is installed on this host.');
        }

        $this->assertFalse((new SwooleExtension())->dispatchProcessSignal(1, 0));
    }

    /**
     * Signalling a missing process reports failure on any host.
     *
     * The existence check runs before any signal delivery, so an unused
     * process ID stays false whether the Swoole, OpenSwoole, or neither
     * runtime backs the dispatch. The ID fits the native process ID type
     * while staying above the kernel maximum, unlike wider integers that
     * truncate into the broadcast process group.
     *
     * @return void
     */
    public function testDispatchProcessSignalReportsMissingProcess(): void
    {
        $this->assertFalse((new SwooleExtension())->dispatchProcessSignal(999999999, 0));
    }

    /**
     * The dispatcher reports unreachable processes.
     *
     * @return void
     */
    public function testDispatcherReportsUnreachableProcess(): void
    {
        if ((new SwooleExtension())->isInstalled()) {
            $this->markTestSkipped('The Swoole extension is installed on this host.');
        }

        $dispatcher = new SignalDispatcher(new SwooleExtension());

        $this->assertFalse($dispatcher->canCommunicateWith(1));
    }

    /**
     * The inspector reports a stopped server without state.
     *
     * @return void
     */
    public function testInspectorReportsStoppedWithoutState(): void
    {
        $inspector = new ServerProcessInspector(
            new SignalDispatcher(new SwooleExtension()),
            new ServerStateFile($this->statePath()),
            new Exec(),
        );

        $this->assertFalse($inspector->serverIsRunning());
    }

    /**
     * The worker state carries its defaults.
     *
     * @return void
     */
    public function testWorkerStateDefaults(): void
    {
        $state = new WorkerState();

        $this->assertNull($state->server);
        $this->assertNull($state->workerId);
        $this->assertNull($state->workerPid);
        $this->assertNull($state->worker);
        $this->assertNull($state->client);
        $this->assertNull($state->timerTable);
        $this->assertNull($state->cacheTable);
        $this->assertSame([], $state->tables);
        $this->assertNull($state->tickTimerId);
        $this->assertNull($state->lastRequestTime);
    }

    /**
     * The installation probe reflects the loaded extensions.
     *
     * @return void
     */
    public function testIsInstalledReflectsLoadedExtensions(): void
    {
        $this->assertSame(
            extension_loaded('swoole') || extension_loaded('openswoole'),
            (new SwooleExtension())->isInstalled(),
        );
    }

    /**
     * Container CPU quotas read the cgroup v1 files.
     *
     * @return void
     */
    public function testContainerQuotaReadsCgroupV1Files(): void
    {
        $extension = new SwooleExtension(
            static fn(string $path): bool => true,
            static fn(string $path): string|false => match ($path) {
                '/sys/fs/cgroup/cpu/cpu.cfs_quota_us' => '250000',
                '/sys/fs/cgroup/cpu/cpu.cfs_period_us' => '100000',
                default => false,
            },
        );

        $this->assertSame(3, $extension->cpuCount());
    }

    /**
     * Unlimited quotas fall back to the host CPU count.
     *
     * @return void
     */
    public function testUnlimitedQuotaFallsBackToHostCount(): void
    {
        $extension = new SwooleExtension(
            static fn(string $path): bool => $path === '/sys/fs/cgroup/cpu.max',
            static fn(string $path): string|false => 'max 100000',
        );

        $this->assertGreaterThanOrEqual(1, $extension->cpuCount());
    }

    /**
     * Naming the process never raises without the extension.
     *
     * @return void
     */
    public function testSetProcessNameNeverRaises(): void
    {
        (new SwooleExtension())->setProcessName('Stimulus', 'worker process');

        $this->assertTrue(true);
    }

    /**
     * Builds a tracked temporary state file path.
     *
     * @return string Temporary state file path.
     */
    protected function statePath(): string
    {
        $path = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'stimulus-swoole-ext-test-' . getmypid() . '-' . count($this->stateFiles) . '.json';

        if (file_exists($path)) {
            unlink($path);
        }

        $this->stateFiles[] = $path;

        return $path;
    }
}
