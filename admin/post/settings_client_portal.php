<?php

/*
 * Admin > Settings > Client portal (admin/settings_client_portal.php):
 * edit_client_portal_settings saves two-step sign-in for the portal - those
 * without it set up are taken to Your account to do it
 * (client/includes/check_login.php) - and, once the 3.4.6 update has run,
 * whether people can sign up themselves (functions/portal_invite.php).
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['edit_client_portal_settings'])) {

    validateCSRFToken();

    $columns = ['config_client_portal_require_mfa'];
    $after = ['config_client_portal_require_mfa' => empty($_POST['config_client_portal_require_mfa']) ? 0 : 1];
    if (portalInvitesReady()) {
        $columns[] = 'config_client_portal_signup';
        $after['config_client_portal_signup'] = empty($_POST['config_client_portal_signup']) ? 0 : 1;
    }
    settingsSave('client_portal', settingsCurrent($columns), $after);

    redirect();

}
