<?php

// A quote's own fields from POST - date, expire, scope, category_id, discount and
// note - as columns to set, each only when sent. $quote_model_error says what was wrong.

$quote_set = [];
$quote_model_error = '';

foreach (['date' => 'quote_date', 'expire' => 'quote_expire'] as $field => $column) {
    if (!array_key_exists($field, $_POST)) {
        continue;
    }
    $value = trim((string) $_POST[$field]);
    $date = DateTime::createFromFormat('!Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value) {
        $quote_model_error = "$field is a date - 2026-11-05.";
    } else {
        $quote_set[$column] = "'$value'";
    }
}
if (array_key_exists('scope', $_POST)) {
    $quote_set['quote_scope'] = "'" . escapeSql(mb_substr(trim((string) $_POST['scope']), 0, 255)) . "'";
}
if (array_key_exists('category_id', $_POST)) {
    $category_id = intval($_POST['category_id']);
    if ($category_id && !mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM categories WHERE category_id = $category_id AND category_type = 'Income' AND category_archived_at IS NULL"))) {
        $quote_model_error = 'category_id is not one of your income categories.';
    }
    $quote_set['quote_category_id'] = $category_id;
}
if (array_key_exists('discount', $_POST)) {
    $discount = billingNumberInput($_POST['discount']);
    if ($discount === null || $discount < 0) {
        $quote_model_error = 'discount is an amount off the quote, 0 or more.';
    }
    $quote_set['quote_discount_amount'] = round(floatval($discount), 2);
}
if (array_key_exists('note', $_POST)) {
    $quote_set['quote_note'] = "'" . mysqli_real_escape_string($mysqli, (string) $_POST['note']) . "'";
}

if ($quote_model_error !== '') {
    http_response_code(400);
    $return_arr['success'] = "False";
    $return_arr['message'] = $quote_model_error;
    echo json_encode($return_arr);
    exit();
}

// The quote's columns, as a SET list
function quoteApiSetSql(array $set) {
    return implode(', ', array_map(function ($column, $value) {
        return "$column = $value";
    }, array_keys($set), $set));
}
