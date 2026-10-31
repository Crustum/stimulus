<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Manifest;

use Crustum\PluginManifest\Manifest\ManifestOps;
use Crustum\Stimulus\StimulusPlugin;

/**
 * Standalone manifest provider for the Stimulus plugin.
 *
 * Plain class without interface or trait dependencies, so the plugin loads
 * and runs without `crustum/plugin-manifest` installed. Discovered by the
 * installer through the provider convention when the plugin class itself
 * provides no manifest. All package references stay inside method bodies,
 * which only execute when the installer (and therefore the package) exists.
 */
final class StimulusManifest
{
    /**
     * Plugin install assets via crustum/plugin-manifest.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function manifest(): array
    {
        $pluginPath = dirname(__DIR__, 2);
        $plugin = ManifestOps::pluginName(StimulusPlugin::class);

        return array_merge(
            ManifestOps::manifestConfig(
                $plugin,
                $pluginPath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'stimulus.php',
                CONFIG . 'stimulus.php',
                false,
            ),
            ManifestOps::manifestBootstrapAppend(
                $plugin,
                "if (file_exists(CONFIG . 'stimulus.php')) {\n    Configure::load('stimulus', 'default');\n}",
                '// Stimulus Plugin Configuration',
            ),
            ManifestOps::manifestStarRepo($plugin, 'Crustum/Stimulus'),
        );
    }
}
