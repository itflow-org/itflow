<?php

require_once '../validate_api_key.php';

require_once '../require_get_method.php';

// Specific client via name (single)
if (isset($_GET['client_name'])) {
    $name = mysqli_real_escape_string($mysqli, $_GET['client_name']);
    $sql = mysqli_query($mysqli, "SELECT * FROM clients WHERE client_name = '$name' AND client_id LIKE '$client_id'");

} else {
    // All clients (by client ID if given, or all in general if key permits)
    $sql = mysqli_query($mysqli, "SELECT * FROM clients WHERE client_id LIKE '$client_id' ORDER BY client_id LIMIT $limit OFFSET $offset");
}

// Output
require_once "../read_output.php";

