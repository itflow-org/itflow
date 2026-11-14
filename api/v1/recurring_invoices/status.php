<?php
/*
 * API - Recurring Invoices - Status
 * POST /api/v1/recurring_invoices/status.php
 *
 * Pause a recurring invoice, or resume it, as on its page. Resumed with its
 * next date passed, it won't run until next_date is moved on
 * (recurring_invoices/update.php) - the answer's message says so.
 *
 * Parameters (POST, JSON body):
 *   api_key, recurring_invoice_id, client_id (its client)
 *   status   required - 1 to resume (Active), 0 to pause
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';

// Parse ID
$recurring_invoice_id = intval($_POST['recurring_invoice_id'] ?? 0);

// Default
$update_count = false;

if (!empty($recurring_invoice_id) && isset($_POST['status'])) {

    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT recurring_invoice_id, recurring_invoice_prefix, recurring_invoice_number, recurring_invoice_next_date FROM recurring_invoices WHERE recurring_invoice_id = $recurring_invoice_id AND recurring_invoice_client_id = $client_id LIMIT 1"));

    if ($row) {

        $status = intval($_POST['status']) ? 1 : 0;

        $update_sql = mysqli_query($mysqli, "UPDATE recurring_invoices SET recurring_invoice_status = $status WHERE recurring_invoice_id = $recurring_invoice_id AND recurring_invoice_client_id = $client_id LIMIT 1");

        if ($update_sql) {
            $update_count = 1;

            $reference = billingNumber('recurring', $row);
            billingLog('recurring', $recurring_invoice_id, $status ? 'Active' : 'Paused', ($status ? 'Resumed' : 'Paused') . ' by the API (' . html_entity_decode($api_key_name, ENT_QUOTES, 'UTF-8') . ')');

            if ($status && $row['recurring_invoice_next_date'] < date('Y-m-d')) {
                $return_arr['message'] = "$reference resumed - its next date has passed, so move it on before it will run again.";
            }

            $reference = escapeSql($reference);
            logAudit("Recurring Invoice", "Edit", ($status ? 'Resumed' : 'Paused') . " recurring invoice $reference via API ($api_key_name)", $client_id, $recurring_invoice_id);
            logAudit("API", "Success", ($status ? 'Resumed' : 'Paused') . " recurring invoice $reference via API ($api_key_name)", $client_id);
        }
    }
}

// Output
require_once '../update_output.php';
