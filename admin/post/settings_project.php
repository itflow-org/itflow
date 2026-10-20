<?php

/*
 * Admin > Settings > Projects (admin/settings_project.php):
 * edit_project_settings saves the numbering - a next number already in use
 * comes back to the page saying so.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['edit_project_settings'])) {

    validateCSRFToken();

    $before = settingsCurrent(['config_project_prefix', 'config_project_next_number']);

    [$prefix, $number, $errors] = settingsNumberingCheck('project', $_POST + array_map('strval', $before), $before);
    if ($errors) {
        settingsRefuse('project', $errors);
    }

    settingsSave('project', $before, ['config_project_prefix' => $prefix, 'config_project_next_number' => $number]);

    redirect();

}
