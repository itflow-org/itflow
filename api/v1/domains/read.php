<?php

require_once '../validate_api_key.php';

require_once '../require_get_method.php';


// Specific domain via ID (single)
if (isset($_GET['domain_id'])) {
    $id = intval($_GET['domain_id']);
    $sql = mysqli_query($mysqli, "SELECT * FROM domains WHERE domain_id = '$id' AND domain_client_id LIKE '$client_id'");

} elseif (isset($_GET['domain_name'])) {
    // Domain by name
    $name = mysqli_real_escape_string($mysqli, $_GET['domain_name']);
    $sql = mysqli_query($mysqli, "SELECT * FROM domains WHERE domain_name = '$name' AND domain_client_id LIKE '$client_id' ORDER BY asset_id LIMIT $limit OFFSET $offset");

} else {
    // All domains (by client ID or all in general if key permits)
    $sql = mysqli_query($mysqli, "SELECT * FROM domains WHERE domain_client_id LIKE '$client_id' ORDER BY domain_id LIMIT $limit OFFSET $offset");
}

// Output
require_once "../read_output.php";

