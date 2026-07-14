<?php
defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

$subject = escapeSql($_POST['subject']);
$priority = escapeSql($_POST['priority']);
$details = mysqli_real_escape_string($mysqli, $_POST['details']);
$frequency = escapeSql($_POST['frequency']);
$billable = intval($_POST['billable'] ?? 0);
$asset_id = intval($_POST['asset_id'] ?? 0);
$contact_id = intval($_POST['contact_id'] ?? 0);
$assigned_to = intval($_POST['assigned_to'] ?? 0);
$category_id = intval($_POST['category_id'] ?? 0);
