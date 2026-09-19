<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Exception;

use Crustum\Stimulus\Exception\DdException;
use Crustum\Stimulus\Test\TestCase;

/**
 * Tests for the dump-and-die task exception.
 */
class DdExceptionTest extends TestCase
{
    /**
     * Message defaults to the JSON encoded variables.
     *
     * @return void
     */
    public function testMessageDefaultsToJsonEncodedVars(): void
    {
        $exception = new DdException(['foo' => 'bar']);

        $this->assertSame(['foo' => 'bar'], $exception->vars);
        $this->assertSame(json_encode(['foo' => 'bar']), $exception->getMessage());
    }

    /**
     * Explicit message, code, and previous are honored.
     *
     * @return void
     */
    public function testExplicitMessageCodeAndPrevious(): void
    {
        $previous = new DdException(['inner' => true]);
        $exception = new DdException(['foo' => 'bar'], 'custom', 3, $previous);

        $this->assertSame('custom', $exception->getMessage());
        $this->assertSame(3, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }

    /**
     * Render dumps every variable as HTML.
     *
     * @return void
     */
    public function testRenderDumpsVarsAsHtml(): void
    {
        $exception = new DdException(['foo' => 'bar', 'nested' => ['a' => 1]]);

        $rendered = $exception->render();

        $this->assertStringContainsString('<pre>', $rendered);
        $this->assertStringContainsString('bar', $rendered);
        $this->assertStringContainsString('1', $rendered);
    }

    /**
     * Render escapes HTML special chars in dumped values.
     *
     * @return void
     */
    public function testRenderEscapesHtml(): void
    {
        $exception = new DdException(['<b>bold</b>']);

        $this->assertStringNotContainsString('<b>bold</b>', $exception->render());
        $this->assertStringContainsString('&lt;b&gt;bold&lt;/b&gt;', $exception->render());
    }

    /**
     * Render truncates long strings.
     *
     * @return void
     */
    public function testRenderTruncatesLongStrings(): void
    {
        $exception = new DdException([str_repeat('a', 200)]);

        $this->assertStringContainsString(str_repeat('a', 160) . '...', $exception->render());
    }
}
