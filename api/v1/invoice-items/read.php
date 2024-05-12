<?php

require_once '../validate_api_key.php';

require_once '../require_get_method.php';


if (isset($_GET['invoice_id'])) {
    // Invoice items via ID (single)

    $id = intval($_GET['invoice_id']);
    $sql = mysqli_query($mysqli, "SELECT * FROM invoice_items WHERE item_invoice_id = '$id'");

} else {
    // All invoices items

    $sql = mysqli_query($mysqli, "SELECT * FROM invoice_items ORDER BY item_id LIMIT $limit OFFSET $offset");
}

// Output
require_once "../read_output.php";