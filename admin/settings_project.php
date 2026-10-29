<?php

/*
 * Admin > Settings > Projects - how projects are numbered. Saved by
 * edit_project_settings (admin/post/settings_project.php).
 */

require_once "includes/inc_all_admin.php";

echo settingsPageStart('project');

?>

<form action="post.php" method="post" autocomplete="off" data-settings-form>
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="card itflow-settings-card">
        <div class="card-body">

            <?= settingsSectionStart('numbering', 'Numbering', 'Each project gets the next number, after its prefix.') ?>
                <?= settingsNumberingFields('project', $config_project_prefix, $config_project_next_number) ?>
            <?= settingsSectionEnd() ?>

            <?= settingsSaveBar('edit_project_settings') ?>
        </div>
    </div>
</form>

<?php
require_once "../includes/footer.php";
