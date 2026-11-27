<?php

require_once "session_init.php";
require_once "redirect_if_setup_enabled.php";
require_once "auth_check.php";
require_once "inc_set_timezone.php";
require_once "load_user_session.php";
require_once "load_company_settings.php";
require_once "load_global_settings.php";
require_once "detect_device_type.php";

// A managed instance its manager has made read-only or suspended, and support sessions from
// the manager (functions/managed.php)
managedRequestGuard('agent');

// The demo - what visitors can't change there (functions/demo.php)
demoModeGuard();

// 2FA keys saved before they were kept encrypted - encrypted with this agent's vault, once a
// session until none are left (functions/credential.php)
credentialOtpVaultStored();
