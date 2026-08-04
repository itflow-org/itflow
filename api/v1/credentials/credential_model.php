<?php


// Variable assignment from POST (or: blank/from DB is updating)

/*
 * There is no form behind the API, so nothing has capped these before they arrive.
 * An overlong value is a hard MySQL error, not a truncation, so it would surface as a
 * generic "insert query failed" - say what actually went wrong instead.
 * Only the fields present are checked, which keeps partial updates working.
 */
$credential_field_too_long = checkCredentialLengths([
    'name'        => $_POST['credential_name'] ?? null,
    'description' => $_POST['credential_description'] ?? null,
    'uri'         => $_POST['credential_uri'] ?? null,
    'uri_2'       => $_POST['credential_uri_2'] ?? null,
    'username'    => $_POST['credential_username'] ?? null,
    'password'    => $_POST['credential_password'] ?? null,
    'otp_secret'  => $_POST['credential_otp_secret'] ?? null,
]);

if ($credential_field_too_long) {
    $return_arr['success'] = "False";
    $return_arr['message'] = "credential_$credential_field_too_long is too long to store.";
    echo json_encode($return_arr);
    exit();
}

$api_key_decrypt_password = '';
if (isset($_POST['api_key_decrypt_password'])) {
    $api_key_decrypt_password = $_POST['api_key_decrypt_password']; // No sanitization
}

if (isset($_POST['credential_name'])) {
    $name = escapeSql($_POST['credential_name']);
} elseif (isset($credential_row) && isset($credential_row['credential_name'])) {
    $name = mysqli_real_escape_string($mysqli, $credential_row['credential_name']);
} else {
    $name = '';
}

if (isset($_POST['credential_description'])) {
    $description = escapeSql($_POST['credential_description']);
} elseif (isset($credential_row) && isset($credential_row['credential_description'])) {
    $description = mysqli_real_escape_string($mysqli, $credential_row['credential_description']);
} else {
    $description = '';
}

if (isset($_POST['credential_uri'])) {
    $uri = escapeSql($_POST['credential_uri']);
} elseif (isset($credential_row) && isset($credential_row['credential_uri'])) {
    $uri = mysqli_real_escape_string($mysqli, $credential_row['credential_uri']);
} else {
    $uri = '';
}

if (isset($_POST['credential_uri_2'])) {
    $uri_2 = escapeSql($_POST['credential_uri_2']);
} elseif (isset($credential_row) && isset($credential_row['credential_uri_2'])) {
    $uri_2 = mysqli_real_escape_string($mysqli, $credential_row['credential_uri_2']);
} else {
    $uri_2 = '';
}

if (isset($_POST['credential_username'])) {
    $username = $_POST['credential_username'];
    $username = apiEncryptCredentialEntry($username, $api_key_decrypt_hash, $api_key_decrypt_password);
} elseif (isset($credential_row) && isset($credential_row['credential_username'])) {
    $username = $credential_row['credential_username'];
} else {
    $username = '';
}

if (isset($_POST['credential_password'])) {
    $password = $_POST['credential_password'];
    $password = apiEncryptCredentialEntry($password, $api_key_decrypt_hash, $api_key_decrypt_password);
    $password_changed = true;
} elseif (isset($credential_row) && isset($credential_row['credential_password'])) {
    $password = $credential_row['credential_password'];
    $password_changed = false;
} else {
    $password = '';
    $password_changed = false;
}

if (isset($_POST['credential_otp_secret'])) {
    $otp_secret = escapeSql($_POST['credential_otp_secret']);
} elseif (isset($credential_row) && isset($credential_row['credential_otp_secret'])) {
    $otp_secret = mysqli_real_escape_string($mysqli, $credential_row['credential_otp_secret']);
} else {
    $otp_secret = '';
}

if (isset($_POST['credential_note'])) {
    $note = escapeSql($_POST['credential_note']);
} elseif (isset($credential_row) && isset($credential_row['credential_note'])) {
    $note = mysqli_real_escape_string($mysqli, $credential_row['credential_note']);
} else {
    $note = '';
}

if (isset($_POST['credential_favorite'])) {
    $favorite = intval($_POST['credential_favorite']);
} elseif (isset($credential_row) && isset($credential_row['credential_favorite'])) {
    $favorite = $credential_row['credential_favorite'];
} else {
    $favorite = 0;
}

if (isset($_POST['credential_contact_id'])) {
    $contact_id = intval($_POST['credential_contact_id']);
} elseif (isset($credential_row) && isset($credential_row['credential_contact_id'])) {
    $contact_id = $credential_row['credential_contact_id'];
} else {
    $contact_id = 0;
}

if (isset($_POST['credential_vendor_id'])) {
    $vendor_id = intval($_POST['credential_vendor_id']);
} elseif (isset($credential_row) && isset($credential_row['credential_vendor_id'])) {
    $vendor_id = $credential_row['credential_vendor_id'];
} else {
    $vendor_id = 0;
}

if (isset($_POST['credential_asset_id'])) {
    $asset_id = intval($_POST['credential_asset_id']);
} elseif (isset($credential_row) && isset($credential_row['credential_asset_id'])) {
    $asset_id = $credential_row['credential_asset_id'];
} else {
    $asset_id = 0;
}

if (isset($_POST['credential_software_id'])) {
    $software_id = intval($_POST['credential_software_id']);
} elseif (isset($credential_row) && isset($credential_row['credential_software_id'])) {
    $software_id = $credential_row['credential_software_id'];
} else {
    $software_id = 0;
}
