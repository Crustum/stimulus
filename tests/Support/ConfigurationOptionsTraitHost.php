<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Support;

use Crustum\Stimulus\Trait\ProvidesDefaultConfigurationOptionsTrait;

/**
 * Host exposing the configuration options trait for testing.
 */
class ConfigurationOptionsTraitHost
{
    /**
     * Configuration behavior under test.
     */
    use ProvidesDefaultConfigurationOptionsTrait;
}
