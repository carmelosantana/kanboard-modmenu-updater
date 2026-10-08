<?php

require_once 'tests/units/Base.php';

use KanboardTests\units\Base;

class ShowTemplateTest extends Base
{
    private function render(?string $installed, ?string $target, bool $selfUpdating): string
    {
        $html = $this->container['template']->render('ModMenuUpdater:updater/show', [
            'installed' => $installed,
            'target' => $target,
            'self_updating' => $selfUpdating,
        ]);
        return html_entity_decode($html, ENT_QUOTES | ENT_HTML5);
    }

    public function testSelfUpdatingTellsAdminToRemoveTheUpdater()
    {
        $html = $this->render('1.3.0', '1.3.0', true);
        $this->assertStringContainsString('ModMenu 1.3.0 updates itself now. Remove ModMenuUpdater: ModMenu → Installed → ModMenuUpdater → Remove.', $html);
        $this->assertStringContainsString('controller=ModMenuController', $html);
        $this->assertStringContainsString('plugin=ModMenu', $html);
        $this->assertStringNotContainsString('<form', $html);
    }

    public function testOldModMenuOffersUpdateButton()
    {
        $html = $this->render('1.1.0', '1.3.0', false);
        $this->assertStringContainsString('1.1.0', $html);
        $this->assertStringContainsString('<form method="post"', $html);
        $this->assertStringContainsString('action=run', $html);
        $this->assertStringContainsString('name="csrf_token"', $html);
        $this->assertStringContainsString('Update ModMenu to 1.3.0', $html);
    }

    public function testNoTargetShowsNoButton()
    {
        $html = $this->render('1.1.0', null, false);
        $this->assertStringNotContainsString('<form', $html);
        $this->assertStringContainsString('No ModMenu release was found in your directory sources.', $html);
    }

    public function testValuesAreEscaped()
    {
        $html = $this->container['template']->render('ModMenuUpdater:updater/show', [
            'installed' => '<b>x</b>', 'target' => '<i>y</i>', 'self_updating' => false,
        ]);
        $this->assertStringNotContainsString('<b>x</b>', $html);
        $this->assertStringNotContainsString('<i>y</i>', $html);
    }
}
