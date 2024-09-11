<?php

require_once '../validate_api_key.php';

require_once '../require_get_method.php';

// Specific vendor via their ID (single)
if (isset($_GET['vendor_id'])) {
    $id = intval($_GET['vendor_id']);
    $sql = mysqli_query($mysqli, "SELECT * FROM vendors WHERE vendor_id = '$id' AND vendor_client_id LIKE '$client_id'");

} else {
    // All Vendors (by client ID or all in general if key permits)
    $sql = mysqli_query($mysqli, "SELECT * FROM vendors WHERE vendor_client_id LIKE '$client_id' ORDER BY vendor_id LIMIT $limit OFFSET $offset");
}

// Output
require_once "../read_output.php";

