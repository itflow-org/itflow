<?php
// Model of reusable variables for client credentials - not to be confused with the ITFLow login process
defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

// The form maxlength is client-side only - a hand-rolled POST gets here without it
if ($credential_field_too_long = checkCredentialLengths($_POST)) {
    flashAlert("Credential <strong>$credential_field_too_long</strong> is too long to store", 'error');
    redirect();
    exit;
}

$name = escapeSql($_POST['name']);
$description = escapeSql($_POST['description']);
$uri = escapeSql($_POST['uri']);
$uri_2 = escapeSql($_POST['uri_2']);
$username = encryptCredentialEntry(trim($_POST['username']));
$password = encryptCredentialEntry(trim($_POST['password']));
$otp_secret = escapeSql($_POST['otp_secret']);
$note = escapeSql($_POST['note']);
$favorite = intval($_POST['favorite'] ?? 0);
$contact_id = intval($_POST['contact'] ?? 0);
$asset_id = intval($_POST['asset'] ?? 0);
