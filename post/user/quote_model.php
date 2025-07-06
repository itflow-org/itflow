<?php
defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

$date = sanitizeInput($_POST['date']);
$expire = sanitizeInput($_POST['expire']);
$category = intval($_POST['category']);
$scope = sanitizeInput($_POST['scope']);
$quote_discount = floatval($_POST['quote_discount']);

$config_quote_prefix = sanitizeInput($config_quote_prefix);
