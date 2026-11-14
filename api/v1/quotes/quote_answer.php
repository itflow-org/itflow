<?php

// The client's answer to a quote, recorded by an agent - accept.php and decline.php
// set $quote_answer ('Accepted' or 'Declined'). An invoiced quote keeps its answer.

// Only through those, after the key is checked (apiDeny() comes with enforce_api_rbac.php)
if (!function_exists('apiDeny') || !in_array($quote_answer ?? '', ['Accepted', 'Declined'], true)) {
    http_response_code(404);
    exit();
}

// Parse ID
$quote_id = intval($_POST['quote_id'] ?? 0);

// Default
$update_count = false;

if (!empty($quote_id)) {

    $quote = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT quote_id, quote_prefix, quote_number, quote_status FROM quotes WHERE quote_id = $quote_id AND quote_client_id = $client_id LIMIT 1"));

    if ($quote && $quote['quote_status'] === 'Invoiced') {
        http_response_code(400);
        $return_arr['success'] = "False";
        $return_arr['message'] = billingNumber('quote', $quote) . " has been invoiced - its answer stays as it is.";
        echo json_encode($return_arr);
        exit();
    }

    if ($quote) {

        $update_sql = mysqli_query($mysqli, "UPDATE quotes SET quote_status = '$quote_answer' WHERE quote_id = $quote_id AND quote_client_id = $client_id LIMIT 1");

        if ($update_sql) {
            $update_count = 1;

            $word = strtolower($quote_answer);
            logHistory($quote_answer, "Quote $word via API ($api_key_name)", 0, $quote_id);

            $reference = escapeSql(billingNumber('quote', $quote));
            logAudit("Quote", "Edit", "Marked quote $reference as $word via API ($api_key_name)", $client_id, $quote_id);
            logAudit("API", "Success", "Marked quote $reference as $word via API ($api_key_name)", $client_id);

            triggerCustomAction($quote_answer === 'Accepted' ? 'quote_accept' : 'quote_decline', $quote_id);
        }
    }
}

// Output
require_once '../update_output.php';
