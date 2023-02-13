<?php
$date = trim(strip_tags(mysqli_real_escape_string($mysqli, $_POST['date'])));
$amount = floatval($_POST['amount']);
$account_from = intval($_POST['account_from']);
$account_to = intval($_POST['account_to']);
$notes = trim(strip_tags(mysqli_real_escape_string($mysqli, $_POST['notes'])));
