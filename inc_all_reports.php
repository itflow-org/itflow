<?php

include("config.php");
include_once("functions.php");
include("check_login.php");
include("header.php");
include("top_nav.php");
include("reports_side_nav.php");
include("inc_wrapper.php");
include("inc_alert_feedback.php");

// Set variable default values
$largest_income_month = 0;
$largest_invoice_month = 0;
$recurring_total = 0;
