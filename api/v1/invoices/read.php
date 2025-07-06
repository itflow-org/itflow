<?php

require_once '../validate_api_key.php';

require_once '../require_get_method.php';


if (isset($_GET['invoice_id'])) {
    // Invoice via ID (single)
    $id = intval($_GET['invoice_id']);
    $sql = mysqli_query($mysqli, "SELECT * FROM invoices WHERE invoice_id = '$id' AND invoice_client_id LIKE '$client_id'");

} else {
    // All invoices (by client ID if given, or all in general if key permits)
    $sql = mysqli_query($mysqli, "SELECT * FROM invoices WHERE invoice_client_id LIKE '$client_id' ORDER BY invoice_id LIMIT $limit OFFSET $offset");
}

// Output
require_once "../read_output.php";

