<?php

/*
 * Admin > Settings > Client portal (admin/settings_client_portal.php):
 * edit_client_portal_settings saves two-step sign-in for the portal - those
 * without it set up are taken to Your account to do it
 * (client/includes/check_login.php).
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['edit_client_portal_settings'])) {

    validateCSRFToken();

    $before = settingsCurrent(['config_client_portal_require_mfa']);
    settingsSave('client_portal', $before, ['config_client_portal_require_mfa' => empty($_POST['config_client_portal_require_mfa']) ? 0 : 1]);

    redirect();

}
