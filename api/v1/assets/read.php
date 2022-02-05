<?php
require('../validate_api_key.php');

if($_SERVER['REQUEST_METHOD'] !== "GET"){
    header("HTTP/1.1 405 Method Not Allowed");
    $return_arr['success'] = "False";
    $return_arr['message'] = "Can only send GET requests to this endpoint.";
    echo json_encode($return_arr);
    exit();
}

// Specific asset query via ID
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
elseif(isset($_GET['name'])){
    $name = mysqli_real_escape_string($mysqli,$_GET['name']);
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

// All asset query
else{
    $sql = mysqli_query($mysqli, "SELECT * FROM assets WHERE company_id = '$company_id' ORDER BY asset_id LIMIT $limit OFFSET $offset");
}


// Output
if($sql && mysqli_num_rows($sql) > 0){
    $return_arr['success'] = "True";
    $return_arr['count'] = mysqli_num_rows($sql);

    $row = array();
    while($row = mysqli_fetch_array($sql)){
        $return_arr['data'][] = $row;
    }

    echo json_encode($return_arr);
    exit();
}
else{
    $return_arr['success'] = "False";
    $return_arr['message'] = "No asset(s) (with that ID) for this company";
    echo json_encode($return_arr);
    exit();
}