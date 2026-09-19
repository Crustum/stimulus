<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Bridge\RoadRunner;

use Crustum\Stimulus\Bridge\RoadRunner\ServerStateFile;
use Crustum\Stimulus\Test\TestCase;

/**
 * Tests for the RoadRunner server state file.
 */
class ServerStateFileTest extends TestCase
{
    /**
     * Server state file round-trips process IDs and state arrays.
     *
     * @return void
     */
    public function testServerStateFileCanBeManaged(): void
    {
        $stateFile = new ServerStateFile(sys_get_temp_dir() . '/stimulus-roadrunner-state-test.json');

        $stateFile->delete();

        $state = $stateFile->read();
        $this->assertEquals(['masterProcessId' => null, 'state' => []], $state);

        $stateFile->writeProcessId(1);
        $stateFile->writeState(['name' => 'Taylor']);

        $state = $stateFile->read();
        $this->assertEquals(['masterProcessId' => 1, 'state' => ['name' => 'Taylor']], $state);

        $stateFile->delete();
        $state = $stateFile->read();
        $this->assertEquals(['masterProcessId' => null, 'state' => []], $state);
    }
}
