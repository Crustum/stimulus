<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Bridge\Swoole;

use Closure;
use Swoole\Process;
use Throwable;

/**
 * Probes the Swoole extension and the host CPU layout.
 *
 * Pulled into the command phase as a dependency of the Swoole starter command; the remaining
 * Swoole bridge (client, dispatchers, tables, handlers) lands in P9. The
 * native process signalling goes through the extension class by name so the
 * file loads on hosts without the extension installed.
 */
class SwooleExtension
{
    /**
     * Creates a new Swoole extension helper.
     *
     * @param \Closure|null $isReadable Readability probe, or null for the default.
     * @param \Closure|null $fileGetContents Contents reader, or null for the default.
     */
    public function __construct(
        protected ?Closure $isReadable = null,
        protected ?Closure $fileGetContents = null,
    ) {
        $this->isReadable ??= is_readable(...);
        $this->fileGetContents ??= static fn(string $path): string|false => is_readable($path)
            ? file_get_contents($path)
            : false;
    }

    /**
     * Determines if the Swoole extension is installed.
     *
     * @return bool Whether the Swoole or OpenSwoole extension is loaded.
     */
    public function isInstalled(): bool
    {
        return extension_loaded('swoole') || extension_loaded('openswoole');
    }

    /**
     * Sends a signal to the given process.
     *
     * The native process class resolves by name behind the runtime lookup so
     * hosts without either extension load this file without errors, and the
     * dispatch is protected against a missing class at runtime. The Swoole
     * process class wins when present and the OpenSwoole one covers
     * OpenSwoole-only hosts.
     *
     * @param int $processId ID of the process to signal.
     * @param int $signal Signal number to send to the process.
     * @return bool Whether the signal was sent successfully.
     */
    public function dispatchProcessSignal(int $processId, int $signal): bool
    {
        if (class_exists(Process::class)) {
            $processClass = Process::class;
        } elseif (class_exists('OpenSwoole\\Process')) {
            $processClass = 'OpenSwoole\\Process';
        } else {
            return false;
        }

        try {
            if (!$processClass::kill($processId, 0)) {
                return false;
            }

            return (bool)$processClass::kill($processId, $signal);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Sets the current process name.
     *
     * @param string $appName Application name shown in the process title.
     * @param string $processName Process role shown in the process title.
     * @return void
     */
    public function setProcessName(string $appName, string $processName): void
    {
        if (PHP_OS_FAMILY === 'Linux') {
            cli_set_process_title('swoole_http_server: ' . $processName . ' for ' . $appName);
        }
    }

    /**
     * Gets the number of CPUs detected on the system.
     *
     * Prefers the container CPU quota, then the Swoole and OpenSwoole
     * counters.
     *
     * @return int Number of CPUs available to the workers.
     */
    public function cpuCount(): int
    {
        $cgroupCpuCount = $this->containerCpuCount();

        if ($cgroupCpuCount !== null) {
            return $cgroupCpuCount;
        }

        if (function_exists('swoole_cpu_num')) {
            return swoole_cpu_num();
        }

        if (function_exists('OpenSwoole\\Util::getCPUNum')) {
            $utilClass = 'OpenSwoole\\Util';

            return (int)$utilClass::getCPUNum();
        }

        return 1;
    }

    /**
     * Gets the CPU count from the container CPU quota, if available.
     *
     * Reads the cgroup v2 quota first, then the cgroup v1 quota and period
     * files.
     *
     * @return int|null Container CPU count, or null when no quota applies.
     */
    protected function containerCpuCount(): ?int
    {
        if (($this->isReadable)('/sys/fs/cgroup/cpu.max')) {
            $cpuMax = ($this->fileGetContents)('/sys/fs/cgroup/cpu.max');

            if ($cpuMax !== false) {
                $parts = preg_split('/\s+/', trim((string)$cpuMax));

                $quota = is_array($parts) ? $parts[0] ?? null : null;
                $period = isset($parts[1]) ? (int)$parts[1] : 0;

                if ($quota !== 'max' && $period > 0) {
                    return max(1, (int)ceil((int)$quota / $period));
                }
            }
        }

        $quotaFile = '/sys/fs/cgroup/cpu/cpu.cfs_quota_us';
        $periodFile = '/sys/fs/cgroup/cpu/cpu.cfs_period_us';

        if (($this->isReadable)($quotaFile) && ($this->isReadable)($periodFile)) {
            $quota = ($this->fileGetContents)($quotaFile);
            $period = ($this->fileGetContents)($periodFile);

            if ($quota !== false && $period !== false) {
                $quota = (int)trim((string)$quota);
                $period = (int)trim((string)$period);

                if ($quota > 0 && $period > 0) {
                    return max(1, (int)ceil($quota / $period));
                }
            }
        }

        return null;
    }
}
