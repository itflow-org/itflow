<?php
defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

$calendar_id = intval($_POST['calendar']);
$title = escapeSql($_POST['title']);
$location = escapeSql($_POST['location']);
$description = escapeSql($_POST['description']);
$start = escapeSql($_POST['start']);
$end = escapeSql($_POST['end']);
$repeat = escapeSql($_POST['repeat'] ?? 0);
$client_id = intval($_POST['client_id']);
$email_event = intval($_POST['email_event'] ?? 0);
