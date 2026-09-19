<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Support;

use Crustum\Stimulus\Trait\ProvidesConcurrencySupportTrait;

/**
 * Host exposing the concurrency support trait for tests.
 */
class ConcurrencySupportTraitHost
{
    use ProvidesConcurrencySupportTrait;
}
