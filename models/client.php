<?php
$name = trim(strip_tags(mysqli_real_escape_string($mysqli, $_POST['name'])));
$type = trim(strip_tags(mysqli_real_escape_string($mysqli, $_POST['type'])));
$website = trim(strip_tags(mysqli_real_escape_string($mysqli, $_POST['website'])));
$referral = trim(strip_tags(mysqli_real_escape_string($mysqli, $_POST['referral'])));
$currency_code = trim(strip_tags(mysqli_real_escape_string($mysqli, $_POST['currency_code'])));
$net_terms = intval($_POST['net_terms']);
$notes = trim(strip_tags(mysqli_real_escape_string($mysqli, $_POST['notes'])));
