<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Trait;

use Cake\Queue\QueueManager;

/**
 * Refreshes static-configuration entries for long-lived workers.
 *
 * Host bootstraps register adapter configuration once per process through
 * the `X::setConfig(Configure::consume(...))` skeleton shape, which empties
 * the `Configure` source at boot. Refreshes must therefore read the entry
 * from the live registry at operation time, never from `Configure`, and
 * restore it through the trait-documented drop plus reconfigure pair. The
 * restore is validated before the drop: the framework trait stores whatever
 * it receives without validation, so re-registering a partial entry would
 * destroy a pool the worker can no longer rebuild. Entries failing
 * validation stay untouched and report `false`.
 */
trait RefreshesStaticConfigTrait
{
    /**
     * Refreshes the given static-configuration entry.
     *
     * Snapshots the entry from the live registry, drops the resolved
     * instance, and re-registers the snapshot so adapters rebuild lazily on
     * next use. Validation runs before the drop, so a failed validation
     * leaves the entry exactly as found.
     *
     * @param class-string<\Cake\Cache\Cache|\Cake\Datasource\ConnectionManager|\Cake\Log\Log|\Cake\Mailer\TransportFactory|\Cake\Queue\QueueManager> $manager Static-configuration manager class name.
     * @param string $name Entry name whose resolved instance is refreshed.
     * @return bool Whether the entry configuration survived the refresh.
     */
    protected function refreshStaticConfig(string $manager, string $name): bool
    {
        $config = $manager::getConfig($name);

        if (!$this->isRebuildableConfig($manager, $config)) {
            return false;
        }

        $manager::drop($name);
        $manager::setConfig($name, $config);

        return true;
    }

    /**
     * Determines whether the given snapshot rebuilds after a drop.
     *
     * The queue manager carries its own contract keyed on the transport
     * locator instead of an adapter class, so it validates separately.
     * Every other manager normalizes injected instances into a class-name
     * pair the re-registration accepts as-is, while arrays must name their
     * adapter through either configuration key.
     *
     * @param class-string<\Cake\Cache\Cache|\Cake\Datasource\ConnectionManager|\Cake\Log\Log|\Cake\Mailer\TransportFactory|\Cake\Queue\QueueManager> $manager Static-configuration manager class name.
     * @param mixed $config Snapshotted entry configuration.
     * @return bool Whether dropping plus re-registering restores the entry.
     */
    protected function isRebuildableConfig(string $manager, mixed $config): bool
    {
        if ($manager === QueueManager::class) {
            return is_array($config) && !empty($config['url']);
        }

        if (is_object($config)) {
            return true;
        }

        return is_array($config) && !empty($config['className'] ?? $config['engine'] ?? null);
    }
}
