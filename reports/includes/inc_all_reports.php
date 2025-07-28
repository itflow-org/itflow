<?php

require_once "../config.php";
require_once "../functions.php";
require_once "../includes/check_login.php";
require_once "../includes/page_title.php";
// Reporting Perms
enforceUserPermission('module_reporting');
require_once "includes/header.php";
require_once "../includes/top_nav.php";
require_once "includes/side_nav.php";
require_once "../includes/inc_wrapper.php";
require_once "../includes/inc_alert_feedback.php";
require_once "../includes/filter_header.php";

// Set variable default values
$largest_income_month = 0;
$largest_invoice_month = 0;
$recurring_total = 0;
