<?php

namespace Kanboard\Plugin\ModMenuUpdater\Model;

use Kanboard\Core\Base;
use Kanboard\Plugin\ModMenu\Exception\ModMenuException;
use Kanboard\Plugin\ModMenu\Model\PluginArchive;
use Kanboard\Plugin\ModMenu\Model\PluginSwap;

/**
 * The one-time replace of an old ModMenu (< 1.3.0, which refuses to install
 * over itself) with a self-updating release.
 *
 * Compatibility rule: this runs while the OLD ModMenu is active, so it uses
 * only ModMenu API that exists unchanged in 1.1.0 (PluginArchive::inspect /
 * extractTo, DirectoryClient::catalogMap, ModMenuException). PluginSwap is not
 * in 1.1.0: it is require_once'd from the staged new release.
 */
class Bootstrapper extends Base
{
    private const NAME = 'ModMenu';

    /**
     * @return array{installed: ?string, self_updating: bool}
     */
    public function state(string $pluginsDir): array
    {
        $dir = rtrim($pluginsDir, '/') . '/' . self::NAME;
        return [
            'installed' => $this->readVersion($dir),
            'self_updating' => is_file($dir . '/Model/PluginSwap.php'),
        ];
    }

    /**
     * Replace the installed ModMenu with the release in $zipPath. Returns the
     * new version. The old copy is kept as .modmenu-previous-ModMenu for the
     * new ModMenu's load guard to finalize or roll back.
     *
     * @throws ModMenuException
     */
    public function installZip(string $zipPath, string $pluginsDir): string
    {
        $pluginsDir = rtrim($pluginsDir, '/');
        $state = $this->state($pluginsDir);

        if ($state['self_updating']) {
            throw new ModMenuException(t('ModMenu already updates itself; ModMenuUpdater is no longer needed. Remove it from ModMenu → Installed.'));
        }

        $archive = new PluginArchive($this->container);
        $name = $archive->inspect($zipPath);
        if ($name !== self::NAME) {
            throw new ModMenuException(t('The downloaded archive contains "%s", not ModMenu.', $name));
        }

        $staging = $pluginsDir . '/.modmenu-staging-' . bin2hex(random_bytes(8));
        if (! @mkdir($staging, 0755)) {
            throw new ModMenuException(t('Unable to create a staging directory in the plugins folder.'));
        }

        try {
            $archive->extractTo($zipPath, $staging);
            $staged = $staging . '/' . self::NAME;

            $swapFile = $staged . '/Model/PluginSwap.php';
            if (! is_file($swapFile)) {
                throw new ModMenuException(t('This ModMenu release does not support self-update (it has no Model/PluginSwap.php).'));
            }
            if (! class_exists(PluginSwap::class)) {
                require_once $swapFile;
            }

            $swap = new PluginSwap($pluginsDir);
            $version = $swap->verify($staged, self::NAME, $state['installed'] ?? '0.0.0');
            $swap->swap($staged, self::NAME, true);

            return $version;
        } finally {
            if (class_exists(PluginSwap::class, false)) {
                PluginSwap::removeTree($staging);
            } else {
                $this->removeTree($staging);
            }
        }
    }

    /**
     * The https:// download URL of ModMenu in a DirectoryClient::catalogMap().
     *
     * @throws ModMenuException
     */
    public function downloadUrl(array $catalog): string
    {
        $url = $catalog[self::NAME]['download'] ?? null;
        if (! is_string($url) || $url === '') {
            throw new ModMenuException(t('No ModMenu download was found in your directory sources.'));
        }
        if (! preg_match('#^https://#i', $url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new ModMenuException(t('The ModMenu download URL must be a valid https:// URL.'));
        }
        return $url;
    }

    private function readVersion(string $pluginDir): ?string
    {
        $file = $pluginDir . '/plugin.json';
        if (! is_file($file)) {
            return null;
        }
        $meta = json_decode((string) file_get_contents($file), true);
        return is_array($meta) && isset($meta['version']) && is_scalar($meta['version'])
            ? (string) $meta['version']
            : null;
    }

    private function removeTree(string $dir): void
    {
        if (is_link($dir) || is_file($dir)) {
            @unlink($dir);
            return;
        }
        if (! is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $f) {
            if ($f !== '.' && $f !== '..') {
                $this->removeTree($dir . '/' . $f);
            }
        }
        @rmdir($dir);
    }
}
