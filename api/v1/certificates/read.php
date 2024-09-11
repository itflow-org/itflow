<?php

require_once '../validate_api_key.php';

require_once '../require_get_method.php';


// Specific certificate via ID (single)
if (isset($_GET['certificate_id'])) {
    $id = intval($_GET['certificate_id']);
    $sql = mysqli_query($mysqli, "SELECT * FROM certificates WHERE certificate_id = '$id' AND certificate_client_id LIKE '$client_id'");

} elseif (isset($_GET['certificate_name'])) {
    // Certificate by name
    $name = mysqli_real_escape_string($mysqli, $_GET['certificate_name']);
    $sql = mysqli_query($mysqli, "SELECT * FROM certificates WHERE certificate_name = '$name' AND certificate_client_id LIKE '$client_id' ORDER BY certificate_id LIMIT $limit OFFSET $offset");

} else {
    // All certificates (by client ID or all in general if key permits)
    $sql = mysqli_query($mysqli, "SELECT * FROM certificates WHERE certificate_client_id LIKE '$client_id' ORDER BY certificate_id LIMIT $limit OFFSET $offset");
}

// Output
require_once "../read_output.php";

