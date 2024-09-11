<?php

require_once '../validate_api_key.php';

require_once '../require_get_method.php';


// Specific software via ID (single)
if (isset($_GET['software_id'])) {
    $id = intval($_GET['software_id']);
    $sql = mysqli_query($mysqli, "SELECT * FROM software WHERE software_id = '$id' AND software_client_id LIKE '$client_id'");

} elseif (isset($_GET['software_key'])) {
    // Specific software via key
    $key = mysqli_real_escape_string($mysqli, $_GET['software_license']);
    $sql = mysqli_query($mysqli, "SELECT * FROM software WHERE software_key = '$key' AND software_client_id LIKE '$client_id' ORDER BY software_id LIMIT $limit OFFSET $offset");

} elseif (isset($_GET['software_name'])) {
    // Software by name
    $name = mysqli_real_escape_string($mysqli, $_GET['software_name']);
    $sql = mysqli_query($mysqli, "SELECT * FROM software WHERE software_name = '$name' AND software_client_id LIKE '$client_id' ORDER BY asset_id LIMIT $limit OFFSET $offset");

} elseif (isset($_GET['software_type'])) {
    // Software via type
    $type = intval($_GET['software_type']);
    $sql = mysqli_query($mysqli, "SELECT * FROM software WHERE software_type = '$type' AND software_client_id LIKE '$client_id' ORDER BY software_id LIMIT $limit OFFSET $offset");

} else {
    // All software(s) (by client ID if given, or all in general if key permits)
    $sql = mysqli_query($mysqli, "SELECT * FROM software WHERE software_client_id LIKE '$client_id' ORDER BY software_id LIMIT $limit OFFSET $offset");
}

// Output
require_once "../read_output.php";

