<?php

// An invoice's own fields from POST - date, due, scope, category_id, discount and
// note - as columns to set, each only when sent. $invoice_model_error says what was wrong.

$invoice_set = [];
$invoice_model_error = '';

$api_date = function ($field) use (&$invoice_model_error) {
    $value = trim((string) ($_POST[$field] ?? ''));
    $date = DateTime::createFromFormat('!Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value) {
        $invoice_model_error = "$field is a date - 2026-11-05.";
        return null;
    }
    return $value;
};

if (array_key_exists('date', $_POST) && ($value = $api_date('date')) !== null) {
    $invoice_set['invoice_date'] = "'$value'";
}
if (array_key_exists('due', $_POST) && ($value = $api_date('due')) !== null) {
    $invoice_set['invoice_due'] = "'$value'";
}
if (array_key_exists('scope', $_POST)) {
    $invoice_set['invoice_scope'] = "'" . escapeSql(mb_substr(trim((string) $_POST['scope']), 0, 255)) . "'";
}
if (array_key_exists('category_id', $_POST)) {
    $category_id = intval($_POST['category_id']);
    if ($category_id && !mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM categories WHERE category_id = $category_id AND category_type = 'Income' AND category_archived_at IS NULL"))) {
        $invoice_model_error = 'category_id is not one of your income categories.';
    }
    $invoice_set['invoice_category_id'] = $category_id;
}
if (array_key_exists('discount', $_POST)) {
    $discount = billingNumberInput($_POST['discount']);
    if ($discount === null || $discount < 0) {
        $invoice_model_error = 'discount is an amount off the invoice, 0 or more.';
    }
    $invoice_set['invoice_discount_amount'] = round(floatval($discount), 2);
}
if (array_key_exists('note', $_POST)) {
    $invoice_set['invoice_note'] = "'" . mysqli_real_escape_string($mysqli, (string) $_POST['note']) . "'";
}

if ($invoice_model_error !== '') {
    http_response_code(400);
    $return_arr['success'] = "False";
    $return_arr['message'] = $invoice_model_error;
    echo json_encode($return_arr);
    exit();
}
