<?php

require_once 'tests/units/Base.php';

use KanboardTests\units\Base;
use Kanboard\Plugin\ModMenuUpdater\Controller\UpdaterController;
use Kanboard\Core\Controller\AccessForbiddenException;

class ControllerAccessTest extends Base
{
    private function nonAdminController(): UpdaterController
    {
        // No user session => userSession->isAdmin() is false.
        return new UpdaterController($this->container);
    }

    public function testShowForbiddenForNonAdmin()
    {
        $this->expectException(AccessForbiddenException::class);
        $this->nonAdminController()->show();
    }

    public function testRunForbiddenForNonAdmin()
    {
        $this->expectException(AccessForbiddenException::class);
        $this->nonAdminController()->run();
    }
}
