<?php

$client_id = intval($_POST['client']);
$subject = sanitizeInput($_POST['subject']);
$priority = sanitizeInput($_POST['priority']);
$details = mysqli_real_escape_string($mysqli, $_POST['details']);
$frequency = sanitizeInput($_POST['frequency']);

$asset_id = "0";
if (isset($_POST['asset'])) {
    $asset_id = intval($_POST['asset']);
}

$contact_id = "0";
if (isset($_POST['contact'])) {
    $contact_id = intval($_POST['contact']);
}

$assigned_to = "0";
if (isset($_POST['assigned_to'])) {
    $assigned_to = intval($_POST['assigned_to']);
}
