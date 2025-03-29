<?php


// Variable assignment from POST (or: blank/from DB is updating)

$api_key_decrypt_password = '';
if (isset($_POST['api_key_decrypt_password'])) {
    $api_key_decrypt_password = $_POST['api_key_decrypt_password']; // No sanitization
}

if (isset($_POST['credential_name'])) {
    $name = sanitizeInput($_POST['credential_name']);
} elseif (isset($credential_row) && isset($credential_row['credential_name'])) {
    $name = $credential_row['credential_name'];
} else {
    $name = '';
}

if (isset($_POST['credential_description'])) {
    $description = sanitizeInput($_POST['credential_description']);
} elseif (isset($credential_row) && isset($credential_row['credential_description'])) {
    $description = $credential_row['credential_description'];
} else {
    $description = '';
}

if (isset($_POST['credential_uri'])) {
    $uri = sanitizeInput($_POST['credential_uri']);
} elseif (isset($credential_row) && isset($credential_row['credential_uri'])) {
    $uri = $credential_row['credential_uri'];
} else {
    $uri = '';
}

if (isset($_POST['credential_uri_2'])) {
    $uri_2 = sanitizeInput($_POST['credential_uri_2']);
} elseif (isset($credential_row) && isset($credential_row['credential_uri_2'])) {
    $uri_2 = $credential_row['credential_uri_2'];
} else {
    $uri_2 = '';
}

if (isset($_POST['credential_username'])) {
    $username = $_POST['credential_username'];
    $username = apiEncryptLoginEntry($username, $api_key_decrypt_hash, $api_key_decrypt_password);
} elseif (isset($credential_row) && isset($credential_row['credential_username'])) {
    $username = $credential_row['credential_username'];
} else {
    $username = '';
}

if (isset($_POST['credential_password'])) {
    $password = $_POST['credential_password'];
    $password = apiEncryptLoginEntry($password, $api_key_decrypt_hash, $api_key_decrypt_password);
    $password_changed = true;
} elseif (isset($credential_row) && isset($credential_row['credential_password'])) {
    $password = $credential_row['credential_password'];
    $password_changed = false;
} else {
    $password = '';
    $password_changed = false;
}



if (isset($_POST['credential_otp_secret'])) {
    $otp_secret = sanitizeInput($_POST['credential_otp_secret']);
} elseif (isset($credential_row) && isset($credential_row['credential_otp_secret'])) {
    $otp_secret = $credential_row['credential_otp_secret'];
} else {
    $otp_secret = '';
}

if (isset($_POST['credential_note'])) {
    $note = sanitizeInput($_POST['credential_note']);
} elseif (isset($credential_row) && isset($credential_row['credential_note'])) {
    $note = $credential_row['credential_note'];
} else {
    $note = '';
}

if (isset($_POST['credential_important'])) {
    $important = intval($_POST['credential_important']);
} elseif (isset($credential_row) && isset($credential_row['credential_important'])) {
    $important = $credential_row['credential_important'];
} else {
    $important = '';
}

if (isset($_POST['credential_contact_id'])) {
    $contact_id = intval($_POST['credential_contact_id']);
} elseif (isset($credential_row) && isset($credential_row['credential_contact_id'])) {
    $contact_id = $credential_row['credential_contact_id'];
} else {
    $contact_id = '';
}

if (isset($_POST['credential_vendor_id'])) {
    $vendor_id = intval($_POST['credential_vendor_id']);
} elseif (isset($credential_row) && isset($credential_row['credential_vendor_id'])) {
    $vendor_id = $credential_row['credential_vendor_id'];
} else {
    $vendor_id = '';
}

if (isset($_POST['credential_asset_id'])) {
    $asset_id = intval($_POST['credential_asset_id']);
} elseif (isset($credential_row) && isset($credential_row['credential_asset_id'])) {
    $asset_id = $credential_row['credential_asset_id'];
} else {
    $asset_id = '';
}

if (isset($_POST['credential_software_id'])) {
    $software_id = intval($_POST['credential_software_id']);
} elseif (isset($credential_row) && isset($credential_row['credential_software_id'])) {
    $software_id = $credential_row['credential_software_id'];
} else {
    $software_id = '';
}
