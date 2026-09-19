<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Support;

use Crustum\Stimulus\Trait\ProvidesRoutingTrait;

/**
 * Host exposing the routing trait for testing.
 */
class RoutingTraitHost
{
    /**
     * Routing behavior under test.
     */
    use ProvidesRoutingTrait;
}
