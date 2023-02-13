<?php
$client_id = intval($_POST['client_id']);
$name = trim(strip_tags(mysqli_real_escape_string($mysqli, $_POST['name'])));
$title = trim(strip_tags(mysqli_real_escape_string($mysqli, $_POST['title'])));
$department = trim(strip_tags(mysqli_real_escape_string($mysqli, $_POST['department'])));
$phone = preg_replace("/[^0-9]/", '',$_POST['phone']);
$extension = preg_replace("/[^0-9]/", '',$_POST['extension']);
$mobile = preg_replace("/[^0-9]/", '',$_POST['mobile']);
$email = trim(strip_tags(mysqli_real_escape_string($mysqli, $_POST['email'])));
$primary_contact = intval($_POST['primary_contact']);
$notes = trim(strip_tags(mysqli_real_escape_string($mysqli, $_POST['notes'])));
$contact_important = intval($_POST['contact_important']);
$contact_billing = intval($_POST['contact_billing']);
$contact_technical = intval($_POST['contact_technical']);
$location_id = intval($_POST['location']);
$auth_method = trim(strip_tags(mysqli_real_escape_string($mysqli, $_POST['auth_method'])));
