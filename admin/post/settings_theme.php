<?php

/*
 * Admin > Settings > Theme (admin/settings_theme.php): edit_theme_settings
 * saves the colour - one of settingsThemes() - and a new tab icon, a real
 * .ico; reset_favicon puts ITFlow's own icon back.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['edit_theme_settings'])) {

    validateCSRFToken();

    $before = settingsCurrent(['config_theme']);
    $theme = (string) ($_POST['config_theme'] ?? '');

    $errors = [];
    if (!isset(settingsThemes()[$theme])) {
        $errors['config_theme'] = 'Pick one of the colours.';
    }
    $favicon_problem = settingsImageProblem($_FILES['favicon'] ?? null, ['ico'], ['ico'], '.ico', 1048576);
    if ($favicon_problem !== '') {
        $errors['favicon'] = $favicon_problem;
    }
    if ($errors) {
        settingsRefuse('theme', $errors);
    }

    // A new tab icon - always uploads/favicon.ico, which every page header looks for
    $also = [];
    if (!empty($_FILES['favicon']['tmp_name']) && ($_FILES['favicon']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK && is_uploaded_file($_FILES['favicon']['tmp_name'])) {
        if (move_uploaded_file($_FILES['favicon']['tmp_name'], dirname(__DIR__, 2) . '/uploads/favicon.ico')) {
            $also[] = 'Browser tab icon changed';
        }
    }

    settingsSave('theme', $before, ['config_theme' => $theme], 'settings', $also);

    redirect();

}

if (isset($_GET['reset_favicon'])) {

    validateCSRFToken();

    if (is_file(dirname(__DIR__, 2) . '/uploads/favicon.ico')) {
        unlink(dirname(__DIR__, 2) . '/uploads/favicon.ico');
    }

    logAudit("Settings", "Edit", "$session_name put the ITFlow browser tab icon back");

    flashAlert("The browser tab icon is ITFlow's own again");

    redirect();

}
