<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Bridge\FrankenPhp;

use Cake\Core\Configure;
use Crustum\Stimulus\Bridge\FrankenPhp\FindsFrankenPhpBinaryTrait;
use Crustum\Stimulus\Test\TestCase;

/**
 * Tests for the FrankenPHP binary finder base path resolution.
 *
 * Binary resolution itself needs the Symfony executable finder, which is an
 * optional dependency decided in the worker core phase, so only the
 * configuration-driven base path is covered here.
 */
class FindsFrankenPhpBinaryTraitTest extends TestCase
{
    /**
     * Base path configured before each test.
     *
     * @var mixed
     */
    protected mixed $basePath = null;

    /**
     * Stores the base path so finder tests can restore it.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->basePath = Configure::read('Stimulus.basePath');
    }

    /**
     * Restores the base path after each test.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        if ($this->basePath === null) {
            Configure::delete('Stimulus.basePath');
        } else {
            Configure::write('Stimulus.basePath', $this->basePath);
        }

        parent::tearDown();
    }

    /**
     * Finder prefers the application base path configuration value.
     *
     * @return void
     */
    public function testBasePathReadsConfiguredValue(): void
    {
        Configure::write('Stimulus.basePath', sys_get_temp_dir());

        $this->assertSame(sys_get_temp_dir(), $this->finder()->base());
    }

    /**
     * Finder falls back to the working directory without configuration.
     *
     * @return void
     */
    public function testBasePathFallsBackToWorkingDirectory(): void
    {
        Configure::delete('Stimulus.basePath');

        $workingDirectory = getcwd();

        $this->assertSame($workingDirectory === false ? sys_get_temp_dir() : $workingDirectory, $this->finder()->base());
    }

    /**
     * Creates a probe exposing the trait base path for testing.
     *
     * @return object Probe with base path accessor.
     */
    protected function finder(): object
    {
        return new class {
            use FindsFrankenPhpBinaryTrait;

            /**
             * Exposes the base path resolution for testing.
             *
             * @return string Absolute path to the application base directory.
             */
            public function base(): string
            {
                return $this->applicationBasePath();
            }
        };
    }
}
