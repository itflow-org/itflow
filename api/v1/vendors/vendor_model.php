<?php

// Variable assignment from POST (or: blank/from DB is updating)

if (isset($_POST['vendor_name'])) {
    $name = escapeSql($_POST['vendor_name']);
} elseif ($vendor_row) {
    $name = mysqli_real_escape_string($mysqli, $vendor_row['vendor_name']);
} else {
    $name = '';
}

if (isset($_POST['vendor_description'])) {
    $description = escapeSql($_POST['vendor_description']);
} elseif ($vendor_row) {
    $description = mysqli_real_escape_string($mysqli, $vendor_row['vendor_description']);
} else {
    $description = '';
}

if (isset($_POST['vendor_account_number'])) {
    $account_number = escapeSql($_POST['vendor_account_number']);
} elseif ($vendor_row) {
    $account_number = mysqli_real_escape_string($mysqli, $vendor_row['vendor_account_number']);
} else {
    $account_number = '';
}

if (isset($_POST['vendor_contact_name'])) {
    $contact_name = escapeSql($_POST['vendor_contact_name']);
} elseif ($vendor_row) {
    $contact_name = mysqli_real_escape_string($mysqli, $vendor_row['vendor_contact_name']);
} else {
    $contact_name = '';
}

if (isset($_POST['vendor_phone_country_code'])) {
    $phone_country_code = preg_replace("/[^0-9]/", '', $_POST['vendor_phone_country_code']);
} elseif ($vendor_row) {
    $phone_country_code = mysqli_real_escape_string($mysqli, $vendor_row['vendor_phone_country_code']);
} else {
    $phone_country_code = '';
}

if (isset($_POST['vendor_phone'])) {
    $phone = preg_replace("/[^0-9]/", '', $_POST['vendor_phone']);
} elseif ($vendor_row) {
    $phone = mysqli_real_escape_string($mysqli, $vendor_row['vendor_phone']);
} else {
    $phone = '';
}

if (isset($_POST['vendor_extension'])) {
    $extension = preg_replace("/[^0-9]/", '', $_POST['vendor_extension']);
} elseif ($vendor_row) {
    $extension = mysqli_real_escape_string($mysqli, $vendor_row['vendor_extension']);
} else {
    $extension = '';
}

if (isset($_POST['vendor_email'])) {
    $email = escapeSql($_POST['vendor_email']);
} elseif ($vendor_row) {
    $email = mysqli_real_escape_string($mysqli, $vendor_row['vendor_email']);
} else {
    $email = '';
}

if (isset($_POST['vendor_website'])) {
    $website = preg_replace("(^https?://)", "", escapeSql($_POST['vendor_website']));
} elseif ($vendor_row) {
    $website = mysqli_real_escape_string($mysqli, $vendor_row['vendor_website']);
} else {
    $website = '';
}

if (isset($_POST['vendor_hours'])) {
    $hours = escapeSql($_POST['vendor_hours']);
} elseif ($vendor_row) {
    $hours = mysqli_real_escape_string($mysqli, $vendor_row['vendor_hours']);
} else {
    $hours = '';
}

if (isset($_POST['vendor_sla'])) {
    $sla = escapeSql($_POST['vendor_sla']);
} elseif ($vendor_row) {
    $sla = mysqli_real_escape_string($mysqli, $vendor_row['vendor_sla']);
} else {
    $sla = '';
}

if (isset($_POST['vendor_code'])) {
    $code = escapeSql($_POST['vendor_code']);
} elseif ($vendor_row) {
    $code = mysqli_real_escape_string($mysqli, $vendor_row['vendor_code']);
} else {
    $code = '';
}

if (isset($_POST['vendor_notes'])) {
    $notes = escapeSql($_POST['vendor_notes']);
} elseif ($vendor_row) {
    $notes = mysqli_real_escape_string($mysqli, $vendor_row['vendor_notes']);
} else {
    $notes = '';
}