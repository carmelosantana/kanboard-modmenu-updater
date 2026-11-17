<?php

require_once 'tests/units/Base.php';

use KanboardTests\units\Base;
use Kanboard\Plugin\ModMenuUpdater\Model\Bootstrapper;
use Kanboard\Plugin\ModMenu\Exception\ModMenuException;
use Kanboard\Plugin\ModMenu\Model\PluginSwap;

/** Records whether the staged PluginSwap.php would have been loaded. */
class RecordingBootstrapper extends Bootstrapper
{
    public array $loaded = [];

    protected function loadSwapClass(string $file): void
    {
        $this->loaded[] = $file;
        parent::loadSwapClass($file);
    }
}

class BootstrapperTest extends Base
{
    private string $work;
    private string $plugins;

    public function setUp(): void
    {
        parent::setUp();
        $this->work = sys_get_temp_dir() . '/modmenu-updater-test-' . bin2hex(random_bytes(4));
        $this->plugins = $this->work . '/plugins';
        mkdir($this->plugins, 0777, true);
    }

    public function tearDown(): void
    {
        PluginSwap::removeTree($this->work);
        parent::tearDown();
    }

    private function bootstrapper(): Bootstrapper
    {
        return new Bootstrapper($this->container);
    }

    /** An installed ModMenu copy in the plugins dir, optionally self-updating. */
    private function seedInstalled(string $version, bool $selfUpdating = false): void
    {
        $dir = $this->plugins . '/ModMenu';
        mkdir($dir . '/Model', 0777, true);
        file_put_contents("$dir/Plugin.php", "<?php\nnamespace Kanboard\\Plugin\\ModMenu;\n");
        file_put_contents("$dir/plugin.json", json_encode(['name' => 'ModMenu', 'version' => $version]));
        if ($selfUpdating) {
            file_put_contents("$dir/Model/PluginSwap.php", "<?php\n");
        }
    }

