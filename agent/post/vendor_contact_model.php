<?php
defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

$client_id = intval($_POST['client_id']);
$vendor_id = intval($_POST['vendor_id']);
$name = escapeSql($_POST['name']);
$title = escapeSql($_POST['title']);
$department = escapeSql($_POST['department']);
$phone = preg_replace("/[^0-9]/", '', $_POST['phone']);
$extension = preg_replace("/[^0-9]/", '', $_POST['extension']);
$mobile = preg_replace("/[^0-9]/", '', $_POST['mobile']);
$email = escapeSql($_POST['email']);
$notes = escapeSql($_POST['notes']);
