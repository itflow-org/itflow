<?php
defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

$date = sanitizeInput($_POST['date']);
$amount = floatval($_POST['amount']);
$account = intval($_POST['account']);
$vendor = intval($_POST['vendor']);
$client = intval($_POST['client']);
$category = intval($_POST['category']);
$description = sanitizeInput($_POST['description']);
$reference = sanitizeInput($_POST['reference']);
