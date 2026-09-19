<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Cache;

use Crustum\Stimulus\Cache\Table\OpenSwooleTable;
use Crustum\Stimulus\Cache\Table\SwooleTable;
use Crustum\Stimulus\Cache\Table\TableFactory;
use Crustum\Stimulus\Exception\ValueTooLargeForColumnException;
use Crustum\Stimulus\Test\TestCase;
use OpenSwoole\Table as OpenSwooleNativeTable;
use PHPUnit\Framework\Attributes\DataProvider;
use Swoole\Table;

/**
 * Tests for the Swoole table backend.
 *
 * Covers factory implementation picks, row capacity, string storage, size
 * enforcement, undeclared columns, and non-string columns. The worker
 * entries creating cache, timer, and configured tables ship in a later
 * phase, so their coverage travels with that phase. Every test skips
 * cleanly where the extension is absent.
 */
class TableTest extends TestCase
{
    /**
     * Requires the Swoole extension before each test.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        if (!extension_loaded('openswoole') && !extension_loaded('swoole')) {
            $this->markTestSkipped('The Swoole or OpenSwoole extension is not installed on this host.');
        }
    }

    /**
     * The factory picks the implementation matching the loaded extension.
     *
     * @return void
     */
    public function testFactoryPicksImplementationByLoadedExtension(): void
    {
        $table = TableFactory::make(100);

        if (extension_loaded('openswoole')) {
            $this->assertInstanceOf(OpenSwooleTable::class, $table);

            return;
        }

        $this->assertInstanceOf(SwooleTable::class, $table);
    }

    /**
     * Tables hold every available row.
     *
     * @return void
     */
    public function testCanUseAllAvailableRows(): void
    {
        $table = $this->createSwooleTable();

        for ($i = 0; $i < 1000; $i++) {
            $table->set((string)$i, ['string' => 'foo']);
        }

        $this->assertSame(1000, $table->count());
    }

    /**
     * Fitting string values store and retrieve.
     *
     * @param string $value Fitting string value.
     * @return void
     */
    #[DataProvider('validStringValues')]
    public function testSetOfStringColumns(string $value): void
    {
        $table = $this->createSwooleTable();

        $table->set('key', ['string' => $value]);

        $this->assertSame($value, $table->get('key')['string']);
    }

    /**
     * Oversized string values raise.
     *
     * @param string $value Oversized string value.
     * @return void
     */
    #[DataProvider('tooLargeStringValues')]
    public function testEnsuresStringSizes(string $value): void
    {
        $table = $this->createSwooleTable();

        $this->expectException(ValueTooLargeForColumnException::class);
        $this->expectExceptionMessage(sprintf(
            'Value [%s...] is too large for [string] column.',
            substr($value, 0, 20),
        ));

        $table->set('key', ['string' => $value]);
    }

    /**
     * Undeclared columns skip size enforcement.
     *
     * @return void
     */
    public function testEnsuresStringSizesOnlyOnDeclaredColumns(): void
    {
        $table = $this->createSwooleTable();

        $table->set('key', ['non-existing-column' => 'foo']);

        $this->assertArrayNotHasKey(
            'non-existing-column',
            $table->get('key'),
        );
    }

    /**
     * Non-string columns skip size enforcement.
     *
     * @return void
     */
    public function testEnsuresStringSizesOnlyOnStringTypes(): void
    {
        $table = $this->createSwooleTable();

        $value = 1000000000000000000;

        $table->set('key', ['int' => $value]);

        $this->assertSame($value, $table->get('key')['int']);
    }

    /**
     * Provides fitting string values.
     *
     * @return list<array{0: string}> Fitting string values.
     */
    public static function validStringValues(): array
    {
        return [
            [str_repeat('a', 10)],
            ['taylortayl'],
            ['手 田日'],
            ['! p8VrB[]{'],
            ['N Rs*Gz2@h'],
            ['O:29:"Illu'],
            ['          '],
        ];
    }

    /**
     * Provides oversized string values.
     *
     * @return list<array{0: string}> Oversized string values.
     */
    public static function tooLargeStringValues(): array
    {
        return [
            [str_repeat('a', 11)],
            ['taylortaylo'],
            ['手 田日尸'],
            ['! p8VrB[]{@'],
            ['N Rs*Gz2@hS'],
            ['O:29:"Illum'],
            ['           '],
        ];
    }

    /**
     * Builds a Swoole table with sized columns.
     *
     * Column type constants come from the loaded runtime so the factory
     * table works on OpenSwoole-only hosts too.
     *
     * @return object Swoole table with string and integer columns.
     */
    protected function createSwooleTable(): object
    {
        $table = TableFactory::make(1000);

        $nativeTableClass = $this->nativeTableClass();

        $table->column('string', $nativeTableClass::TYPE_STRING, 10);
        $table->column('int', $nativeTableClass::TYPE_INT);

        $table->create();

        return $table;
    }

    /**
     * Resolves the native table class of the loaded runtime.
     *
     * Prefers Swoole and falls back to OpenSwoole, so column constants
     * always belong to the backing table.
     *
     * @return class-string Native table class name.
     */
    protected function nativeTableClass(): string
    {
        if (class_exists(Table::class)) {
            return Table::class;
        }

        return OpenSwooleNativeTable::class;
    }
}
