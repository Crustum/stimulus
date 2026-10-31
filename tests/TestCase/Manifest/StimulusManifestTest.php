<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\TestCase\Manifest;

use Cake\TestSuite\TestCase;
use Crustum\PluginManifest\Manifest\ManifestResolver;
use Crustum\Stimulus\Manifest\StimulusManifest;
use Crustum\Stimulus\StimulusPlugin;

/**
 * Covers the standalone manifest provider: identical operations to the
 * former plugin-class manifest, discoverable without the interface.
 */
class StimulusManifestTest extends TestCase
{
    /**
     * The provider emits config, bootstrap and star operations.
     *
     * @return void
     */
    public function testManifestOperations(): void
    {
        $ops = StimulusManifest::manifest();
        $tags = array_column($ops, 'tag');

        $this->assertContains('config', $tags);
        $this->assertContains('bootstrap', $tags);
        $this->assertContains('star-repo', $tags);
    }

    /**
     * The resolver finds the provider for the plugin class.
     *
     * @return void
     */
    public function testResolverFindsProvider(): void
    {
        $this->assertSame(
            StimulusManifest::class,
            ManifestResolver::providerClass(StimulusPlugin::class),
        );
        $this->assertSame(StimulusManifest::manifest(), ManifestResolver::manifestFor(StimulusPlugin::class));
    }

    /**
     * The plugin class itself carries no manifest contract.
     *
     * @return void
     */
    public function testPluginClassHasNoManifestContract(): void
    {
        $this->assertFalse(method_exists(StimulusPlugin::class, 'manifest'));
    }
}
