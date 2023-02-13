<?php
$date = trim(strip_tags(mysqli_real_escape_string($mysqli, $_POST['date'])));
$source = trim(strip_tags(mysqli_real_escape_string($mysqli, $_POST['source'])));
$destination = trim(strip_tags(mysqli_real_escape_string($mysqli, $_POST['destination'])));
$miles = floatval($_POST['miles']);
$roundtrip = intval($_POST['roundtrip']);
$purpose = trim(strip_tags(mysqli_real_escape_string($mysqli, $_POST['purpose'])));
$user_id = intval($_POST['user']);
$client_id = intval($_POST['client']);
