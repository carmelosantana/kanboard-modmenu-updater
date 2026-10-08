<?php

namespace Kanboard\Plugin\ModMenuUpdater\Controller;

use Kanboard\Controller\BaseController;
use Kanboard\Core\Controller\AccessForbiddenException;
use Kanboard\Plugin\ModMenu\Exception\ModMenuException;
use Kanboard\Plugin\ModMenu\Model\DirectoryClient;
use Kanboard\Plugin\ModMenuUpdater\Model\Bootstrapper;

/**
 * Admin-only page with one button: replace the installed ModMenu with the
 * release listed in ModMenu's directory sources.
 */
class UpdaterController extends BaseController
{
    private function requireAdmin(): void
    {
        if (! $this->userSession->isAdmin()) {
            throw new AccessForbiddenException();
        }
    }

    public function show()
    {
        $this->requireAdmin();
        $state = (new Bootstrapper($this->container))->state(PLUGINS_DIR);
        $target = $state['self_updating'] ? null : ((new DirectoryClient($this->container))->catalogMap()['ModMenu']['version'] ?? null);

        $this->response->html($this->helper->layout->config('ModMenuUpdater:updater/show', [
            'title' => t('ModMenu Updater'),
            'installed' => $state['installed'],
            'target' => $target === null ? null : (string) $target,
            'self_updating' => $state['self_updating'],
        ]));
    }

    public function run()
    {
        $this->requireAdmin();
        $this->checkCSRFForm();

        $bootstrapper = new Bootstrapper($this->container);
        $tmp = null;
        try {
            $url = $bootstrapper->downloadUrl((new DirectoryClient($this->container))->catalogMap());
            $body = $this->httpClient->get($url);
            if (empty($body)) {
                throw new ModMenuException(t('Unable to download the ModMenu archive.'));
            }
            $tmp = tempnam(sys_get_temp_dir(), 'modmenu_updater');
            file_put_contents($tmp, $body);

            $version = $bootstrapper->installZip($tmp, PLUGINS_DIR);
            $this->flash->success(t('ModMenu %s is installed. Remove ModMenuUpdater from ModMenu → Installed.', $version));
        } catch (\Exception $e) { // ModMenuException, or a transport error
            $this->flash->failure($e->getMessage());
        } finally {
            if ($tmp !== null && $tmp !== false) {
                @unlink($tmp);
            }
        }

        $this->response->redirect($this->helper->url->to('UpdaterController', 'show', ['plugin' => 'ModMenuUpdater']));
    }
}
