<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Command;

use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\TestSuite\StubConsoleOutput;
use Cake\Core\Configure;
use Crustum\Stimulus\Command\StartRoadRunnerCommand;
use Crustum\Stimulus\Test\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests for the RoadRunner binary version requirements.
 *
 * Calendar versions compare against the calendar floor while semantic `3.x`
 * versions compare against the semantic floor. The banner comes from a stub
 * so no binary executes.
 */
class RoadRunnerBinaryRequirementsTest extends TestCase
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

        $this->temporaryPaths = [];

        if ($this->stimulusConfig === null) {
            Configure::delete('Stimulus');
        } else {
            Configure::write('Stimulus', $this->stimulusConfig);
        }

        parent::tearDown();
    }

    /**
     * Supported binaries are left untouched without downloading.
     *
     * @param string $version Binary version from the version banner.
     * @return void
     */
    #[DataProvider('supportedVersions')]
    public function testSupportedBinaryIsLeftUntouched(string $version): void
    {
        $binary = $this->fakeBinary();

        [$command, $output] = $this->command($version);

        $command->ensureRoadRunnerBinaryMeetsRequirementsPublic($binary);

        $this->assertFalse($command->downloaded);
        $this->assertStringNotContainsString('may be incompatible', $output->output());
    }

    /**
     * Unsupported binaries are replaced through the download.
     *
     * @param string $version Binary version from the version banner.
     * @return void
     */
    #[DataProvider('unsupportedVersions')]
    public function testUnsupportedBinaryIsReplaced(string $version): void
    {
        $binary = $this->fakeBinary();

        [$command, $output] = $this->command($version);

        $command->ensureRoadRunnerBinaryMeetsRequirementsPublic($binary);

        $this->assertTrue($command->downloaded);
        $this->assertFileDoesNotExist($binary . '.backup');
        $this->assertStringContainsString(
            sprintf('Your RoadRunner binary version (<error>%s</error>) may be incompatible with Stimulus.', $version),
            $output->output(),
        );
    }

    /**
     * Provides binary versions the check accepts.
     *
     * @return array<string, array{0: string}> Supported versions keyed by version.
     */
    public static function supportedVersions(): array
    {
        return [
            '2023.3.0' => ['2023.3.0'],
            '2024.3.5' => ['2024.3.5'],
            '2025.1.15' => ['2025.1.15'],
            '3.0.0' => ['3.0.0'],
            '3.0.1' => ['3.0.1'],
            '3.1.0' => ['3.1.0'],
        ];
    }

    /**
     * Provides binary versions the check rejects.
     *
     * @return array<string, array{0: string}> Unsupported versions keyed by version.
     */
    public static function unsupportedVersions(): array
    {
        return [
            '1.9.2' => ['1.9.2'],
            '2.12.3' => ['2.12.3'],
            '2023.1.0' => ['2023.1.0'],
            '2023.2.2' => ['2023.2.2'],
        ];
    }

    /**
     * Builds a starter exposing the binary requirements check.
     *
     * The banner reports the given version and the download only records
     * that it ran, so the tests prove which path the check took.
     *
     * @param string $version Binary version from the version banner.
     * @return array{0: object, 1: \Cake\Console\TestSuite\StubConsoleOutput} Command and stub output pair.
     */
    protected function command(string $version): array
    {
        $command = new class ($version) extends StartRoadRunnerCommand {
            /**
             * Whether the binary download ran.
             *
             * @var bool
             */
            public bool $downloaded = false;

            /**
             * Creates the starter with a fixed reported binary version.
             *
             * @param string $version Binary version from the version banner.
             */
            public function __construct(protected string $reportedVersion = '2023.3.0')
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
             * @param string $binary Absolute path to the RoadRunner binary.
             * @return void
             */
            public function ensureRoadRunnerBinaryMeetsRequirementsPublic(string $binary): void
            {
                $this->ensureRoadRunnerBinaryMeetsRequirements($binary);
            }

            /**
             * Reports the fixed version instead of executing a binary.
             *
             * @param string $roadRunnerBinary Absolute path to the RoadRunner binary.
             * @return string Binary version from the fixed banner.
             */
            protected function roadRunnerBinaryVersion(string $roadRunnerBinary): string
            {
                return $this->reportedVersion;
            }

            /**
             * Records the download instead of hitting the network.
             *
             * @return void
             */
            protected function downloadRoadRunnerBinary(): void
            {
                $this->downloaded = true;
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
     * Builds a tracked fake binary path.
     *
     * The file only needs to exist for the requirements check, its contents
     * never execute because the banner comes from a stub.
     *
     * @return string Absolute path to the fake binary.
     */
    protected function fakeBinary(): string
    {
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'stimulus-roadrunner-bin-' . getmypid();

        file_put_contents($path, 'fake');

        $this->temporaryPaths[] = $path;

        return $path;
    }
}
