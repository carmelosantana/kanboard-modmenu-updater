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
 * in 1.1.0: it is require_once'd from the staged new release, after
 * preflight() has checked that copy without running it.
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
        // Load the exception class now: once the swap moves the current ModMenu
        // aside, autoloading it from plugins/ModMenu would fail.
        class_exists(ModMenuException::class);

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
            $this->preflight($staged, $state['installed'] ?? '0.0.0');
            $this->loadSwapClass($swapFile);

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
     * Check the staged copy without running any of it: Plugin.php declares the
     * ModMenu namespace, Model/PluginSwap.php declares the PluginSwap class,
     * plugin.json is newer than the installed copy, and every .php file parses.
     *
     * @throws ModMenuException
     */
    private function preflight(string $staged, string $installed): void
    {
        $plugin = (string) @file_get_contents($staged . '/Plugin.php');
        if (preg_match('/^\s*namespace\s+Kanboard\\\\Plugin\\\\ModMenu\s*;/m', $plugin) !== 1) {
            throw new ModMenuException(t('The new ModMenu Plugin.php does not declare namespace Kanboard\\Plugin\\ModMenu.'));
        }

        $swap = (string) @file_get_contents($staged . '/Model/PluginSwap.php');
        if (preg_match('/^\s*namespace\s+Kanboard\\\\Plugin\\\\ModMenu\\\\Model\s*;/m', $swap) !== 1
            || preg_match('/^\s*(?:final\s+)?class\s+PluginSwap\b/m', $swap) !== 1) {
            throw new ModMenuException(t('The new ModMenu Model/PluginSwap.php does not declare the PluginSwap class.'));
        }

        $version = $this->readVersion($staged);
        if ($version === null || ! version_compare($version, $installed, '>')) {
            throw new ModMenuException(t('The new ModMenu (%s) is not newer than the installed version (%s).', $version ?? '?', $installed));
        }

        if (! extension_loaded('tokenizer')) {
            return;
        }
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($staged, \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if (! $file->isFile() || strtolower($file->getExtension()) !== 'php') {
                continue;
            }
            try {
                token_get_all((string) file_get_contents($file->getPathname()), TOKEN_PARSE);
            } catch (\ParseError $e) {
                $rel = substr($file->getPathname(), strlen($staged) + 1);
                throw new ModMenuException(t('The new ModMenu has a syntax error in %s on line %d.', $rel, $e->getLine()));
            }
        }
    }

    /** Load PluginSwap from the staged copy, once preflight() has passed. */
    protected function loadSwapClass(string $file): void
    {
        if (! class_exists(PluginSwap::class)) {
            require_once $file;
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
