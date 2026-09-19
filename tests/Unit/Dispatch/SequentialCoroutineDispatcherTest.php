<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Dispatch;

use Crustum\Stimulus\Dispatch\SequentialCoroutineDispatcher;
use Crustum\Stimulus\Test\TestCase;

/**
 * Tests for the sequential coroutine dispatcher.
 *
 * Covers eager coroutine resolution keyed by the given keys.
 */
class SequentialCoroutineDispatcherTest extends TestCase
{
    /**
     * Coroutines resolve eagerly with results keyed by their given keys.
     *
     * @return void
     */
    public function testCoroutinesCanBeResolved(): void
    {
        $dispatcher = new SequentialCoroutineDispatcher();

        $this->assertEquals([
            'first' => 1,
            'second' => 2,
            'third' => null,
        ], $dispatcher->resolve([
            'first' => fn(): int => 1,
            'second' => fn(): int => 2,
            'third' => function (): void {
            },
        ]));
    }

    /**
     * Coroutine results keep integer keys in dispatch order.
     *
     * @return void
     */
    public function testCoroutineKeysArePreserved(): void
    {
        $dispatcher = new SequentialCoroutineDispatcher();

        $this->assertSame(
            [0 => 'first', 2 => 'second'],
            $dispatcher->resolve([
                0 => fn(): string => 'first',
                2 => fn(): string => 'second',
            ]),
        );
    }
}
