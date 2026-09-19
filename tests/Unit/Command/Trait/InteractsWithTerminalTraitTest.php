<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Command\Trait;

use Crustum\Stimulus\Command\Trait\InteractsWithTerminalTrait;
use Crustum\Stimulus\Test\TestCase;

/**
 * Tests for the terminal width trait.
 *
 * Covers the trait against the fixed terminal width.
 */
class InteractsWithTerminalTraitTest extends TestCase
{
    use InteractsWithTerminalTrait;

    /**
     * The terminal width keeps the minimum width.
     *
     * @return void
     */
    public function testWidth(): void
    {
        $this->assertGreaterThanOrEqual(30, $this->getTerminalWidth());
    }
}
