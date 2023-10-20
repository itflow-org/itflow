<?php

require_once '../validate_api_key.php';

require_once '../require_get_method.php';


// Payments aren't stored against client IDs, so we instead validate the API key is for All Clients


if (isset($_GET['payment_id']) && $client_id == "%") {
    // Payment via ID (single)

    $id = intval($_GET['payment_id']);
    $sql = mysqli_query($mysqli, "SELECT * FROM payments WHERE payment_id = '$id'");

} elseif (isset($_GET['payment_invoice_id']) && $client_id == "%") {
    // Payments for an invoice

    $id = intval($_GET['payment_invoice_id']);
    $sql = mysqli_query($mysqli, "SELECT * FROM payments WHERE payment_invoice_id = '$id'");

} elseif ($client_id == "%") {
    // All payments

    $sql = mysqli_query($mysqli, "SELECT * FROM payments ORDER BY payment_id LIMIT $limit OFFSET $offset");
}

// Output
require_once "../read_output.php";

