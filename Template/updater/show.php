<div class="page-header"><h2><?= t('ModMenu Updater') ?></h2></div>

<?php if ($self_updating): ?>
    <p class="alert alert-success"><?= $this->text->e(t('ModMenu %s updates itself now. Remove ModMenuUpdater: ModMenu → Installed → ModMenuUpdater → Remove.', (string) $installed)) ?></p>
    <p><?= $this->url->link(t('Open ModMenu → Installed'), 'ModMenuController', 'show', ['plugin' => 'ModMenu']) ?></p>
<?php else: ?>
    <p><?= $this->text->e(t('ModMenu releases older than 1.3.0 cannot update themselves. This one-shot helper downloads the current ModMenu from your directory sources and puts it in place; afterwards ModMenu updates itself and this helper can be removed.')) ?></p>
    <ul>
        <li><?= $this->text->e(t('Installed ModMenu: %s', $installed ?? t('not found'))) ?></li>
        <li><?= $this->text->e(t('Available ModMenu: %s', $target ?? t('none'))) ?></li>
    </ul>

    <?php if ($target !== null): ?>
        <form method="post" action="<?= $this->url->href('UpdaterController', 'run', ['plugin' => 'ModMenuUpdater']) ?>">
            <?= $this->form->csrf() ?>
            <button type="submit" class="btn btn-blue"><?= $this->text->e(t('Update ModMenu to %s', $target)) ?></button>
        </form>
    <?php else: ?>
        <p class="alert alert-error"><?= $this->text->e(t('No ModMenu release was found in your directory sources.')) ?></p>
    <?php endif ?>
<?php endif ?>
