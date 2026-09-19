<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Support;

/**
 * Application double exposing container scope hooks.
 *
 * Supports listeners that forget scoped and temporary container instances.
 */
class ScopedContainerTestApp
{
    /**
     * Number of scope resets.
     *
     * @var int
     */
    public int $scopeResets = 0;

    /**
     * Number of scoped instance resets.
     *
     * @var int
     */
    public int $scopedForgets = 0;

    /**
     * Bindings forgotten so far.
     *
     * @var list<string>
     */
    public array $forgotten = [];

    /**
     * Records a scope reset.
     *
     * @return void
     */
    public function resetScope(): void
    {
        $this->scopeResets++;
    }

    /**
     * Records a scoped instance reset.
     *
     * @return void
     */
    public function forgetScopedInstances(): void
    {
        $this->scopedForgets++;
    }

    /**
     * Records a forgotten binding.
     *
     * @param string $binding Binding name.
     * @return void
     */
    public function forgetInstance(string $binding): void
    {
        $this->forgotten[] = $binding;
    }
}
