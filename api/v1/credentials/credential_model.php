<?php


// Variable assignment from POST (or: blank/from DB is updating)

$api_key_decrypt_password = '';
if (isset($_POST['api_key_decrypt_password'])) {
    $api_key_decrypt_password = $_POST['api_key_decrypt_password']; // No sanitization
}

if (isset($_POST['login_name'])) {
    $name = sanitizeInput($_POST['login_name']);
} elseif (isset($credential_row) && isset($credential_row['login_name'])) {
    $name = $credential_row['login_name'];
} else {
    $name = '';
}

if (isset($_POST['login_description'])) {
    $description = sanitizeInput($_POST['login_description']);
} elseif (isset($credential_row) && isset($credential_row['login_description'])) {
    $description = $credential_row['login_description'];
} else {
    $description = '';
}

if (isset($_POST['login_uri'])) {
    $uri = sanitizeInput($_POST['login_uri']);
} elseif (isset($credential_row) && isset($credential_row['login_uri'])) {
    $uri = $credential_row['login_uri'];
} else {
    $uri = '';
}

if (isset($_POST['login_uri_2'])) {
    $uri_2 = sanitizeInput($_POST['login_uri_2']);
} elseif (isset($credential_row) && isset($credential_row['login_uri_2'])) {
    $uri_2 = $credential_row['login_uri_2'];
} else {
    $uri_2 = '';
}

if (isset($_POST['login_username'])) {
    $username = $_POST['login_username'];
    $username = apiEncryptLoginEntry($username, $api_key_decrypt_hash, $api_key_decrypt_password);
} elseif (isset($credential_row) && isset($credential_row['login_username'])) {
    $username = $credential_row['login_username'];
} else {
    $username = '';
}

if (isset($_POST['login_password'])) {
    $password = $_POST['login_password'];
    $password = apiEncryptLoginEntry($password, $api_key_decrypt_hash, $api_key_decrypt_password);
    $password_changed = true;
} elseif (isset($credential_row) && isset($credential_row['login_password'])) {
    $password = $credential_row['login_password'];
    $password_changed = false;
} else {
    $password = '';
    $password_changed = false;
}



if (isset($_POST['login_otp_secret'])) {
    $otp_secret = sanitizeInput($_POST['login_otp_secret']);
} elseif (isset($credential_row) && isset($credential_row['login_otp_secret'])) {
    $otp_secret = $credential_row['login_otp_secret'];
} else {
    $otp_secret = '';
}

if (isset($_POST['login_note'])) {
    $note = sanitizeInput($_POST['login_note']);
} elseif (isset($credential_row) && isset($credential_row['login_note'])) {
    $note = $credential_row['login_note'];
} else {
    $note = '';
}

if (isset($_POST['login_important'])) {
    $important = intval($_POST['login_important']);
} elseif (isset($credential_row) && isset($credential_row['login_important'])) {
    $important = $credential_row['login_important'];
} else {
    $important = '';
}

if (isset($_POST['login_contact_id'])) {
    $contact_id = intval($_POST['login_contact_id']);
} elseif (isset($credential_row) && isset($credential_row['login_contact_id'])) {
    $contact_id = $credential_row['login_contact_id'];
} else {
    $contact_id = '';
}

if (isset($_POST['login_vendor_id'])) {
    $vendor_id = intval($_POST['login_vendor_id']);
} elseif (isset($credential_row) && isset($credential_row['login_vendor_id'])) {
    $vendor_id = $credential_row['login_vendor_id'];
} else {
    $vendor_id = '';
}

if (isset($_POST['login_asset_id'])) {
    $asset_id = intval($_POST['login_asset_id']);
} elseif (isset($credential_row) && isset($credential_row['login_asset_id'])) {
    $asset_id = $credential_row['login_asset_id'];
} else {
    $asset_id = '';
}

if (isset($_POST['login_software_id'])) {
    $software_id = intval($_POST['login_software_id']);
} elseif (isset($credential_row) && isset($credential_row['login_software_id'])) {
    $software_id = $credential_row['login_software_id'];
} else {
    $software_id = '';
}
