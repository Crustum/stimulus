<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Support;

use Crustum\Stimulus\Trait\RegistersTickHandlersTrait;

/**
 * Host exposing the tick handler registration trait for tests.
 */
class TickHandlersTraitHost
{
    use RegistersTickHandlersTrait;
}
