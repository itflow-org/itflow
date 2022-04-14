<?php
require('../validate_api_key.php');

require('../require_get_method.php');

// Specific network via ID (single)
if(isset($_GET['network_id'])){
    $id = intval($_GET['network_id']);
    $sql = mysqli_query($mysqli, "SELECT * FROM networks WHERE network_id = '$id' AND company_id = '$company_id'");
}

// Network by name
elseif(isset($_GET['network_name'])){
    $name = mysqli_real_escape_string($mysqli,$_GET['network_name']);
    $sql = mysqli_query($mysqli, "SELECT * FROM networks WHERE network_name = '$name' AND company_id = '$company_id' ORDER BY network_id LIMIT $limit OFFSET $offset");
}

// Network via client ID
elseif(isset($_GET['network_client_id'])){
    $client = intval($_GET['network_client_id']);
    $sql = mysqli_query($mysqli, "SELECT * FROM networks WHERE network_client_id = '$client' AND company_id = '$company_id' ORDER BY network_id LIMIT $limit OFFSET $offset");
}

// All networks
else{
    $sql = mysqli_query($mysqli, "SELECT * FROM networks WHERE company_id = '$company_id' ORDER BY network_id LIMIT $limit OFFSET $offset");
}

// Output
include("../read_output.php");