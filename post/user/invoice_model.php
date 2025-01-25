<?php
defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

$date = sanitizeInput($_POST['date']);
$category = intval($_POST['category']);
$scope = sanitizeInput($_POST['scope']);
$invoice_discount = floatval($_POST['invoice_discount']);
$recurring_discount = floatval($_POST['recurring_discount']);

$config_invoice_prefix = sanitizeInput($config_invoice_prefix);
