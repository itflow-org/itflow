<?php
$name = trim(strip_tags(mysqli_real_escape_string($mysqli, $_POST['name'])));
$address = trim(strip_tags(mysqli_real_escape_string($mysqli, $_POST['address'])));
$city = trim(strip_tags(mysqli_real_escape_string($mysqli, $_POST['city'])));
$state = trim(strip_tags(mysqli_real_escape_string($mysqli, $_POST['state'])));
$zip = trim(strip_tags(mysqli_real_escape_string($mysqli, $_POST['zip'])));
$country = trim(strip_tags(mysqli_real_escape_string($mysqli, $_POST['country'])));
$phone = preg_replace("/[^0-9]/", '',$_POST['phone']);
$email = trim(strip_tags(mysqli_real_escape_string($mysqli, $_POST['email'])));
$website = trim(strip_tags(mysqli_real_escape_string($mysqli, $_POST['website'])));
$locale = trim(strip_tags(mysqli_real_escape_string($mysqli, $_POST['locale'])));
$currency_code = trim(strip_tags(mysqli_real_escape_string($mysqli, $_POST['currency_code'])));
