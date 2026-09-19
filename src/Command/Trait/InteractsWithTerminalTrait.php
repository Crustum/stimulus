<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Command\Trait;

use Symfony\Component\Console\Terminal;

/**
 * Computes the terminal width for console output formatting.
 *
 * The Symfony terminal reader ships with the installed Symfony console
 * component, so no host wiring is needed to measure the output width.
 *
 * @mixin \Crustum\Stimulus\Command\StimulusCommand
 */
trait InteractsWithTerminalTrait
{
    /**
     * The current terminal width.
     *
     * @var int|null
     */
    protected ?int $terminalWidth = null;

    /**
     * Computes the terminal width.
     *
     * Falls back to 30 columns when the detected width is narrower so request
     * lines keep a minimum readable layout.
     *
     * @return int Terminal width in columns, at least 30.
     */
    protected function getTerminalWidth(): int
    {
        if ($this->terminalWidth === null) {
            $width = (new Terminal())->getWidth();

            $this->terminalWidth = max($width, 30);
        }

        return $this->terminalWidth;
    }
}
