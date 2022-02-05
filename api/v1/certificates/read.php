<?php
require('../validate_api_key.php');

if($_SERVER['REQUEST_METHOD'] !== "GET"){
    header("HTTP/1.1 405 Method Not Allowed");
    $return_arr['success'] = "False";
    $return_arr['message'] = "Can only send GET requests to this endpoint.";
    echo json_encode($return_arr);
    exit();
}

// Specific certificate via ID (single)
if(isset($_GET['certificate_id'])){
    $id = intval($_GET['certificate_id']);
    $sql = mysqli_query($mysqli, "SELECT * FROM certificates WHERE certificate_id = '$id' AND company_id = '$company_id'");
}

// Certificate by name
elseif(isset($_GET['certificate_name'])){
    $name = mysqli_real_escape_string($mysqli,$_GET['certificate_name']);
    $sql = mysqli_query($mysqli, "SELECT * FROM certificates WHERE certificate_name = '$name' AND company_id = '$company_id' ORDER BY certificate_id LIMIT $limit OFFSET $offset");
}

// Certificate via client ID
elseif(isset($_GET['certificate_client_id'])){
    $client = intval($_GET['certificate_client_id']);
    $sql = mysqli_query($mysqli, "SELECT * FROM certificates WHERE certificate_client_id = '$client' AND company_id = '$company_id' ORDER BY certificate_id LIMIT $limit OFFSET $offset");
}

// All certificates
else{
    $sql = mysqli_query($mysqli, "SELECT * FROM certificates WHERE company_id = '$company_id' ORDER BY certificate_id LIMIT $limit OFFSET $offset");
}

// Output
include("../read_output.php");