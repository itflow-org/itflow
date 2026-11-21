<?php
/*
 * API - Quotes - Invoice
 * POST /api/v1/quotes/invoice.php
 *
 * Invoice an accepted quote, as Invoice does on its page (quoteConvert()):
 * its one-time lines go on a new draft invoice, and its lines billed each
 * month, quarter or year on a recurring invoice for each - a new one, or
 * added to one of the client's billed as often. The quote becomes Invoiced.
 *
 * Parameters (POST, JSON body):
 *   api_key       required - Your API key
 *   quote_id      required - The quote
 *   client_id     the quote's client - needed when the key's user is limited to certain clients
 *   date          optional - The invoice's date, Y-m-d - today if left out
 *   first_period  optional - 1: put the recurring lines on the invoice too, billing their first period now
 *   recurring     optional - For each frequency the quote bills - month, quarter or year - where its lines go:
 *                            {"month": {"to": 0, "start": "2026-12-01", "auto_send": 1}}
 *                            to: 0 for a new recurring invoice (the default), or one of the client's
 *                            billed as often; start: a new one's first invoice date (the first of next
 *                            month if left out); auto_send: 1 (the default) emails its invoices, 0 leaves drafts
 *
 * Answer: invoice_id (0 when the quote had no one-time lines), recurring_invoice_ids, and references.
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';
require_once "../../../includes/load_global_settings.php";
require_once "../../../includes/load_company_settings.php";

// Parse ID
$quote_id = intval($_POST['quote_id'] ?? 0);

// Default
$update_count = false;

$quote = $quote_id ? billingRow('quote', $quote_id) : null;
if ($quote && $client_id != 0 && intval($quote['quote_client_id']) !== $client_id) {
    $quote = null;
}

if ($quote) {

    $client_id = intval($quote['quote_client_id']);

    $recurring = [];
    foreach ((array) ($_POST['recurring'] ?? []) as $frequency => $choice) {
        $choice = (array) $choice;
        $recurring[(string) $frequency] = [
            'to' => intval($choice['to'] ?? 0),
            'start' => (string) ($choice['start'] ?? ''),
            'auto_send' => intval($choice['auto_send'] ?? 1),
        ];
    }

    $error = '';
    $result = quoteConvert($quote_id, [
        'date' => (string) ($_POST['date'] ?? ''),
        'first_period' => !empty($_POST['first_period']),
        'recurring' => $recurring,
        'by' => 'the API (' . html_entity_decode($api_key_name, ENT_QUOTES, 'UTF-8') . ')',
    ], $error);

    if (!$result) {
        http_response_code(400);
        $return_arr['success'] = "False";
        $return_arr['message'] = $error;
        echo json_encode($return_arr);
        exit();
    }

    $update_count = 1;
    $return_arr['invoice_id'] = intval($result['invoice_id']);
    $return_arr['recurring_invoice_ids'] = array_map('intval', array_keys($result['recurring']));
    $return_arr['references'] = array_values($result['numbers']);

    $reference = escapeSql(billingNumber('quote', $quote));
    logAudit("API", "Success", "Invoiced quote $reference as " . escapeSql(implode(', ', $result['numbers'])) . " via API ($api_key_name)", $client_id);
}

// Output
require_once '../update_output.php';
