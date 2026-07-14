<?php

// Variable assignment from POST (or: blank/from DB is updating)
if (isset($_POST['asset_name'])) {
    $name = escapeSql($_POST['asset_name']);
} elseif (isset($asset_row) && isset($asset_row['asset_name'])) {
    $name = mysqli_real_escape_string($mysqli, $asset_row['asset_name']);
} else {
    $name = '';
}

if (isset($_POST['asset_description'])) {
    $description = escapeSql($_POST['asset_description']);
} elseif (isset($asset_row) && isset($asset_row['asset_description'])) {
    $description = mysqli_real_escape_string($mysqli, $asset_row['asset_description']);
} else {
    $description = '';
}

if (isset($_POST['asset_type'])) {
    $type = escapeSql($_POST['asset_type']);
} elseif (isset($asset_row) && isset($asset_row['asset_type'])) {
    $type = mysqli_real_escape_string($mysqli, $asset_row['asset_type']);
} else {
    $type = '';
}

if (isset($_POST['asset_make'])) {
    $make = escapeSql($_POST['asset_make']);
} elseif (isset($asset_row) && isset($asset_row['asset_make'])) {
    $make = mysqli_real_escape_string($mysqli, $asset_row['asset_make']);
} else {
    $make = '';
}
if (isset($_POST['asset_model'])) {
    $model = escapeSql($_POST['asset_model']);
} elseif (isset($asset_row) && isset($asset_row['asset_model'])) {
    $model = mysqli_real_escape_string($mysqli, $asset_row['asset_model']);
} else {
    $model = '';
}

if (isset($_POST['asset_serial'])) {
    $serial = escapeSql($_POST['asset_serial']);
} elseif (isset($asset_row) && isset($asset_row['asset_serial'])) {
    $serial = mysqli_real_escape_string($mysqli, $asset_row['asset_serial']);
} else {
    $serial = '';
}

if (isset($_POST['asset_os'])) {
    $os = escapeSql($_POST['asset_os']);
} elseif (isset($asset_row) && isset($asset_row['asset_os'])) {
    $os = mysqli_real_escape_string($mysqli, $asset_row['asset_os']);
} else {
    $os = '';
}

if (isset($_POST['asset_ip'])) {
    $ip = escapeSql($_POST['asset_ip']);
} elseif (isset($asset_row) && isset($asset_row['interface_ip'])) {
    $ip = mysqli_real_escape_string($mysqli, $asset_row['interface_ip']);
} else {
    $ip = '';
}

if (isset($_POST['asset_mac'])) {
    $mac = escapeSql($_POST['asset_mac']);
} elseif (isset($asset_row) && isset($asset_row['interface_mac'])) {
    $mac = mysqli_real_escape_string($mysqli, $asset_row['interface_mac']);
} else {
    $mac = '';
}

if (isset($_POST['asset_uri'])) {
    $uri = escapeSql($_POST['asset_uri']);
} elseif (isset($asset_row) && isset($asset_row['asset_uri'])) {
    $uri = mysqli_real_escape_string($mysqli, $asset_row['asset_uri']);
} else {
    $uri = '';
}

if (isset($_POST['asset_uri_2'])) {
    $uri_2 = escapeSql($_POST['asset_uri_2']);
} elseif (isset($asset_row) && isset($asset_row['asset_uri_2'])) {
    $uri_2 = mysqli_real_escape_string($mysqli, $asset_row['asset_uri_2']);
} else {
    $uri_2 = '';
}

if (isset($_POST['asset_status'])) {
    $status = escapeSql($_POST['asset_status']);
} elseif (isset($asset_row) && isset($asset_row['asset_status'])) {
    $status = mysqli_real_escape_string($mysqli, $asset_row['asset_status']);
} else {
    $status = '';
}

if (isset($_POST['asset_purchase_date']) && !empty($_POST['asset_purchase_date'])) {
    $purchase_date = "'" . escapeSql($_POST['asset_purchase_date']) . "'";
} elseif (isset($asset_row) && isset($asset_row['asset_purchase_date'])) {
    $purchase_date = "'" . mysqli_real_escape_string($mysqli, $asset_row['asset_purchase_date']) . "'";
} else {
    $purchase_date = "NULL";
}

if (isset($_POST['asset_warranty_expire']) && !empty($_POST['asset_warranty_expire'])) {
    $warranty_expire = "'" . escapeSql($_POST['asset_warranty_expire']) . "'";
} elseif (isset($asset_row) && isset($asset_row['asset_warranty_expire'])) {
    $warranty_expire = "'" . mysqli_real_escape_string($mysqli, $asset_row['asset_warranty_expire']) . "'";
} else {
    $warranty_expire = "NULL";
}

if (isset($_POST['asset_install_date']) && !empty($_POST['asset_install_date'])) {
    $install_date = "'" . escapeSql($_POST['asset_install_date']) . "'";
} elseif (isset($asset_row) && isset($asset_row['asset_install_date'])) {
    $install_date = "'" . mysqli_real_escape_string($mysqli, $asset_row['asset_install_date']) . "'";
} else {
    $install_date = "NULL";
}

if (isset($_POST['asset_notes'])) {
    $notes = escapeSql($_POST['asset_notes']);
} elseif (isset($asset_row) && isset($asset_row['asset_notes'])) {
    $notes = mysqli_real_escape_string($mysqli, $asset_row['asset_notes']);
} else {
    $notes = '';
}

if (isset($_POST['asset_vendor_id'])) {
    $vendor = intval($_POST['asset_vendor_id']);
} elseif (isset($asset_row) && isset($asset_row['asset_vendor_id'])) {
    $vendor = $asset_row['asset_vendor_id'];
} else {
    $vendor = '0';
}

if (isset($_POST['asset_location_id'])) {
    $location = intval($_POST['asset_location_id']);
} elseif (isset($asset_row) && isset($asset_row['asset_location_id'])) {
    $location = $asset_row['asset_location_id'];
} else {
    $location = '0';
}

if (isset($_POST['asset_contact_id'])) {
    $contact = intval($_POST['asset_contact_id']);
} elseif (isset($asset_row) && isset($asset_row['asset_contact_id'])) {
    $contact = $asset_row['asset_contact_id'];
} else {
    $contact = '0';
}

if (isset($_POST['asset_network_id'])) {
    $network = intval($_POST['asset_network_id']);
} elseif (isset($asset_row) && isset($asset_row['interface_network_id'])) {
    $network = $asset_row['interface_network_id'];
} else {
    $network = '0';
}
