<?php
define('number_regex', '/[^0-9]/');

$name = trim(strip_tags(mysqli_real_escape_string($mysqli, $_POST['contact_name'])));
$title = trim(strip_tags(mysqli_real_escape_string($mysqli, $_POST['contact_title'])));
$department = trim(strip_tags(mysqli_real_escape_string($mysqli, $_POST['contact_department'])));
$email = trim(strip_tags(mysqli_real_escape_string($mysqli, $_POST['contact_email'])));
$phone = preg_replace(number_regex, '', $_POST['contact_phone']);
$extension = preg_replace(number_regex, '', $_POST['contact_extension']);
$mobile = preg_replace(number_regex, '', $_POST['contact_mobile']);
$notes = trim(strip_tags(mysqli_real_escape_string($mysqli, $_POST['contact_notes'])));
$auth_method = trim(strip_tags(mysqli_real_escape_string($mysqli, $_POST['contact_auth_method'])));
$important = intval($_POST['contact_important']);
$billing = intval($_POST['contact_billing']);
$technical = intval($_POST['contact_technical']);
$location_id = intval($_POST['contact_location_id']);
