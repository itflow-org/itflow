<?php

require_once "config.php";

require_once "functions.php";

require_once "check_login.php";

validateAdminRole();

// TODO: Change this to enforceAdminPermission();
//  We can't do this until everyone has the new database fields added in db 1.4.9 on Sept 14th 2024

require_once "header.php";

require_once "top_nav.php";

require_once "admin_side_nav.php";

require_once "inc_wrapper.php";

require_once "inc_alert_feedback.php";

require_once "filter_header.php";

require_once "app_version.php";
