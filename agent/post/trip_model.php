<?php
defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

$date = sanitizeInput($_POST['date']);
$source = sanitizeInput($_POST['source']);
$destination = sanitizeInput($_POST['destination']);
$miles = floatval($_POST['miles']);
$roundtrip = intval($_POST['roundtrip'] ?? 0);
$purpose = sanitizeInput($_POST['purpose']);
$user_id = intval($_POST['user']);
$client_id = intval($_POST['client']);
