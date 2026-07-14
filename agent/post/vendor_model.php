<?php
defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

$name = escapeSql($_POST['name']);
$description = escapeSql($_POST['description']);
$account_number = escapeSql($_POST['account_number']);
$contact_name = escapeSql($_POST['contact_name']);
$phone_country_code = preg_replace("/[^0-9]/", '', $_POST['phone_country_code']);
$phone = preg_replace("/[^0-9]/", '', $_POST['phone']);
$extension = preg_replace("/[^0-9]/", '', $_POST['extension']);
$email = escapeSql($_POST['email']);
$website = preg_replace("(^https?://)", "", escapeSql($_POST['website']));
$hours = escapeSql($_POST['hours']);
$sla = escapeSql($_POST['sla']);
$code = escapeSql($_POST['code']);
$notes = escapeSql($_POST['notes']);
