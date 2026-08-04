<?php
define('number_regex', '/[^0-9]/');

// Variable assignment from POST (or: blank/from DB is updating)
if (isset($_POST['contact_name'])) {
    $name = escapeSql($_POST['contact_name']);
} elseif ($contact_row) {
    $name = mysqli_real_escape_string($mysqli, $contact_row['contact_name']);
} else {
    $name = '';
}

if (isset($_POST['contact_title'])) {
    $title = escapeSql($_POST['contact_title']);
} elseif ($contact_row) {
    $title = mysqli_real_escape_string($mysqli, $contact_row['contact_title']);
} else {
    $title = '';
}

if (isset($_POST['contact_department'])) {
    $department = escapeSql($_POST['contact_department']);
} elseif ($contact_row) {
    $department = mysqli_real_escape_string($mysqli, $contact_row['contact_department']);
} else {
    $department = '';
}

if (isset($_POST['contact_email'])) {
    $email = escapeSql($_POST['contact_email']);
} elseif ($contact_row) {
    $email = mysqli_real_escape_string($mysqli, $contact_row['contact_email']);
} else {
    $email = '';
}

if (isset($_POST['contact_phone'])) {
    $phone = preg_replace(number_regex, '', $_POST['contact_phone']);
} elseif ($contact_row) {
    $phone = mysqli_real_escape_string($mysqli, $contact_row['contact_phone']);
} else {
    $phone = '';
}

if (isset($_POST['contact_extension'])) {
    $extension = preg_replace(number_regex, '', $_POST['contact_extension']);
} elseif ($contact_row) {
    $extension = mysqli_real_escape_string($mysqli, $contact_row['contact_extension']);
} else {
    $extension = '';
}

if (isset($_POST['contact_mobile'])) {
    $mobile = preg_replace(number_regex, '', $_POST['contact_mobile']);
} elseif ($contact_row) {
    $mobile = mysqli_real_escape_string($mysqli, $contact_row['contact_mobile']);
} else {
    $mobile = '';
}

if (isset($_POST['contact_notes'])) {
    $notes = escapeSql($_POST['contact_notes']);
} elseif ($contact_row) {
    $notes = mysqli_real_escape_string($mysqli, $contact_row['contact_notes']);
} else {
    $notes = '';
}

if (isset($_POST['contact_primary'])) {
    $primary = intval($_POST['contact_primary']);
} elseif ($contact_row) {
    $primary = $contact_row['contact_primary'];
} else {
    $primary = '0';
}

if (isset($_POST['contact_important'])) {
    $important = intval($_POST['contact_important']);
} elseif ($contact_row) {
    $important = $contact_row['contact_important'];
} else {
    $important = '0';
}

if (isset($_POST['contact_billing'])) {
    $billing = intval($_POST['contact_billing']);
} elseif ($contact_row) {
    $billing = $contact_row['contact_billing'];
} else {
    $billing = '0';
}

if (isset($_POST['contact_technical'])) {
    $technical = intval($_POST['contact_technical']);
} elseif ($contact_row) {
    $technical = $contact_row['contact_technical'];
} else {
    $technical = '0';
}

if (isset($_POST['contact_location_id'])) {
    $location_id = intval($_POST['contact_location_id']);
} elseif ($contact_row) {
    $location_id = $contact_row['contact_location_id'];
} else {
    $location_id = '0';
}
