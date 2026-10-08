<?php

require_once 'tests/units/Base.php';

use Kanboard\Plugin\ModMenuUpdater\Plugin;
use KanboardTests\units\Base;

class PluginTest extends Base
{
    public function testPluginNameValue()
    {
        $this->assertSame('ModMenuUpdater', (new Plugin($this->container))->getPluginName());
    }

    public function testPluginVersionValue()
    {
        $this->assertSame('1.0.0', (new Plugin($this->container))->getPluginVersion());
    }

    public function testCompatibleVersion()
    {
        $this->assertSame('>=1.2.47', (new Plugin($this->container))->getCompatibleVersion());
    }

    public function testGettersMatchPluginJson()
    {
        $meta = json_decode(file_get_contents(dirname(__DIR__) . '/plugin.json'), true);
        $plugin = new Plugin($this->container);
        $this->assertSame($meta['name'], $plugin->getPluginName());
        $this->assertSame($meta['version'], $plugin->getPluginVersion());
        $this->assertSame($meta['description'], $plugin->getPluginDescription());
        $this->assertSame($meta['author'], $plugin->getPluginAuthor());
        $this->assertSame($meta['homepage'], $plugin->getPluginHomepage());
        $this->assertSame($meta['license'], $plugin->getPluginLicense());
        $this->assertSame($meta['compatible_version'], $plugin->getCompatibleVersion());
    }

    public function testInitializeRegistersRoutes()
    {
        $route = $this->container['route'];
        $route->enable();
        (new Plugin($this->container))->initialize();

        $show = $route->findRoute('config/modmenu-updater');
        $this->assertSame(['ModMenuUpdater:UpdaterController', 'show'], [$show['controller'], $show['action']]);

        $run = $route->findRoute('config/modmenu-updater/run');
        $this->assertSame(['ModMenuUpdater:UpdaterController', 'run'], [$run['controller'], $run['action']]);
    }

    public function testInitializeAddsConfigSidebarLink()
    {
        (new Plugin($this->container))->initialize();
        $this->assertContains(
            ['template' => 'ModMenuUpdater:config/sidebar'],
            $this->container['hook']->getListeners('template:config:sidebar')
        );
    }
}
