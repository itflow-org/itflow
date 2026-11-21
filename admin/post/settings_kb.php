<?php

/*
 * Admin > Settings > Knowledge Base (admin/settings_kb.php): edit_kb_settings
 * saves whether the public help center is open.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['edit_kb_settings'])) {

    validateCSRFToken();

    if (!isset($config_kb_public_enable)) {
        flashAlert('Run the database update first - Admin > Update.', 'error');
        redirect();
    }

    $before = settingsCurrent(['config_kb_public_enable']);

    settingsSave('kb', $before, [
        // A switch sends nothing when off
        'config_kb_public_enable' => empty($_POST['config_kb_public_enable']) ? '0' : '1',
    ]);

    redirect();

}
