<?php
defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

$date = sanitizeInput($_POST['date']);
$amount = floatval($_POST['amount']);
$account_from = intval($_POST['account_from']);
$account_to = intval($_POST['account_to']);
$transfer_method = sanitizeInput($_POST['transfer_method']);
$notes = sanitizeInput($_POST['notes']);
