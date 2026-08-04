<?php
defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

$name = escapeSql($_POST['name']);
$title = escapeSql($_POST['title']);
$department = escapeSql($_POST['department']);
$phone_country_code = preg_replace("/[^0-9]/", '', $_POST['phone_country_code']);
$phone = preg_replace("/[^0-9]/", '', $_POST['phone']);
$extension = preg_replace("/[^0-9]/", '', $_POST['extension']);
$mobile_country_code = preg_replace("/[^0-9]/", '', $_POST['mobile_country_code']);
$mobile = preg_replace("/[^0-9]/", '', $_POST['mobile']);
$email = escapeSql($_POST['email']);
$notes = escapeSql($_POST['notes']);
$contact_primary = intval($_POST['contact_primary'] ?? 0);
$contact_important = intval($_POST['contact_important'] ?? 0);
$contact_billing = intval($_POST['contact_billing'] ?? 0);
$contact_technical = intval($_POST['contact_technical'] ?? 0);
$location_id = intval($_POST['location'] ?? 0);
$pin = escapeSql($_POST['pin']);
$auth_method = escapeSql($_POST['auth_method']);
