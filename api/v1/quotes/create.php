<?php
/*
 * API - Quotes - Create
 * POST /api/v1/quotes/create.php
 *
 * Makes a draft quote for a client, as New Quote does - the next number, in
 * the company's currency - with its lines. Send it with quotes/send.php.
 *
 * Parameters (POST, JSON body):
 *   api_key      required - Your API key
 *   client_id    required - The client
 *   date         optional - Y-m-d, today if left out
 *   expire       optional - Y-m-d, the day it runs out - 30 days on if left out
 *   scope        optional - What it is for
 *   category_id  optional - An income category
 *   discount     optional - An amount off it
 *   note         optional - The note it carries
 *   items        optional - Its lines, up to 100: [{"product_id": 4, "qty": 2}, {"name": "Setup", "price": 150, "frequency": "month"}]
 *                           each a product_id (its name, description and price unless sent) or a name and price;
 *                           qty (1 if left out), description, tax_id - a tax's id, or 'default' for the client's
 *                           usual rate - and frequency: billed once (left out), or each month, quarter or year
 *
 * Answer: insert_id, and quote_reference, amount and url (where the client views and answers it).
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';
require_once "../../../includes/load_global_settings.php";
require_once "../../../includes/load_company_settings.php";

$insert_id = false;

$client_ok = $client_id && mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM clients WHERE client_id = $client_id AND client_archived_at IS NULL"));

if ($client_ok) {

    require_once 'quote_model.php';

    // Its lines - each checked before anything is made
    $items = $_POST['items'] ?? [];
    if (!is_array($items) || count($items) > 100) {
        http_response_code(400);
        $return_arr['success'] = "False";
        $return_arr['message'] = 'items is a list of up to 100 lines - each a product_id, or a name and price.';
        echo json_encode($return_arr);
        exit();
    }
    $lines = [];
    foreach (array_values($items) as $i => $item) {
        $line = billingLineFromApi($item, $client_id, $error, "items[$i]");
        if (!$line) {
            http_response_code(400);
            $return_arr['success'] = "False";
            $return_arr['message'] = $error;
            echo json_encode($return_arr);
            exit();
        }
        if (array_key_exists('frequency', $item)) {
            $line['frequency'] = (string) $item['frequency'];
        }
        $lines[] = $line;
    }

    $date = isset($quote_set['quote_date']) ? trim($quote_set['quote_date'], "'") : date('Y-m-d');
    $expire = isset($quote_set['quote_expire']) ? trim($quote_set['quote_expire'], "'") : date('Y-m-d', strtotime("$date +30 days"));
    $scope = $quote_set['quote_scope'] ?? "''";
    $category_id = intval($quote_set['quote_category_id'] ?? 0);
    $discount = floatval($quote_set['quote_discount_amount'] ?? 0);
    $note = $quote_set['quote_note'] ?? "''";

    // Atomically increment and get the new quote number
    $quote_number = billingNextNumber('quote');
    $prefix = escapeSql($config_quote_prefix);
    $currency = escapeSql($session_company_currency);
    $url_key = randomString(32);

    $insert_sql = mysqli_query($mysqli, "INSERT INTO quotes SET quote_prefix = '$prefix', quote_number = $quote_number, quote_scope = $scope, quote_date = '$date', quote_expire = '$expire',
        quote_discount_amount = $discount, quote_amount = " . (0 - $discount) . ", quote_note = $note, quote_currency_code = '$currency', quote_category_id = $category_id,
        quote_status = 'Draft', quote_url_key = '$url_key', quote_client_id = $client_id");

    if ($insert_sql) {
        $quote_id = mysqli_insert_id($mysqli);
        $insert_id = $quote_id;

        logHistory('Draft', "Quote created via API ($api_key_name)", 0, $quote_id);

        foreach ($lines as $line) {
            billingItemSave('quote', $quote_id, $line, 0, $error);
        }
        billingRecalc('quote', $quote_id);

        $quote = billingRow('quote', $quote_id, false);
        $reference = billingNumber('quote', $quote);
        $return_arr['quote_reference'] = $reference;
        $return_arr['amount'] = (float) $quote['quote_amount'];
        $return_arr['url'] = billingGuestUrl('quote', $quote);

        $reference = escapeSql($reference);
        logAudit("Quote", "Create", "Created quote $reference via API ($api_key_name)", $client_id, $quote_id);
        logAudit("API", "Success", "Created quote $reference via API ($api_key_name)", $client_id);

        triggerCustomAction('quote_create', $quote_id);
    }
}

// Output
require_once '../create_output.php';
