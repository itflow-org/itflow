<?php

// Variable assignment from POST (or: blank/from DB is updating)

if (isset($_POST['location_name'])) {
    $name = escapeSql($_POST['location_name']);
} elseif ($location_row) {
    $name = mysqli_real_escape_string($mysqli, $location_row['location_name']);
} else {
    $name = '';
}

if (isset($_POST['location_description'])) {
    $description = escapeSql($_POST['location_description']);
} elseif ($location_row) {
    $description = mysqli_real_escape_string($mysqli, $location_row['location_description']);
} else {
    $description = '';
}

if (isset($_POST['location_country'])) {
    $country = escapeSql($_POST['location_country']);
} elseif ($location_row) {
    $country = mysqli_real_escape_string($mysqli, $location_row['location_country']);
} else {
    $country = '';
}

if (isset($_POST['location_address'])) {
    $address = escapeSql($_POST['location_address']);
} elseif ($location_row) {
    $address = mysqli_real_escape_string($mysqli, $location_row['location_address']);
} else {
    $address = '';
}

if (isset($_POST['location_city'])) {
    $city = escapeSql($_POST['location_city']);
} elseif ($location_row) {
    $city = mysqli_real_escape_string($mysqli, $location_row['location_city']);
} else {
    $city = '';
}

if (isset($_POST['location_state'])) {
    $state = escapeSql($_POST['location_state']);
} elseif ($location_row) {
    $state = mysqli_real_escape_string($mysqli, $location_row['location_state']);
} else {
    $state = '';
}

if (isset($_POST['location_zip'])) {
    $zip = escapeSql($_POST['location_zip']);
} elseif ($location_row) {
    $zip = mysqli_real_escape_string($mysqli, $location_row['location_zip']);
} else {
    $zip = '';
}

if (isset($_POST['location_hours'])) {
    $hours = escapeSql($_POST['location_hours']);
} elseif ($location_row) {
    $hours = mysqli_real_escape_string($mysqli, $location_row['location_hours']);
} else {
    $hours = '';
}

if (isset($_POST['location_notes'])) {
    $notes = escapeSql($_POST['location_notes']);
} elseif ($location_row) {
    $notes = mysqli_real_escape_string($mysqli, $location_row['location_notes']);
} else {
    $notes = '';
}

if (isset($_POST['location_primary'])) {
    $primary = intval($_POST['location_primary']);
} elseif ($location_row) {
    $primary = $location_row['location_primary'];
} else {
    $primary = '0';
}
