<?php

/*
 * Admin > Client Portal - the portal's own settings.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['edit_client_portal_settings'])) {

    validateCSRFToken();

    // Two-step sign-in for everyone signing in with a password - those without
    // it set up are taken to Your account to do it (client/includes/check_login.php)
    $require_mfa = intval($_POST['config_client_portal_require_mfa'] ?? 0) === 1 ? 1 : 0;

    mysqli_query($mysqli, "UPDATE settings SET config_client_portal_require_mfa = $require_mfa WHERE company_id = 1");

    logAudit("Settings", "Edit", "$session_name " . ($require_mfa ? 'required' : 'stopped requiring') . " two-step sign-in for the client portal");

    flashAlert("Client portal settings saved");

    redirect();

}
