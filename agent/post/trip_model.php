<?php
defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

$date = escapeSql($_POST['date']);
$source = escapeSql($_POST['source']);
$destination = escapeSql($_POST['destination']);
$miles = floatval($_POST['miles']);
$roundtrip = intval($_POST['roundtrip'] ?? 0);
$purpose = escapeSql($_POST['purpose']);
$user_id = intval($_POST['user']);
