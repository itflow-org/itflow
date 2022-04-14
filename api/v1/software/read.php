<?php
require('../validate_api_key.php');

require('../require_get_method.php');

// Specific software via ID (single)
if(isset($_GET['software_id'])){
    $id = intval($_GET['software_id']);
    $sql = mysqli_query($mysqli, "SELECT * FROM software WHERE software_id = '$id' AND company_id = '$company_id'");
}

// Specific software via License ID
if(isset($_GET['software_license'])){
    $license = mysqli_real_escape_string($mysqli,$_GET['software_license']);
    $sql = mysqli_query($mysqli, "SELECT * FROM software WHERE software_license = '$license' AND company_id = '$company_id' ORDER BY software_id LIMIT $limit OFFSET $offset");
}

// Software by name
elseif(isset($_GET['software_name'])){
    $name = mysqli_real_escape_string($mysqli,$_GET['software_name']);
    $sql = mysqli_query($mysqli, "SELECT * FROM software WHERE software_name = '$name' AND company_id = '$company_id' ORDER BY asset_id LIMIT $limit OFFSET $offset");
}

// Software via type
elseif(isset($_GET['software_type'])){
    $type = intval($_GET['software_type']);
    $sql = mysqli_query($mysqli, "SELECT * FROM software WHERE software_type = '$type' AND company_id = '$company_id' ORDER BY software_id LIMIT $limit OFFSET $offset");
}

// Software via client ID
elseif(isset($_GET['software_client_id'])){
    $client = intval($_GET['software_client_id']);
    $sql = mysqli_query($mysqli, "SELECT * FROM software WHERE software_client_id = '$client' AND company_id = '$company_id' ORDER BY software_id LIMIT $limit OFFSET $offset");
}

// All software(s)
else{
    $sql = mysqli_query($mysqli, "SELECT * FROM software WHERE company_id = '$company_id' ORDER BY software_id LIMIT $limit OFFSET $offset");
}

// Output
include("../read_output.php");