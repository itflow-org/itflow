<?php

/*
 * Admin > Settings > Theme - the colour of the top bar, links, buttons and
 * highlights (settingsThemes(), each a .theme-<name> block in
 * css/itflow_custom.css), shown across the page as it is picked
 * (js/settings.js), and the icon on the browser tab. Saved by
 * edit_theme_settings; reset_favicon puts ITFlow's own icon back
 * (admin/post/settings_theme.php).
 */

require_once "includes/inc_all_admin.php";

$themes = settingsThemes();
$picked = (string) settingsValue('config_theme', $config_theme);
$own_favicon = is_file(dirname(__DIR__) . '/uploads/favicon.ico');

echo settingsPageStart('theme');

?>

<form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off" data-settings-form data-settings-theme-form>
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="card itflow-settings-card">
        <div class="card-body">

            <?= settingsSectionStart('colour', 'Colour', 'The top bar, links, buttons and highlights, for everyone. Pick one to see it here - Save keeps it.') ?>
                <?php ob_start(); ?>
                <div class="row row-cols-2 row-cols-sm-3 row-cols-xl-4 g-2" role="radiogroup" aria-label="Theme colour">
                    <?php foreach ($themes as $theme => $theme_name) { ?>
                        <div class="col">
                            <input type="radio" class="btn-check" id="theme-<?= $theme ?>" name="config_theme" value="<?= $theme ?>" data-settings-theme <?= $picked === $theme ? 'checked' : '' ?>>
                            <label class="itflow-theme-swatch" for="theme-<?= $theme ?>">
                                <?php /* A miniature of the app, after AdminLTE 4's theme generator. The wrapper carries
                                         .theme-<name>, so everything inside paints from that theme's --itflow-accent and
                                         the preview can never drift out of step with the stylesheet. Decorative - the
                                         name below is what the label actually announces. */ ?>
                                <span class="itflow-theme-preview theme-<?= $theme ?>" aria-hidden="true">
                                    <span class="itflow-theme-preview-sidebar">
                                        <span class="itflow-theme-preview-brand"></span>
                                        <span class="itflow-theme-preview-item itflow-theme-preview-active"></span>
                                        <span class="itflow-theme-preview-item"></span>
                                        <span class="itflow-theme-preview-item"></span>
                                    </span>
                                    <span class="itflow-theme-preview-main">
                                        <span class="itflow-theme-preview-header">
                                            <span class="itflow-theme-preview-item"></span>
                                            <span class="itflow-theme-preview-avatar"></span>
                                        </span>
                                        <span class="itflow-theme-preview-body">
                                            <span class="itflow-theme-preview-box"></span>
                                            <span class="itflow-theme-preview-box itflow-theme-preview-box-muted"></span>
                                            <span class="itflow-theme-preview-card"></span>
                                        </span>
                                    </span>
                                </span>
                                <span class="itflow-theme-name"><?= escapeHtml($theme_name) ?></span>
                            </label>
                        </div>
                    <?php } ?>
                </div>
                <?= settingsField('config_theme', '', ob_get_clean()) ?>
            <?= settingsSectionEnd() ?>

            <?= settingsSectionStart('favicon', 'Browser tab icon', 'The little icon on ITFlow\'s browser tabs and bookmarks - yours, or ITFlow\'s paper plane.') ?>
                <div class="row g-3 align-items-center">
                    <div class="col-auto">
                        <div class="itflow-settings-picture is-icon">
                            <img id="settingsFaviconPreview" src="<?= $own_favicon ? '/uploads/favicon.ico?v=' . filemtime(dirname(__DIR__) . '/uploads/favicon.ico') : '/favicon.ico' ?>" alt="The tab icon">
                        </div>
                    </div>
                    <div class="col">
                        <?= settingsInput('favicon', '', ['icon' => 'fa-image', 'type' => 'file', 'label' => $own_favicon ? 'Replace it' : 'Use your own', 'accept' => '.ico,image/x-icon,image/vnd.microsoft.icon',
                            'data' => ['settings-preview' => 'settingsFaviconPreview'], 'help' => 'An .ico file, up to 1 MB.']) ?>
                        <?php if ($own_favicon) { ?>
                            <a href="post.php?reset_favicon&csrf_token=<?= $_SESSION['csrf_token'] ?>" class="btn btn-sm btn-outline-secondary mt-2 confirm-link" data-settings-reset-favicon><i class="fas fa-redo-alt me-2"></i>Back to ITFlow's icon</a>
                        <?php } ?>
                    </div>
                </div>
            <?= settingsSectionEnd() ?>

            <?= settingsSaveBar('edit_theme_settings') ?>
        </div>
    </div>
</form>

<?php
require_once "../includes/footer.php";
