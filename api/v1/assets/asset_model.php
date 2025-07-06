<?php

// Variable assignment from POST (or: blank/from DB is updating)
if (isset($_POST['asset_name'])) {
    $name = sanitizeInput($_POST['asset_name']);
} elseif (isset($asset_row) && isset($asset_row['asset_name'])) {
    $name = $asset_row['asset_name'];
} else {
    $name = '';
}

if (isset($_POST['asset_description'])) {
    $description = sanitizeInput($_POST['asset_description']);
} elseif (isset($asset_row) && isset($asset_row['asset_description'])) {
    $description = $asset_row['asset_description'];
} else {
    $description = '';
}

if (isset($_POST['asset_type'])) {
    $type = sanitizeInput($_POST['asset_type']);
} elseif (isset($asset_row) && isset($asset_row['asset_type'])) {
    $type = $asset_row['asset_type'];
} else {
    $type = '';
}

if (isset($_POST['asset_make'])) {
    $make = sanitizeInput($_POST['asset_make']);
} elseif (isset($asset_row) && isset($asset_row['asset_make'])) {
    $make = $asset_row['asset_make'];
} else {
    $make = '';
}
if (isset($_POST['asset_model'])) {
    $model = sanitizeInput($_POST['asset_model']);
} elseif (isset($asset_row) && isset($asset_row['asset_model'])) {
    $model = $asset_row['asset_model'];
} else {
    $model = '';
}

if (isset($_POST['asset_serial'])) {
    $serial = sanitizeInput($_POST['asset_serial']);
} elseif (isset($asset_row) && isset($asset_row['asset_serial'])) {
    $serial = $asset_row['asset_serial'];
} else {
    $serial = '';
}

if (isset($_POST['asset_os'])) {
    $os = sanitizeInput($_POST['asset_os']);
} elseif (isset($asset_row) && isset($asset_row['asset_os'])) {
    $os = $asset_row['asset_os'];
} else {
    $os = '';
}

if (isset($_POST['asset_ip'])) {
    $ip = sanitizeInput($_POST['asset_ip']);
} elseif (isset($asset_row) && isset($asset_row['interface_ip'])) {
    $ip = $asset_row['interface_ip'];
} else {
    $ip = '';
}

if (isset($_POST['asset_mac'])) {
    $mac = sanitizeInput($_POST['asset_mac']);
} elseif (isset($asset_row) && isset($asset_row['interface_mac'])) {
    $mac = $asset_row['interface_mac'];
} else {
    $mac = '';
}

if (isset($_POST['asset_uri'])) {
    $uri = sanitizeInput($_POST['asset_uri']);
} elseif (isset($asset_row) && isset($asset_row['asset_uri'])) {
    $uri = $asset_row['asset_uri'];
} else {
    $uri = '';
}

if (isset($_POST['asset_status'])) {
    $status = sanitizeInput($_POST['asset_status']);
} elseif (isset($asset_row) && isset($asset_row['asset_status'])) {
    $status = $asset_row['asset_status'];
} else {
    $status = '';
}

if (isset($_POST['asset_purchase_date']) && !empty($_POST['asset_purchase_date'])) {
    $purchase_date = "'" . sanitizeInput($_POST['asset_purchase_date']) . "'";
} elseif (isset($asset_row) && isset($asset_row['asset_purchase_date'])) {
    $purchase_date = "'" . $asset_row['asset_purchase_date'] . "'";
} else {
    $purchase_date = "NULL";
}

if (isset($_POST['asset_warranty_expire']) && !empty($_POST['asset_warranty_expire'])) {
    $warranty_expire = "'" . sanitizeInput($_POST['asset_warranty_expire']) . "'";
} elseif (isset($asset_row) && isset($asset_row['asset_warranty_expire'])) {
    $warranty_expire = "'" . $asset_row['asset_warranty_expire'] . "'";
} else {
    $warranty_expire = "NULL";
}

if (isset($_POST['asset_install_date']) && !empty($_POST['asset_install_date'])) {
    $install_date = "'" . sanitizeInput($_POST['asset_install_date']) . "'";
} elseif (isset($asset_row) && isset($asset_row['asset_install_date'])) {
    $install_date = "'" . $asset_row['asset_install_date'] . "'";
} else {
    $install_date = "NULL";
}

if (isset($_POST['asset_notes'])) {
    $notes = sanitizeInput($_POST['asset_notes']);
} elseif (isset($asset_row) && isset($asset_row['asset_notes'])) {
    $notes = $asset_row['asset_notes'];
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
