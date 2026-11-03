<?php

require_once '../validate_api_key.php';

require_once '../require_post_method.php';

// Parse ID
$product_id = intval($_POST['product_id'] ?? 0);

// Default
$update_count = false;

if (!empty($product_id)) {

    $product_row = productRow($product_id);

    // Variable assignment from POST - only supplied fields are changed
    require_once 'product_model.php';

    if ($product_row) {

        if ($product_model_error !== '') {
            http_response_code(400);
            $return_arr['success'] = "False";
            $return_arr['message'] = $product_model_error;
            echo json_encode($return_arr);
            exit();
        }

        if (!$product_input) {
            http_response_code(400);
            $return_arr['success'] = "False";
            $return_arr['message'] = 'Provide at least one product field to update.';
            echo json_encode($return_arr);
            exit();
        }

        if (!productSave($product_input, $product_id, $error)) {
            http_response_code(400);
            $return_arr['success'] = "False";
            $return_arr['message'] = $error;
            echo json_encode($return_arr);
            exit();
        }

        // Check update & get the updated product
        if (!mysqli_errno($mysqli)) {
            $product_row = productRow($product_id);
            $product_name = escapeSql($product_row['product_name']);
            $update_count = 1;

            // Logging
            logAudit("Product", "Edit", "$product_name via API ($api_key_name)", 0, $product_id);
            logAudit("API", "Success", "Edited product $product_name via API ($api_key_name)", 0, $product_id);
        }
    }
}

// Output
require_once '../update_output.php';
