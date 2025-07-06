<?php
defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

$client_id = intval($_POST['client']);
$subject = sanitizeInput($_POST['subject']);
$priority = sanitizeInput($_POST['priority']);
$details = mysqli_real_escape_string($mysqli, $_POST['details']);
$frequency = sanitizeInput($_POST['frequency']);
$billable = intval($_POST['billable'] ?? 0);
$asset_id = intval($_POST['asset'] ?? 0);
$contact_id = intval($_POST['contact'] ?? 0);
$assigned_to = intval($_POST['assigned_to'] ?? 0);
$category = intval($_POST['category'] ?? 0); 