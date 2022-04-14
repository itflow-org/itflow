<?php
require('../validate_api_key.php');

require('../require_get_method.php');

// Asset via ID (single)
if(isset($_GET['asset_id'])){
    $id = intval($_GET['asset_id']);
    $sql = mysqli_query($mysqli, "SELECT * FROM assets WHERE asset_id = '$id' AND company_id = '$company_id'");
}

// Asset query via type
elseif(isset($_GET['asset_type'])){
    $type = mysqli_real_escape_string($mysqli,ucfirst($_GET['asset_type']));
    $sql = mysqli_query($mysqli, "SELECT * FROM assets WHERE asset_type = '$type' AND company_id = '$company_id' ORDER BY asset_id LIMIT $limit OFFSET $offset");
}

// Asset query via name
elseif(isset($_GET['asset_name'])){
    $name = mysqli_real_escape_string($mysqli,$_GET['asset_name']);
    $sql = mysqli_query($mysqli, "SELECT * FROM assets WHERE asset_name = '$name' AND company_id = '$company_id' ORDER BY asset_id LIMIT $limit OFFSET $offset");
}

// Asset query via serial
elseif(isset($_GET['asset_serial'])){
    $serial = mysqli_real_escape_string($mysqli,$_GET['asset_serial']);
    $sql = mysqli_query($mysqli, "SELECT * FROM assets WHERE asset_serial = '$serial' AND company_id = '$company_id' ORDER BY asset_id LIMIT $limit OFFSET $offset");
}

// Asset query via client ID
elseif(isset($_GET['asset_client_id'])){
    $client = intval($_GET['asset_client_id']);
    $sql = mysqli_query($mysqli, "SELECT * FROM assets WHERE asset_client_id = '$client' AND company_id = '$company_id' ORDER BY asset_id LIMIT $limit OFFSET $offset");
}

// All assets
else{
    $sql = mysqli_query($mysqli, "SELECT * FROM assets WHERE company_id = '$company_id' ORDER BY asset_id LIMIT $limit OFFSET $offset");
}

// Output
include("../read_output.php");