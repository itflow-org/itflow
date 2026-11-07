<?php

/*
 * API - Recurring Invoices - Create
 * POST /api/v1/recurring_invoices/create.php
 *
 * Makes a recurring invoice for a client, with its lines, and - with
 * invoice_now - its first invoice straight away, sent so it can be paid.
 *
 * Parameters (POST, JSON body):
 *   api_key        required - Your API key
 *   client_id      required - The client
 *   items          required - The lines, 1 to 100: [{"product_id": 4, "qty": 2}, {"name": "Setup", "price": 150}]
 *                             each a product_id (its name, description and price unless sent) or a name and price;
 *                             qty (1 if left out), description, and tax_id - a tax's id, or 'default' for the client's usual rate
 *   frequency      optional - month (the default), quarter or year
 *   start_date     optional - The first run, Y-m-d - today if left out
 *   scope          optional - What it is for, shown on each invoice
 *   note           optional - The note each invoice carries
 *   category_id    optional - An income category
 *   discount       optional - An amount off each invoice
 *   auto_send      optional - 1 (the default): each invoice it makes later is emailed by itself; 0: left as a draft
 *   invoice_now    optional - 1: make the first invoice now, in place of the run on start_date
 *   invoice_send   optional - With invoice_now, 1 (the default): email it to the primary and billing contacts
 *                             and mark it Sent, so its link can be paid; 0: leave it a draft
 *
 * Answer: insert_id (the recurring invoice), recurring_invoice_reference, amount, next_date, and with
 * invoice_now an invoice - invoice_id, reference, amount, currency, due, status, url (where it is
 * viewed and paid) and emailed_to.
 */

require_once '../validate_api_key.php';

require_once '../require_post_method.php';

require_once "../../../includes/load_global_settings.php";

$insert_id = false;
$by = 'the API (' . html_entity_decode($api_key_name, ENT_QUOTES, 'UTF-8') . ')';

[$recurring_invoice_id, $error] = recurringInvoiceCreate(['client_id' => $client_id] + (array) $_POST, $by);
if (!$recurring_invoice_id) {
    $return_arr['success'] = "False";
    $return_arr['message'] = $error;
    header("HTTP/1.1 400 Bad Request");
    echo json_encode($return_arr);
    exit();
}

$insert_id = $recurring_invoice_id;
$recurring = billingRow('recurring', $recurring_invoice_id, false);
$reference = billingNumber('recurring', $recurring);
$return_arr['recurring_invoice_reference'] = $reference;
$return_arr['amount'] = (float) $recurring['recurring_invoice_amount'];

logAudit("Recurring Invoice", "Create", escapeSql("Created recurring invoice $reference - " . $recurring['recurring_invoice_scope'] . " via API ($api_key_name)"), $client_id, $recurring_invoice_id);
logAudit("API", "Success", escapeSql("Created recurring invoice $reference via API ($api_key_name)"), $client_id);

// The first invoice, now
if (intval($_POST['invoice_now'] ?? 0) === 1) {
    $invoice_id = recurringInvoiceGenerate($recurring_invoice_id, $by, true);
    if ($invoice_id) {
        if (getFieldById('invoices', $invoice_id, 'invoice_status') === 'Sent') {
            // Emailed by itself as it was made - the recurring invoice sends its own (recurringInvoiceGenerate())
            $emailed_to = array_map(function ($r) {
                return $r['name'] !== '' ? "{$r['name']} ({$r['email']})" : $r['email'];
            }, billingEmailRecipients(intval($client_id)));
        } else {
            $emailed_to = intval($_POST['invoice_send'] ?? 1) === 1 ? billingInvoiceIssue($invoice_id, $by) : [];
        }
        $invoice = billingRow('invoice', $invoice_id, false);
        $return_arr['invoice'] = [
            'invoice_id' => $invoice_id,
            'reference' => billingNumber('invoice', $invoice),
            'amount' => (float) $invoice['invoice_amount'],
            'currency' => (string) $invoice['invoice_currency_code'],
            'due' => (string) $invoice['invoice_due'],
            'status' => (string) $invoice['invoice_status'],
            'url' => billingGuestUrl('invoice', $invoice),
            'emailed_to' => $emailed_to,
        ];
    }
}
$return_arr['next_date'] = (string) getFieldById('recurring_invoices', $recurring_invoice_id, 'recurring_invoice_next_date');

// Output
require_once '../create_output.php';
