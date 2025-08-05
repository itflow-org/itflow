<?php

require_once "../config.php";
require_once "../functions.php";
require_once "../includes/router.php";
require_once "../includes/check_login.php";
require_once "../includes/page_title.php";
if (!isset($session_is_admin) || !$session_is_admin) {
    exit(WORDING_ROLECHECK_FAILED . "<br>Tell your admin: Your role does not have admin access.");
}
require_once "../includes/header.php";
require_once "../includes/top_nav.php";
require_once "includes/side_nav.php";
require_once "../includes/inc_wrapper.php";
require_once "../includes/inc_alert_feedback.php";
require_once "../includes/filter_header.php";
require_once "../includes/app_version.php";
