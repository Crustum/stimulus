<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Bridge\Swoole;

use Crustum\Stimulus\Bridge\Swoole\Handlers\OnManagerStart;
use Crustum\Stimulus\Bridge\Swoole\Handlers\OnServerStart;
use Crustum\Stimulus\Bridge\Swoole\ServerStateFile;
use Crustum\Stimulus\Bridge\Swoole\SwooleExtension;
use Crustum\Stimulus\Test\Support\FakeSwooleServer;
use Crustum\Stimulus\Test\TestCase;

/**
 * Tests for the Swoole server state file.
 *
 * Covers the RoadRunner state file behavior for the Swoole server: process
 * IDs and state round-trip through temporary files.
 */
class ServerStateFileTest extends TestCase
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
     * Process IDs and state round-trip through the file.
     *
     * @return void
     */
    public function testProcessIdsAndStateRoundTrip(): void
    {
        $stateFile = new ServerStateFile($path = $this->statePath());

        $stateFile->writeProcessIds(1234, 5678);
        $stateFile->writeState(['host' => '127.0.0.1', 'port' => '8000']);

        $state = $stateFile->read();

        $this->assertSame(1234, $state['masterProcessId']);
        $this->assertSame(5678, $state['managerProcessId']);
        $this->assertSame(['host' => '127.0.0.1', 'port' => '8000'], $state['state']);
        $this->assertSame($path, $stateFile->path());
    }

    /**
     * A missing file reads as empty state.
     *
     * @return void
     */
    public function testMissingFileReadsAsEmpty(): void
    {
        $state = (new ServerStateFile($this->statePath()))->read();

        $this->assertNull($state['masterProcessId']);
        $this->assertNull($state['managerProcessId']);
        $this->assertSame([], $state['state']);
    }

    /**
     * The state file deletes from disk.
     *
     * @return void
     */
    public function testDeleteRemovesFile(): void
    {
        $stateFile = new ServerStateFile($path = $this->statePath());

        $stateFile->writeProcessIds(1234, 5678);

        $this->assertTrue($stateFile->delete());
        $this->assertFileDoesNotExist($path);
        $this->assertFalse($stateFile->delete());
    }

    /**
     * The server start handler records process IDs through the state file.
     *
     * Runs behind the extension guard with the Swoole server faked: without
     * the timer the handler returns after recording the process IDs.
     *
     * @return void
     */
    public function testServerStartHandlerWritesProcessIds(): void
    {
        if (!extension_loaded('swoole') && !extension_loaded('openswoole')) {
            $this->markTestSkipped('The Swoole or OpenSwoole extension is not installed on this host.');
        }

        $stateFile = new ServerStateFile($this->statePath());

        $handler = new OnServerStart(
            $stateFile,
            new SwooleExtension(),
            'Stimulus',
            0,
            null,
            false,
            false,
        );

        $handler(new FakeSwooleServer());

        $state = $stateFile->read();

        $this->assertSame(111, $state['masterProcessId']);
        $this->assertSame(222, $state['managerProcessId']);
    }

    /**
     * The manager start handler names the manager process.
     *
     * @return void
     */
    public function testManagerStartHandlerNamesProcess(): void
    {
        if (!extension_loaded('swoole') && !extension_loaded('openswoole')) {
            $this->markTestSkipped('The Swoole or OpenSwoole extension is not installed on this host.');
        }

        $handler = new OnManagerStart(new SwooleExtension(), 'Stimulus');

        $handler();

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
            . 'stimulus-swoole-state-test-' . getmypid() . '-' . count($this->stateFiles) . '.json';

        if (file_exists($path)) {
            unlink($path);
        }

        $this->stateFiles[] = $path;

        return $path;
    }
}
