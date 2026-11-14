<?php

require_once '../validate_api_key.php';
require_once '../require_post_method.php';

/*
 * Create a product or service for the catalog - the same rules as the New
 * Product form (productSave()). product_name is needed; what isn't sent takes
 * the form's defaults (a service, priced at 0).
 */

$insert_id = false;

$product_row = null;
require_once 'product_model.php';

if ($product_model_error !== '') {
    http_response_code(400);
    $return_arr['success'] = "False";
    $return_arr['message'] = $product_model_error;
    echo json_encode($return_arr);
    exit();
}

$error = '';
$product_id = productSave($product_input, 0, $error);
if (!$product_id) {
    http_response_code(400);
    $return_arr['success'] = "False";
    $return_arr['message'] = $error !== '' ? $error : 'A product needs a name.';
    echo json_encode($return_arr);
    exit();
}

$insert_id = $product_id;

$product_row = productRow($product_id);
$product_name = escapeSql($product_row['product_name']);
logAudit("Product", "Create", "$product_name via API ($api_key_name)", 0, $product_id);
logAudit("API", "Success", "Created product $product_name via API ($api_key_name)", 0, $product_id);

// Output
require_once '../create_output.php';
