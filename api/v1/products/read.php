<?php

require_once '../validate_api_key.php';

require_once '../require_get_method.php';


// Products aren't stored against client IDs, so we instead validate the API key is for All Clients

if (isset($_GET['product_id']) && $client_id == "%") {
    // product via ID (single)
    $id = intval($_GET['product_id']);
    $sql = mysqli_query($mysqli, "SELECT * FROM products WHERE product_id = '$id'");

} elseif ($client_id == "%") {
    // All products
    $sql = mysqli_query($mysqli, "SELECT * FROM products ORDER BY product_id LIMIT $limit OFFSET $offset");
}

// Output
require_once "../read_output.php";

