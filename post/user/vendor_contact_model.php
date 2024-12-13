<?php

$client_id = intval($_POST['client_id']);
$vendor_id = intval($_POST['vendor_id']);
$name = sanitizeInput($_POST['name']);
$title = sanitizeInput($_POST['title']);
$department = sanitizeInput($_POST['department']);
$phone = preg_replace("/[^0-9]/", '', $_POST['phone']);
$extension = preg_replace("/[^0-9]/", '', $_POST['extension']);
$mobile = preg_replace("/[^0-9]/", '', $_POST['mobile']);
$email = sanitizeInput($_POST['email']);
$notes = sanitizeInput($_POST['notes']);
