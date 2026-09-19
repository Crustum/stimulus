<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Process;

use Crustum\Stimulus\Process\SymfonyProcessFactory;
use Crustum\Stimulus\Test\TestCase;
use ReflectionMethod;
use Symfony\Component\Process\Process;

/**
 * Tests for the Symfony process factory.
 */
class SymfonyProcessFactoryTest extends TestCase
{
    /**
     * Factory exposes the construction signature.
     *
     * @return void
     */
    public function testFactoryExposesSignature(): void
    {
        $method = new ReflectionMethod(SymfonyProcessFactory::class, 'createProcess');

        $this->assertSame(
            ['command', 'cwd', 'env', 'input', 'timeout'],
            array_map(
                static fn($parameter): string => $parameter->getName(),
                $method->getParameters(),
            ),
        );
        $this->assertSame(60.0, $method->getParameters()[4]->getDefaultValue());
    }

    /**
     * Create process builds a Symfony process for the given command.
     *
     * The factory passes the command through, while the process resolves the
     * binary to an absolute path, so the assertion checks the carried binary
     * name and flag instead of the literal command line.
     *
     * @return void
     */
    public function testCreateProcessBuildsSymfonyProcess(): void
    {
        if (!class_exists(Process::class)) {
            $this->markTestSkipped('symfony/process is not installed.');
        }

        $factory = new SymfonyProcessFactory();

        $process = $factory->createProcess(['php', '--version']);

        $this->assertInstanceOf(Process::class, $process);
        $this->assertMatchesRegularExpression('/php/i', $process->getCommandLine());
        $this->assertStringContainsString('--version', $process->getCommandLine());
    }
}
