<?php
/*
 * API - Quotes - Mark sent
 * POST /api/v1/quotes/mark_sent.php
 *
 * Mark a quote sent without emailing it, as Mark Sent does on its page.
 *
 * Parameters (POST, JSON body):
 *   api_key, quote_id, client_id (the quote's client)
 *   sent_method  required - Sent by Snail Mail, Sent by Email Client, Hand Delivered,
 *                           Sent by Fax, Sent by Courier, or another of the app's list
 *   note         optional - Kept in its history
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';

// Parse ID
$quote_id = intval($_POST['quote_id'] ?? 0);

// Default
$update_count = false;

if (!empty($quote_id)) {

    $quote = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT quote_id, quote_prefix, quote_number FROM quotes WHERE quote_id = $quote_id AND quote_client_id = $client_id LIMIT 1"));

    if ($quote) {

        // The app's fixed list
        $sent_method = (string) ($_POST['sent_method'] ?? '');
        if (!in_array($sent_method, getSentMethods(), true)) {
            http_response_code(400);
            $return_arr['success'] = "False";
            $return_arr['message'] = 'sent_method is one of: ' . implode(', ', getSentMethods()) . '.';
            echo json_encode($return_arr);
            exit();
        }

        $note = escapeSql(mb_substr(trim((string) ($_POST['note'] ?? '')), 0, 500));

        $update_sql = mysqli_query($mysqli, "UPDATE quotes SET quote_status = 'Sent' WHERE quote_id = $quote_id AND quote_client_id = $client_id LIMIT 1");

        if ($update_sql) {
            $update_count = 1;

            $sent_method = escapeSql($sent_method);
            logHistory('Sent', "Quote marked sent via API ($api_key_name) - $sent_method" . ($note !== '' ? "\nNote: $note" : ''), 0, $quote_id);

            $reference = escapeSql(billingNumber('quote', $quote));
            logAudit("Quote", "Send", "Marked quote $reference as sent - $sent_method via API ($api_key_name)", $client_id, $quote_id);
            logAudit("API", "Success", "Marked quote $reference sent via API ($api_key_name)", $client_id);
        }
    }
}

// Output
require_once '../update_output.php';
