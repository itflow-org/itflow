<?php

require_once('../validate_api_key.php');
require_once('../require_get_method.php');

if (isset($_GET['invoice_id'])) {
    // Invoice via ID (single)

    $id = intval($_GET['invoice_id']);
    $sql = mysqli_query($mysqli, "SELECT * FROM invoices WHERE invoice_id = '$id' AND invoice_client_id LIKE '$client_id' AND company_id = '$company_id'");

} else {
    // All invoices

    $sql = mysqli_query($mysqli, "SELECT * FROM invoices WHERE invoice_client_id LIKE '$client_id' AND company_id = '$company_id' ORDER BY invoice_id LIMIT $limit OFFSET $offset");
}

// Output
require_once("../read_output.php");
