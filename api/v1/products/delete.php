<?php

require_once '../validate_api_key.php';
require_once '../require_post_method.php';

// Parse ID
$product_id = intval($_POST['product_id'] ?? 0);

// Default
$delete_count = false;

if (!empty($product_id)) {

    // Archived first, as for the other records the API deletes
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT product_name FROM products WHERE product_id = $product_id AND product_archived_at IS NOT NULL LIMIT 1"));

    if ($row) {

        // Its stock and price history go; lines it is on keep their text and amounts (functions/products.php)
        productDelete($product_id);

        if (!mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM products WHERE product_id = $product_id"))) {
            $delete_count = 1;

            $product_name = escapeSql($row['product_name']);
            logAudit("Product", "Delete", "$product_name via API ($api_key_name)", 0, $product_id);
            logAudit("API", "Success", "Deleted product $product_name via API ($api_key_name)", 0);
        }
    }
}

// Output
require_once '../delete_output.php';
