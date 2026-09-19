<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Bridge\RoadRunner;

use Cake\Core\Configure;
use Crustum\Stimulus\Bridge\RoadRunner\FindsRoadRunnerBinaryTrait;
use Crustum\Stimulus\Test\TestCase;

/**
 * Tests for the RoadRunner binary finder.
 */
class FindsRoadRunnerBinaryTraitTest extends TestCase
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
     * Finder returns the binary living next to the application.
     *
     * @return void
     */
    public function testFinderReturnsBinaryNextToApplication(): void
    {
        $basePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'stimulus-roadrunner-finder-test';

        if (! is_dir($basePath)) {
            mkdir($basePath, 0777, true);
        }

        file_put_contents($basePath . DIRECTORY_SEPARATOR . 'rr', '');

        Configure::write('Stimulus.basePath', $basePath);

        $this->assertSame($basePath . DIRECTORY_SEPARATOR . 'rr', $this->finder()->find());
    }

    /**
     * Creates a probe exposing the trait methods under test.
     *
     * @return object Probe with binary finder accessors.
     */
    protected function finder(): object
    {
        return new class {
            use FindsRoadRunnerBinaryTrait;

            /**
             * Exposes the binary finder for testing.
             *
             * @return string|null Absolute path to the RoadRunner binary, or null when missing.
             */
            public function find(): ?string
            {
                return $this->findRoadRunnerBinary();
            }

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
