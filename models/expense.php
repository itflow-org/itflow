<?php
$date = trim(strip_tags(mysqli_real_escape_string($mysqli, $_POST['date'])));
$amount = floatval($_POST['amount']);
$account = intval($_POST['account']);
$vendor = intval($_POST['vendor']);
$category = intval($_POST['category']);
$description = trim(strip_tags(mysqli_real_escape_string($mysqli, $_POST['description'])));
$reference = trim(strip_tags(mysqli_real_escape_string($mysqli, $_POST['reference'])));
