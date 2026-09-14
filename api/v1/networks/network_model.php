<?php

// Variable assignment from POST (or: blank/from DB is updating)

if (isset($_POST['network_name'])) {
    $name = escapeSql($_POST['network_name']);
} elseif ($network_row) {
    $name = mysqli_real_escape_string($mysqli, $network_row['network_name']);
} else {
    $name = '';
}

if (isset($_POST['network_description'])) {
    $description = escapeSql($_POST['network_description']);
} elseif ($network_row) {
    $description = mysqli_real_escape_string($mysqli, $network_row['network_description']);
} else {
    $description = '';
}

if (isset($_POST['network'])) {
    $network = escapeSql($_POST['network']);
} elseif ($network_row) {
    $network = mysqli_real_escape_string($mysqli, $network_row['network']);
} else {
    $network = '';
}

if (isset($_POST['network_vlan'])) {
    $vlan = intval($_POST['network_vlan']);
} elseif ($network_row) {
    $vlan = intval($network_row['network_vlan']);
} else {
    $vlan = 0;
}

if (isset($_POST['network_gateway'])) {
    $gateway = escapeSql($_POST['network_gateway']);
} elseif ($network_row) {
    $gateway = mysqli_real_escape_string($mysqli, $network_row['network_gateway']);
} else {
    $gateway = '';
}

if (isset($_POST['network_primary_dns'])) {
    $primary_dns = escapeSql($_POST['network_primary_dns']);
} elseif ($network_row) {
    $primary_dns = mysqli_real_escape_string($mysqli, $network_row['network_primary_dns']);
} else {
    $primary_dns = '';
}

if (isset($_POST['network_secondary_dns'])) {
    $secondary_dns = escapeSql($_POST['network_secondary_dns']);
} elseif ($network_row) {
    $secondary_dns = mysqli_real_escape_string($mysqli, $network_row['network_secondary_dns']);
} else {
    $secondary_dns = '';
}

if (isset($_POST['network_dhcp_range'])) {
    $dhcp_range = escapeSql($_POST['network_dhcp_range']);
} elseif ($network_row) {
    $dhcp_range = mysqli_real_escape_string($mysqli, $network_row['network_dhcp_range']);
} else {
    $dhcp_range = '';
}

if (isset($_POST['network_notes'])) {
    $notes = escapeSql($_POST['network_notes']);
} elseif ($network_row) {
    $notes = mysqli_real_escape_string($mysqli, $network_row['network_notes']);
} else {
    $notes = '';
}

if (isset($_POST['network_location_id'])) {
    $location_id = intval($_POST['network_location_id']);
} elseif ($network_row) {
    $location_id = intval($network_row['network_location_id']);
} else {
    $location_id = 0;
}