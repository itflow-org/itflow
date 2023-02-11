<?php

// Variable assignment from POST (or: blank/from DB is updating)
if (isset($_POST['asset_name'])) {
    $name = trim(strip_tags(mysqli_real_escape_string($mysqli, $_POST['asset_name'])));
} elseif ($asset_row) {
    $name = $asset_row['asset_name'];
} else {
    $name = '';
}

if (isset($_POST['asset_type'])) {
    $type = trim(strip_tags(mysqli_real_escape_string($mysqli, $_POST['asset_type'])));
} elseif ($asset_row) {
    $type = $asset_row['asset_type'];
} else {
    $type = '';
}

if (isset($_POST['asset_make'])) {
    $make = trim(strip_tags(mysqli_real_escape_string($mysqli, $_POST['asset_make'])));
} elseif ($asset_row) {
    $make = $asset_row['asset_make'];
} else {
    $make = '';
}
if (isset($_POST['asset_model'])) {
    $model = trim(strip_tags(mysqli_real_escape_string($mysqli, $_POST['asset_model'])));
} elseif ($asset_row) {
    $model = $asset_row['asset_model'];
} else {
    $model = '';
}

if (isset($_POST['asset_serial'])) {
    $serial = trim(strip_tags(mysqli_real_escape_string($mysqli, $_POST['asset_serial'])));
} elseif ($asset_row) {
    $serial = $asset_row['asset_serial'];
} else {
    $serial = '';
}

if (isset($_POST['asset_os'])) {
    $os = trim(strip_tags(mysqli_real_escape_string($mysqli, $_POST['asset_os'])));
} elseif ($asset_row) {
    $os = $asset_row['asset_os'];
} else {
    $os = '';
}

if (isset($_POST['asset_ip'])) {
    $aip = trim(strip_tags(mysqli_real_escape_string($mysqli, $_POST['asset_ip'])));
} elseif ($asset_row) {
    $aip = $asset_row['asset_ip'];
} else {
    $aip = '';
}

if (isset($_POST['asset_mac'])) {
    $mac = trim(strip_tags(mysqli_real_escape_string($mysqli, $_POST['asset_mac'])));
} elseif ($asset_row) {
    $mac = $asset_row['asset_mac'];
} else {
    $mac = '';
}

if (isset($_POST['asset_status'])) {
    $status = trim(strip_tags(mysqli_real_escape_string($mysqli, $_POST['asset_status'])));
} elseif ($asset_row) {
    $status = $asset_row['asset_status'];
} else {
    $status = '';
}

if (isset($_POST['asset_purchase_date']) && !empty($_POST['asset_purchase_date'])) {
    $purchase_date = trim(strip_tags(mysqli_real_escape_string($mysqli, $_POST['asset_purchase_date'])));
} elseif ($asset_row) {
    $purchase_date = $asset_row['asset_purchase_date'];
} else {
    $purchase_date = "0000-00-00";
}

if (isset($_POST['asset_warranty_expire']) && !empty($_POST['asset_warranty_expire'])) {
    $warranty_expire = trim(strip_tags(mysqli_real_escape_string($mysqli, $_POST['asset_warranty_expire'])));
} elseif ($asset_row) {
    $warranty_expire = $asset_row['asset_warranty_expire'];
} else {
    $warranty_expire = "0000-00-00";
}

if (isset($_POST['asset_install_date']) && !empty($_POST['asset_install_date'])) {
    $install_date = trim(strip_tags(mysqli_real_escape_string($mysqli, $_POST['asset_install_date'])));
} elseif ($asset_row) {
    $install_date = $asset_row['asset_install_date'];
} else {
    $install_date = "0000-00-00";
}

if (isset($_POST['asset_notes'])) {
    $notes = trim(strip_tags(mysqli_real_escape_string($mysqli, $_POST['asset_notes'])));
} elseif ($asset_row) {
    $notes = $asset_row['asset_notes'];
} else {
    $notes = '';
}

if (isset($_POST['asset_vendor_id'])) {
    $vendor = intval($_POST['asset_vendor_id']);
} elseif ($asset_row) {
    $vendor = $asset_row['asset_vendor_id'];
} else {
    $vendor = '0';
}

if (isset($_POST['asset_location_id'])) {
    $location = intval($_POST['asset_location_id']);
} elseif ($asset_row) {
    $location = $asset_row['asset_location_id'];
} else {
    $location = '0';
}

if (isset($_POST['asset_contact_id'])) {
    $contact = intval($_POST['asset_contact_id']);
} elseif ($asset_row) {
    $contact = $asset_row['asset_contact_id'];
} else {
    $contact = '0';
}

if (isset($_POST['asset_network_id'])) {
    $network = intval($_POST['asset_network_id']);
} elseif ($asset_row) {
    $network = $asset_row['asset_network_id'];
} else {
    $network = '0';
}
