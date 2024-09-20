<?php

require_once "config.php";

require_once "functions.php";

require_once "check_login.php";

// Reporting Perms
enforceUserPermission('module_reporting');

require_once "header.php";

require_once "top_nav.php";

require_once "reports_side_nav.php";

require_once "inc_wrapper.php";

require_once "inc_alert_feedback.php";

require_once "pagination_head.php";

// Set variable default values
$largest_income_month = 0;
$largest_invoice_month = 0;
$recurring_total = 0;
