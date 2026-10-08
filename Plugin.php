<?php

namespace Kanboard\Plugin\ModMenuUpdater;

use Kanboard\Core\Plugin\Base;

/**
 * ModMenuUpdater — one-shot helper that replaces a ModMenu older than 1.3.0
 * (which refuses to install over itself) with the self-updating release, then
 * asks the admin to remove it.
 *
 * @author  Carmelo Santana
 * @license MIT
 */
class Plugin extends Base
{
    public function initialize()
    {
        $this->hook->on('template:config:sidebar', ['template' => 'ModMenuUpdater:config/sidebar']);

        $this->route->addRoute('config/modmenu-updater', 'ModMenuUpdater:UpdaterController', 'show');
        $this->route->addRoute('config/modmenu-updater/run', 'ModMenuUpdater:UpdaterController', 'run');
    }

    public function getPluginName(): string
    {
        return 'ModMenuUpdater';
    }

    public function getPluginDescription(): string
    {
        return 'One-shot helper: upgrades ModMenu older than 1.3.0 to the self-updating version, then can be removed.';
    }

    public function getPluginAuthor(): string
    {
        return 'Carmelo Santana';
    }

    public function getPluginVersion(): string
    {
        return '1.0.0';
    }

    public function getCompatibleVersion(): string
    {
        return '>=1.2.47';
    }

    public function getPluginHomepage(): string
    {
        return 'https://github.com/carmelosantana/kanboard-modmenu-updater';
    }

    public function getPluginLicense(): string
    {
        return 'MIT';
    }
}
