<?php

require_once '../validate_api_key.php';

require_once '../require_get_method.php';


if (isset($_GET['location_id'])) {
    // Location via ID (single)
    $id = intval($_GET['location_id']);
    $sql = mysqli_query($mysqli, "SELECT * FROM locations WHERE location_id = '$id' AND location_client_id LIKE '$client_id'");

} else {
    // All locations (by client ID if given, or all in general if key permits)
    $sql = mysqli_query($mysqli, "SELECT * FROM locations WHERE location_client_id LIKE '$client_id' ORDER BY location_id LIMIT $limit OFFSET $offset");
}

// Output
require_once "../read_output.php";

