<?php

/*
 * The old single include for a report page - signed in, allowed to use
 * reports, and the page drawn - kept for custom report pages that still use
 * it. The pages here use inc_report.php and inc_report_layout.php, so they
 * can send a CSV before anything is drawn.
 */

require_once __DIR__ . '/inc_report.php';
require_once __DIR__ . '/inc_report_layout.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/filter_header.php';

// Set variable default values
$largest_income_month = 0;
$largest_invoice_month = 0;
$recurring_total = 0;
