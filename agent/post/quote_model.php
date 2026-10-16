<?php
defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

$date = escapeSql($_POST['date']);
$expire = escapeSql($_POST['expire']);
$category = intval($_POST['category']);
$scope = escapeSql($_POST['scope'] ?? '');
$quote_discount = round(floatval($_POST['quote_discount'] ?? 0), 2);

$config_quote_prefix = escapeSql($config_quote_prefix);