    /** A release zip whose top-level dir is $top, carrying a copy of the real PluginSwap.php. */
    private function makeZip(string $top = 'ModMenu', string $version = '9.9.9', bool $withSwap = true): string
    {
        $path = $this->work . '/' . $top . '-' . $version . '-' . bin2hex(random_bytes(2)) . '.zip';
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE);
        $zip->addFromString("$top/Plugin.php", "<?php\nnamespace Kanboard\\Plugin\\$top;\n");
        $zip->addFromString("$top/plugin.json", json_encode(['name' => $top, 'version' => $version]));
        if ($withSwap) {
            $real = (new ReflectionClass(PluginSwap::class))->getFileName();
            $zip->addFromString("$top/Model/PluginSwap.php", file_get_contents($real));
        }
        $zip->close();
        return $path;
    }

    private function stagingDirs(): array
    {
        return glob($this->plugins . '/.modmenu-staging-*') ?: [];
    }

    private function installedVersion(): ?string
    {
        return PluginSwap::readVersion($this->plugins . '/ModMenu');
    }

    // ── state ──────────────────────────────────────────────────────────────

    public function testStateWithNoModMenu()
    {
        $this->assertSame(['installed' => null, 'self_updating' => false], $this->bootstrapper()->state($this->plugins));
    }

    public function testStateWithOldModMenu()
    {
        $this->seedInstalled('1.1.0');
        $this->assertSame(['installed' => '1.1.0', 'self_updating' => false], $this->bootstrapper()->state($this->plugins));
    }

    public function testStateWithSelfUpdatingModMenu()
    {
        $this->seedInstalled('1.3.0', true);
        $this->assertSame(['installed' => '1.3.0', 'self_updating' => true], $this->bootstrapper()->state($this->plugins));
    }

    // ── installZip ─────────────────────────────────────────────────────────

    public function testInstallZipReplacesModMenuAndKeepsPrevious()
    {
        $this->seedInstalled('1.1.0');

        $version = $this->bootstrapper()->installZip($this->makeZip(), $this->plugins);

        $this->assertSame('9.9.9', $version);
        $this->assertSame('9.9.9', $this->installedVersion());
        $this->assertFileExists($this->plugins . '/ModMenu/Model/PluginSwap.php');
        $this->assertSame('1.1.0', PluginSwap::readVersion($this->plugins . '/.modmenu-previous-ModMenu'));
        $this->assertSame([], $this->stagingDirs());
    }

    public function testInstallZipRefusesArchiveThatIsNotModMenu()
    {
        $this->seedInstalled('1.1.0');

        try {
            $this->bootstrapper()->installZip($this->makeZip('Other'), $this->plugins);
            $this->fail('expected ModMenuException');
        } catch (ModMenuException $e) {
            $this->assertStringContainsString('ModMenu', $e->getMessage());
        }

        $this->assertSame('1.1.0', $this->installedVersion());
        $this->assertSame([], $this->stagingDirs());
        $this->assertDirectoryDoesNotExist($this->plugins . '/Other');
    }

    public function testInstallZipRefusesWhenModMenuAlreadyUpdatesItself()
    {
        $this->seedInstalled('1.3.0', true);

        $this->expectException(ModMenuException::class);
        $this->expectExceptionMessage('ModMenu already updates itself');
        $this->bootstrapper()->installZip($this->makeZip(), $this->plugins);
    }

    public function testInstallZipRefusesVersionThatIsNotNewer()
    {
        $this->seedInstalled('9.9.9');

        try {
            $this->bootstrapper()->installZip($this->makeZip('ModMenu', '9.9.9'), $this->plugins);
            $this->fail('expected ModMenuException');
        } catch (ModMenuException $e) {
            $this->assertStringContainsString('not newer', $e->getMessage());
        }

        $this->assertSame('9.9.9', $this->installedVersion());
        $this->assertFileExists($this->plugins . '/ModMenu/Plugin.php');
        $this->assertDirectoryDoesNotExist($this->plugins . '/.modmenu-previous-ModMenu');
        $this->assertSame([], $this->stagingDirs());
    }

    public function testInstallZipRefusesReleaseWithoutSelfUpdate()
    {
        $this->seedInstalled('1.1.0');

        try {
            $this->bootstrapper()->installZip($this->makeZip('ModMenu', '1.2.0', false), $this->plugins);
            $this->fail('expected ModMenuException');
        } catch (ModMenuException $e) {
            $this->assertStringContainsString('does not support self-update', $e->getMessage());
        }

        $this->assertSame('1.1.0', $this->installedVersion());
        $this->assertSame([], $this->stagingDirs());
    }

    // ── downloadUrl ────────────────────────────────────────────────────────

    public function testDownloadUrlReturnsHttpsUrl()
    {
        $url = 'https://github.com/carmelosantana/kanboard-modmenu/releases/download/v1.3.0/ModMenu-1.3.0.zip';
        $this->assertSame($url, $this->bootstrapper()->downloadUrl(['ModMenu' => ['version' => '1.3.0', 'download' => $url]]));
    }

    public function testDownloadUrlRejectsHttp()
    {
        $this->expectException(ModMenuException::class);
        $this->bootstrapper()->downloadUrl(['ModMenu' => ['download' => 'http://example.com/ModMenu-1.3.0.zip']]);
    }

    public function testDownloadUrlRejectsMissingEntry()
    {
        $this->expectException(ModMenuException::class);
        $this->bootstrapper()->downloadUrl(['Other' => ['download' => 'https://example.com/Other.zip']]);
    }

    public function testDownloadUrlRejectsEntryWithoutDownload()
    {
        $this->expectException(ModMenuException::class);
        $this->bootstrapper()->downloadUrl(['ModMenu' => ['version' => '1.3.0']]);
    }

    // ── the staged copy is checked before any of its code is loaded ────────

    private function makeCustomZip(string $version, string $pluginPhp, string $swapPhp): string
    {
        $path = $this->work . '/custom-' . bin2hex(random_bytes(3)) . '.zip';
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE);
        $zip->addFromString('ModMenu/Plugin.php', $pluginPhp);
        $zip->addFromString('ModMenu/plugin.json', json_encode(['name' => 'ModMenu', 'version' => $version]));
        $zip->addFromString('ModMenu/Model/PluginSwap.php', $swapPhp);
        $zip->close();
        return $path;
    }

    private function realSwap(): string
    {
        return (string) file_get_contents((new ReflectionClass(PluginSwap::class))->getFileName());
    }

    private function assertRefusedBeforeLoad(string $zip, string $message): void
    {
        $b = new RecordingBootstrapper($this->container);
        try {
            $b->installZip($zip, $this->plugins);
            $this->fail('expected ModMenuException');
        } catch (ModMenuException $e) {
            $this->assertStringContainsString($message, $e->getMessage());
        }
        $this->assertSame([], $b->loaded, 'no staged code may be loaded before the checks pass');
        $this->assertSame('1.1.0', $this->installedVersion());
        $this->assertSame([], $this->stagingDirs());
    }

    public function testStagedCopyThatIsNotNewerIsRefusedBeforeLoading()
    {
        $this->seedInstalled('1.1.0');
        $this->assertRefusedBeforeLoad(
            $this->makeCustomZip('1.0.0', "<?php\nnamespace Kanboard\\Plugin\\ModMenu;\n", $this->realSwap()),
            'not newer'
        );
    }

    public function testStagedCopyWithWrongNamespaceIsRefusedBeforeLoading()
    {
        $this->seedInstalled('1.1.0');
        $this->assertRefusedBeforeLoad(
            $this->makeCustomZip('9.9.9', "<?php\nnamespace Other;\n", $this->realSwap()),
            'namespace'
        );
    }

    public function testStagedSwapThatIsNotThePluginSwapClassIsRefusedBeforeLoading()
    {
        $this->seedInstalled('1.1.0');
        $this->assertRefusedBeforeLoad(
            $this->makeCustomZip('9.9.9', "<?php\nnamespace Kanboard\\Plugin\\ModMenu;\n", "<?php\nnamespace Elsewhere;\nclass Thing {}\n"),
            'PluginSwap'
        );
    }

    public function testStagedSwapWithSyntaxErrorIsRefusedBeforeLoading()
    {
        $this->seedInstalled('1.1.0');
        $this->assertRefusedBeforeLoad(
            $this->makeCustomZip('9.9.9', "<?php\nnamespace Kanboard\\Plugin\\ModMenu;\n", "<?php\nnamespace Kanboard\\Plugin\\ModMenu\\Model;\nclass PluginSwap { function ( }\n"),
            'syntax'
        );
    }

    public function testValidStagedCopyIsLoadedOnce()
    {
        $this->seedInstalled('1.1.0');
        $b = new RecordingBootstrapper($this->container);
        $this->assertSame('9.9.9', $b->installZip($this->makeZip(), $this->plugins));
        $this->assertCount(1, $b->loaded);
    }
}
