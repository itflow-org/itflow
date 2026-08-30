<?php
defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

$public_key = escapeSql($_POST['public_key']);
$private_key = escapeSql($_POST['private_key']);
$threshold = floatval($_POST['threshold']);
$account = intval($_POST['account']);
$expense_vendor = intval($_POST['expense_vendor']) ?? 0;
$expense_category = intval($_POST['expense_category']) ?? 0;