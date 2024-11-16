<?php

$client_id = intval($_POST['client_id']);
$name = sanitizeInput($_POST['name']);
$title = sanitizeInput($_POST['title']);
$department = sanitizeInput($_POST['department']);
$phone = preg_replace("/[^0-9]/", '', $_POST['phone']);
$extension = preg_replace("/[^0-9]/", '', $_POST['extension']);
$mobile = preg_replace("/[^0-9]/", '', $_POST['mobile']);
$email = sanitizeInput($_POST['email']);
$notes = sanitizeInput($_POST['notes']);
$contact_primary = intval($_POST['contact_primary'] ?? 0);
$contact_important = intval($_POST['contact_important'] ?? 0);
$contact_billing = intval($_POST['contact_billing'] ?? 0);
$contact_technical = intval($_POST['contact_technical'] ?? 0);
$location_id = intval($_POST['location']);
$pin = sanitizeInput($_POST['pin']);
$auth_method = sanitizeInput($_POST['auth_method']);

