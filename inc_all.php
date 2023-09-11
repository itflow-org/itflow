<?php

require_once("config.php");
include_once("functions.php");
require_once("check_login.php");
require_once("header.php");
require_once("top_nav.php");
// FORCE MFA Setup
if ($session_user_config_force_mfa == 1 && $session_token == NULL) {
    header("Location: user_profile.php");
}
require_once("side_nav.php");
require_once("inc_wrapper.php");
require_once("inc_alert_feedback.php");
require_once("pagination_head.php");