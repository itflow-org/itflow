<?php
// Model of reusable variables for client credentials/logins - not to be confused with the ITFLow login process
defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

$client_id = intval($_POST['client_id']);
$name = sanitizeInput($_POST['name']);
$description = sanitizeInput($_POST['description']);
$uri = sanitizeInput($_POST['uri']);
$uri_2 = sanitizeInput($_POST['uri_2']);
$username = encryptLoginEntry(trim($_POST['username']));
$password = encryptLoginEntry(trim($_POST['password']));
$otp_secret = sanitizeInput($_POST['otp_secret']);
$note = sanitizeInput($_POST['note']);
$important = intval($_POST['important'] ?? 0);
$contact_id = intval($_POST['contact'] ?? 0);
$vendor_id = intval($_POST['vendor'] ?? 0);
$asset_id = intval($_POST['asset'] ?? 0);
$software_id = intval($_POST['software'] ?? 0);
