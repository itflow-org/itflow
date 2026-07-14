<?php
defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

$date = escapeSql($_POST['date']);
$amount = floatval($_POST['amount']);
$account_from = intval($_POST['account_from']);
$account_to = intval($_POST['account_to']);
$transfer_method = escapeSql($_POST['transfer_method']);
$notes = escapeSql($_POST['notes']);
