<?php

require_once '../validate_api_key.php';

require_once '../require_get_method.php';


// Products aren't client-scoped; access is gated by module_sales in enforce_api_rbac.php

if (isset($_GET['product_id'])) {
    // product via ID (single)
    $id = intval($_GET['product_id']);
    $sql = mysqli_query($mysqli, "SELECT * FROM products WHERE product_id = '$id'");

} else {
    // All products
    $sql = mysqli_query($mysqli, "SELECT * FROM products ORDER BY product_id LIMIT $limit OFFSET $offset");
}

// Output
require_once "../read_output.php";

