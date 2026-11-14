<?php

// Archive a product - it is no longer offered on new lines; lines it is on keep it

require_once '../validate_api_key.php';
require_once '../require_post_method.php';

// Parse ID
$product_id = intval($_POST['product_id'] ?? 0);

// Default
$update_count = false;

if (!empty($product_id)) {

    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT product_name FROM products WHERE product_id = $product_id AND product_archived_at IS NULL LIMIT 1"));

    if ($row) {

        $update_sql = mysqli_query($mysqli, "UPDATE products SET product_archived_at = NOW() WHERE product_id = $product_id LIMIT 1");

        if ($update_sql) {
            $update_count = mysqli_affected_rows($mysqli);

            $product_name = escapeSql($row['product_name']);
            logAudit("Product", "Archive", "$product_name archived via API ($api_key_name)", 0, $product_id);
            logAudit("API", "Success", "Archived product $product_name via API ($api_key_name)", 0, $product_id);
        }
    }
}

// Output
require_once '../update_output.php';
