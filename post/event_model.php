<?php
$calendar_id = intval($_POST['calendar']);
$title = sanitizeInput($_POST['title']);
$location = sanitizeInput($_POST['location']);
$description = sanitizeInput($_POST['description']);
$start = sanitizeInput($_POST['start']);
$end = sanitizeInput($_POST['end']);
$repeat = sanitizeInput($_POST['repeat']);
$client = intval($_POST['client']);
$email_event = intval($_POST['email_event']);
