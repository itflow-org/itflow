<?php
/*
 * API - Invoices - Create
 * POST /api/v1/invoices/create.php
 *
 * Makes a draft invoice for a client, as New Invoice does - the next number,
 * due on the client's terms, in the company's currency - with its lines.
 * Send it with invoices/send.php, or mark it sent with invoices/mark_sent.php.
 *
 * Parameters (POST, JSON body):
 *   api_key      required - Your API key
 *   client_id    required - The client
 *   date         optional - Y-m-d, today if left out
 *   scope        optional - What it is for
 *   category_id  optional - An income category
 *   discount     optional - An amount off it
 *   note         optional - The note it carries
 *   items        optional - Its lines, up to 100: [{"product_id": 4, "qty": 2}, {"name": "Setup", "price": 150}]
 *                           each a product_id (its name, description and price unless sent) or a name and price;
 *                           qty (1 if left out), description, and tax_id - a tax's id, or 'default' for the client's usual rate
 *
 * Answer: insert_id, and invoice_reference, amount and url (where it is viewed and paid).
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';
require_once "../../../includes/load_global_settings.php";
require_once "../../../includes/load_company_settings.php";

$insert_id = false;

$client_ok = $client_id && mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM clients WHERE client_id = $client_id AND client_archived_at IS NULL"));

if ($client_ok) {

    require_once 'invoice_model.php';

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
        $lines[] = $line;
    }
    // Tracked products have to be on the shelf
    $shortfall = productStockShortfall(array_map(function ($line) {
        return ['item_product_id' => $line['product_id'], 'item_quantity' => billingNumberInput($line['qty'])];
    }, $lines));
    if ($shortfall !== '') {
        http_response_code(400);
        $return_arr['success'] = "False";
        $return_arr['message'] = $shortfall;
        echo json_encode($return_arr);
        exit();
    }

    $by = 'the API (' . html_entity_decode($api_key_name, ENT_QUOTES, 'UTF-8') . ')';
    $date = isset($invoice_set['invoice_date']) ? trim($invoice_set['invoice_date'], "'") : date('Y-m-d');
    $invoice_id = billingInvoiceCreate($client_id, $date, intval($invoice_set['invoice_category_id'] ?? 0), (string) ($_POST['scope'] ?? ''), $by);

    if ($invoice_id) {
        $insert_id = $invoice_id;

        // The rest of what was sent - the date, scope and category are in already
        unset($invoice_set['invoice_date'], $invoice_set['invoice_scope'], $invoice_set['invoice_category_id']);
        if ($invoice_set) {
            mysqli_query($mysqli, "UPDATE invoices SET " . implode(', ', array_map(function ($column, $value) {
                return "$column = $value";
            }, array_keys($invoice_set), $invoice_set)) . " WHERE invoice_id = $invoice_id");
        }

        foreach ($lines as $line) {
            billingItemSave('invoice', $invoice_id, $line, 0, $error);
        }
        billingRecalc('invoice', $invoice_id);

        $invoice = billingRow('invoice', $invoice_id, false);
        $reference = billingNumber('invoice', $invoice);
        $return_arr['invoice_reference'] = $reference;
        $return_arr['amount'] = (float) $invoice['invoice_amount'];
        $return_arr['url'] = billingGuestUrl('invoice', $invoice);

        $reference = escapeSql($reference);
        $scope = escapeSql($invoice['invoice_scope']);
        logAudit("Invoice", "Create", "Created invoice $reference - $scope via API ($api_key_name)", $client_id, $invoice_id);
        logAudit("API", "Success", "Created invoice $reference via API ($api_key_name)", $client_id);

        triggerCustomAction('invoice_create', $invoice_id);
    }
}

// Output
require_once '../create_output.php';
