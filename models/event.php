<?php
$calendar_id = intval($_POST['calendar']);
$title = trim(strip_tags(mysqli_real_escape_string($mysqli, $_POST['title'])));
$description = trim(strip_tags(mysqli_real_escape_string($mysqli, $_POST['description'])));
$start = trim(strip_tags(mysqli_real_escape_string($mysqli, $_POST['start'])));
$end = trim(strip_tags(mysqli_real_escape_string($mysqli, $_POST['end'])));
$repeat = trim(strip_tags(mysqli_real_escape_string($mysqli, $_POST['repeat'])));
$client = intval($_POST['client']);
$email_event = intval($_POST['email_event']);
