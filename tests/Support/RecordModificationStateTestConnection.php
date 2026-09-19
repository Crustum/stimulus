<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Support;

use Cake\Database\Connection;
use Cake\Database\Driver\Sqlite;

/**
 * Connection double exposing record modification state.
 *
 * Supports listeners that forget per-connection modification tracking.
 */
class RecordModificationStateTestConnection extends Connection
{
    /**
     * Number of times the modification state was forgotten.
     *
     * @var int
     */
    public int $forgets = 0;

    /**
     * Creates a new in-memory connection double.
     */
    public function __construct()
    {
        parent::__construct(['driver' => Sqlite::class, 'database' => ':memory:']);
    }

    /**
     * Records a modification state reset.
     *
     * @return void
     */
    public function forgetRecordModificationState(): void
    {
        $this->forgets++;
    }
}
