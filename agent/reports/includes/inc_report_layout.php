<?php

/*
 * The page around a report - the top bar, the Reports menu and the content
 * wrapper. Comes after inc_report.php and after the page has had its chance
 * to send a CSV instead.
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/header.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/top_nav.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/agent/reports/includes/reports_side_nav.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/inc_wrapper.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/inc_alert_feedback.php';
