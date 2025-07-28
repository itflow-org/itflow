<?php
defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

$name = sanitizeInput($_POST['name']);
$description = sanitizeInput($_POST['description']);
$account_number = sanitizeInput($_POST['account_number']);
$contact_name = sanitizeInput($_POST['contact_name']);
$phone_country_code = preg_replace("/[^0-9]/", '', $_POST['phone_country_code']);
$phone = preg_replace("/[^0-9]/", '', $_POST['phone']);
$extension = preg_replace("/[^0-9]/", '', $_POST['extension']);
$email = sanitizeInput($_POST['email']);
$website = preg_replace("(^https?://)", "", sanitizeInput($_POST['website']));
$hours = sanitizeInput($_POST['hours']);
$sla = sanitizeInput($_POST['sla']);
$code = sanitizeInput($_POST['code']);
$notes = sanitizeInput($_POST['notes']);
