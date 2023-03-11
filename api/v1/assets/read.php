<?php

require_once('../validate_api_key.php');
require_once('../require_get_method.php');

// Asset via ID (single)
if (isset($_GET['asset_id'])) {
    $id = intval($_GET['asset_id']);
    $sql = mysqli_query($mysqli, "SELECT * FROM assets WHERE asset_id = '$id' AND asset_client_id LIKE '$client_id'");

} elseif (isset($_GET['asset_type'])) {
    // Asset query via type

    $type = mysqli_real_escape_string($mysqli, ucfirst($_GET['asset_type']));
    $sql = mysqli_query($mysqli, "SELECT * FROM assets WHERE asset_type = '$type' AND asset_client_id LIKE '$client_id' ORDER BY asset_id LIMIT $limit OFFSET $offset");

} elseif (isset($_GET['asset_name'])) {
    // Asset query via name

    $name = mysqli_real_escape_string($mysqli, $_GET['asset_name']);
    $sql = mysqli_query($mysqli, "SELECT * FROM assets WHERE asset_name = '$name' AND asset_client_id LIKE '$client_id' ORDER BY asset_id LIMIT $limit OFFSET $offset");

} elseif (isset($_GET['asset_serial'])) {
    // Asset query via serial

    $serial = mysqli_real_escape_string($mysqli, $_GET['asset_serial']);
    $sql = mysqli_query($mysqli, "SELECT * FROM assets WHERE asset_serial = '$serial' AND asset_client_id LIKE '$client_id' ORDER BY asset_id LIMIT $limit OFFSET $offset");

} elseif (isset($_GET['client_id'])) {
    // Asset query via client ID

    $sql = mysqli_query($mysqli, "SELECT * FROM assets WHERE asset_client_id LIKE '$client_id' ORDER BY asset_id LIMIT $limit OFFSET $offset");

} elseif (isset($_GET['asset_mac'])) {
    // Asset query via mac

    $mac = mysqli_real_escape_string($mysqli, $_GET['asset_mac']);
    $sql = mysqli_query($mysqli, "SELECT * FROM assets WHERE asset_mac = '$mac' AND asset_client_id LIKE '$client_id' ORDER BY asset_id LIMIT $limit OFFSET $offset");

}
// All assets
else {
    $sql = mysqli_query($mysqli, "SELECT * FROM assets WHERE asset_client_id LIKE '$client_id' ORDER BY asset_id LIMIT $limit OFFSET $offset");
}

// Output
require_once("../read_output.php");