<?php
$name = trim(strip_tags(mysqli_real_escape_string($mysqli, $_POST['name'])));
$description = trim(strip_tags(mysqli_real_escape_string($mysqli, $_POST['description'])));
$account_number = trim(strip_tags(mysqli_real_escape_string($mysqli, $_POST['account_number'])));
$contact_name = trim(strip_tags(mysqli_real_escape_string($mysqli, $_POST['contact_name'])));
$phone = preg_replace("/[^0-9]/", '', $_POST['phone']);
$extension = trim(strip_tags(mysqli_real_escape_string($mysqli, $_POST['extension'])));
$email = trim(strip_tags(mysqli_real_escape_string($mysqli, $_POST['email'])));
$website = trim(strip_tags(mysqli_real_escape_string($mysqli, $_POST['website'])));
$hours = trim(strip_tags(mysqli_real_escape_string($mysqli, $_POST['hours'])));
$sla = trim(strip_tags(mysqli_real_escape_string($mysqli, $_POST['sla'])));
$code = trim(strip_tags(mysqli_real_escape_string($mysqli, $_POST['code'])));
$notes = trim(strip_tags(mysqli_real_escape_string($mysqli, $_POST['notes'])));
