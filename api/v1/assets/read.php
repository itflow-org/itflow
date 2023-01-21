<?php

require_once('../validate_api_key.php');
require_once('../require_get_method.php');

// Asset via ID (single)
if (isset($_GET['asset_id'])) {
    $id = intval($_GET['asset_id']);
    $sql = mysqli_query($mysqli, "SELECT * FROM assets WHERE asset_id = '$id' AND asset_client_id LIKE '$client_id' AND company_id = '$company_id'");
}

// Asset query via type
elseif (isset($_GET['asset_type'])) {
    $type = mysqli_real_escape_string($mysqli, ucfirst($_GET['asset_type']));
    $sql = mysqli_query($mysqli, "SELECT * FROM assets WHERE asset_type = '$type' AND asset_client_id LIKE '$client_id' AND company_id = '$company_id' ORDER BY asset_id LIMIT $limit OFFSET $offset");
}

// Asset query via name
elseif (isset($_GET['asset_name'])) {
    $name = mysqli_real_escape_string($mysqli, $_GET['asset_name']);
    $sql = mysqli_query($mysqli, "SELECT * FROM assets WHERE asset_name = '$name' AND asset_client_id LIKE '$client_id' AND company_id = '$company_id' ORDER BY asset_id LIMIT $limit OFFSET $offset");
}

// Asset query via serial
elseif (isset($_GET['asset_serial'])) {
    $serial = mysqli_real_escape_string($mysqli, $_GET['asset_serial']);
    $sql = mysqli_query($mysqli, "SELECT * FROM assets WHERE asset_serial = '$serial' AND asset_client_id LIKE '$client_id' AND company_id = '$company_id' ORDER BY asset_id LIMIT $limit OFFSET $offset");
}

// Asset query via client ID
elseif (isset($_GET['client_id']) && $client_id == "%") {
    $client_id = intval($_GET['client_id']);
    $sql = mysqli_query($mysqli, "SELECT * FROM assets WHERE asset_client_id LIKE '$client_id' AND company_id = '$company_id' ORDER BY asset_id LIMIT $limit OFFSET $offset");
}

// All assets
else {
    $sql = mysqli_query($mysqli, "SELECT * FROM assets WHERE asset_client_id LIKE '$client_id' AND company_id = '$company_id' ORDER BY asset_id LIMIT $limit OFFSET $offset");
}

// Output
require_once("../read_output.php");
