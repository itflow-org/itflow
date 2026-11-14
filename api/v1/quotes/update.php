<?php
/*
 * API - Quotes - Update
 * POST /api/v1/quotes/update.php
 *
 * Change a quote's date, expiry, scope, category, discount or note - only
 * what you send. Its total follows the discount, as on its page. Its lines
 * change through quote_items/.
 *
 * Parameters (POST, JSON body):
 *   api_key, quote_id, client_id (the quote's client)
 *   date, expire (Y-m-d), scope, category_id, discount, note
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

        require_once 'quote_model.php';

        if (!$quote_set) {
            http_response_code(400);
            $return_arr['success'] = "False";
            $return_arr['message'] = 'Send at least one of date, expire, scope, category_id, discount or note.';
            echo json_encode($return_arr);
            exit();
        }

        $update_sql = mysqli_query($mysqli, "UPDATE quotes SET " . quoteApiSetSql($quote_set) . " WHERE quote_id = $quote_id AND quote_client_id = $client_id LIMIT 1");

        if ($update_sql) {
            // A quote saved as it was still counts as updated
            $update_count = 1;

            billingRecalc('quote', $quote_id);

            $reference = escapeSql(billingNumber('quote', $quote));
            logAudit("Quote", "Edit", "Edited quote $reference via API ($api_key_name)", $client_id, $quote_id);
            logAudit("API", "Success", "Edited quote $reference via API ($api_key_name)", $client_id);
        }
    }
}

// Output
require_once '../update_output.php';
