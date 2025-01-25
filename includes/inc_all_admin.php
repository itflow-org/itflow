<?php

require_once "config.php";
require_once "functions.php";
require_once "check_login.php";
require_once "page_title.php";
if (!isset($session_is_admin) || !$session_is_admin) {
    exit(WORDING_ROLECHECK_FAILED . "<br>Tell your admin: Your role does not have admin access.");
}
require_once "header.php";
require_once "top_nav.php";
require_once "admin_side_nav.php";
require_once "inc_wrapper.php";
require_once "inc_alert_feedback.php";
require_once "filter_header.php";
require_once "includes/app_version.php";
