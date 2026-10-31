<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Command;

use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\TestSuite\StubConsoleOutput;
use Cake\Core\Configure;
use Crustum\Stimulus\Command\StartFrankenPhpCommand;
use Crustum\Stimulus\Test\TestCase;

/**
 * Tests for the FrankenPHP binary version requirements.
 *
 * Outdated binaries outside the project root stay untouched with a manual
 * update note, while the outdated project root binary is replaced through
 * the download. The build info comes from a stub so no binary executes.
 */
class FrankenPhpBinaryRequirementsTest extends TestCase
{
    /**
     * Previous Stimulus configuration restored after each test.
     *
     * @var mixed
     */
    protected mixed $stimulusConfig = null;

    /**
     * Temporary paths removed after each test.
     *
     * @var array<int, string>
     */
    protected array $temporaryPaths = [];

    /**
     * Stores the Stimulus configuration for teardown.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->stimulusConfig = Configure::read('Stimulus');
    }

    /**
     * Removes temporary paths and restores configuration.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        foreach ($this->temporaryPaths as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        foreach ($this->temporaryPaths as $path) {
            if (is_dir($path)) {
                rmdir($path);
            }
        }

        $this->temporaryPaths = [];

        if ($this->stimulusConfig === null) {
            Configure::delete('Stimulus');
        } else {
            Configure::write('Stimulus', $this->stimulusConfig);
        }

        parent::tearDown();
    }

    /**
     * Outdated binaries outside the project root stay untouched.
     *
     * @return void
     */
    public function testOutdatedBinaryOutsideProjectRootIsLeftUntouched(): void
    {
        $basePath = $this->temporaryDirectory('stimulus-frankenphp-base');

        Configure::write('Stimulus.basePath', $basePath);

        $elsewhere = $this->temporaryDirectory('stimulus-frankenphp-elsewhere');

        $binary = $elsewhere . DIRECTORY_SEPARATOR . 'frankenphp';

        file_put_contents($binary, 'original');

        $this->temporaryPaths[] = $binary;

        [$command, $output] = $this->command('1.0.0');

        $command->ensureFrankenPhpBinaryMeetsRequirementsPublic($binary);

        $this->assertFileExists($binary);
        $this->assertSame('original', (string)file_get_contents($binary));
        $this->assertFileDoesNotExist($binary . '.backup');
        $this->assertFileDoesNotExist($basePath . DIRECTORY_SEPARATOR . 'frankenphp');
        $this->assertFalse($command->downloaded);
        $this->assertStringContainsString(
            sprintf('Please update the FrankenPHP binary located at [%s] manually.', $binary),
            $output->output(),
        );
    }

    /**
     * Outdated project root binaries are replaced through the download.
     *
     * The `1.13.1` version sits below the required `1.13.2` floor, so the
     * replacement proves the bumped requirement.
     *
     * @return void
     */
    public function testOutdatedBinaryInProjectRootIsReplaced(): void
    {
        $basePath = $this->temporaryDirectory('stimulus-frankenphp-root');

        Configure::write('Stimulus.basePath', $basePath);

        $binary = $basePath . DIRECTORY_SEPARATOR . 'frankenphp';

        file_put_contents($binary, 'original');

        $this->temporaryPaths[] = $binary;

        [$command, $output] = $this->command('1.13.1');

        $command->ensureFrankenPhpBinaryMeetsRequirementsPublic($binary);

        $this->assertTrue($command->downloaded);
        $this->assertFileExists($binary);
        $this->assertSame('replaced', (string)file_get_contents($binary));
        $this->assertFileDoesNotExist($binary . '.backup');
        $this->assertStringContainsString('may be incompatible', $output->output());
    }

    /**
     * Supported binaries are left untouched without downloading.
     *
     * @return void
     */
    public function testSupportedBinaryIsLeftUntouched(): void
    {
        $basePath = $this->temporaryDirectory('stimulus-frankenphp-current');

        Configure::write('Stimulus.basePath', $basePath);

        $binary = $basePath . DIRECTORY_SEPARATOR . 'frankenphp';

        file_put_contents($binary, 'original');

        $this->temporaryPaths[] = $binary;

        [$command, $output] = $this->command('1.13.2');

        $command->ensureFrankenPhpBinaryMeetsRequirementsPublic($binary);

        $this->assertFalse($command->downloaded);
        $this->assertSame('original', (string)file_get_contents($binary));
        $this->assertStringNotContainsString('may be incompatible', $output->output());
    }

    /**
     * Builds a starter exposing the binary requirements check.
     *
     * The build info reports the given version and the download writes a
     * marker instead of hitting the network, so the tests prove which path
     * the check took.
     *
     * @param string $version FrankenPHP version reported through build info.
     * @return array{0: object, 1: \Cake\Console\TestSuite\StubConsoleOutput} Command and stub output pair.
     */
    protected function command(string $version): array
    {
        $command = new class ($version) extends StartFrankenPhpCommand {
            /**
             * Whether the binary download ran.
             *
             * @var bool
             */
            public bool $downloaded = false;

            /**
             * Creates the starter with a fixed reported binary version.
             *
             * @param string $version FrankenPHP version reported through build info.
             */
            public function __construct(protected string $reportedVersion = '1.0.0')
            {
                parent::__construct();
            }

            /**
             * Executes the command without spawning the server.
             *
             * @param \Cake\Console\Arguments $args The command arguments.
             * @param \Cake\Console\ConsoleIo $io The console io.
             * @return int The exit code.
             */
            public function execute(Arguments $args, ConsoleIo $io): int
            {
                return self::CODE_SUCCESS;
            }

            /**
             * Exposes the binary requirements check.
             *
             * @param string $binary Absolute path to the FrankenPHP binary.
             * @return void
             */
            public function ensureFrankenPhpBinaryMeetsRequirementsPublic(string $binary): void
            {
                $this->ensureFrankenPhpBinaryMeetsRequirements($binary);
            }

            /**
             * Reports the fixed version instead of executing a binary.
             *
             * @param string $frankenPhpBinary Absolute path to the FrankenPHP binary.
             * @return string Build info output carrying the fixed version.
             */
            protected function frankenPhpBinaryBuildInfo(string $frankenPhpBinary): string
            {
                return sprintf('dep	github.com/dunglas/frankenphp	v%s%s', $this->reportedVersion, PHP_EOL);
            }

            /**
             * Records the download and writes a marker instead.
             *
             * @return string Absolute path to the downloaded binary.
             */
            protected function downloadFrankenPhpBinary(): string
            {
                $this->downloaded = true;

                $path = $this->applicationBasePath() . DIRECTORY_SEPARATOR . 'frankenphp';

                file_put_contents($path, 'replaced');

                chmod($path, 0755);

                return $path;
            }

            /**
             * Confirms every action so the tests run non-interactively.
             *
             * @param string $prompt Question asked to the user.
             * @param bool $default Default answer used for non-interactive runs.
             * @return bool Whether the user confirmed the action.
             */
            protected function confirmAction(string $prompt, bool $default = true): bool
            {
                return true;
            }
        };

        $output = new StubConsoleOutput();

        $command->run([], new ConsoleIo($output, $output));

        return [$command, $output];
    }

    /**
     * Builds a tracked temporary directory.
     *
     * @param string $name Directory name below the system temp directory.
     * @return string Absolute path to the temporary directory.
     */
    protected function temporaryDirectory(string $name): string
    {
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $name . '-' . getmypid();

        if (! is_dir($path)) {
            mkdir($path, 0777, true);
        }

        $this->temporaryPaths[] = $path;

        return $path;
    }
}
