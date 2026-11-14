<?php
/*
 * API - Recurring Invoices - Delete
 * POST /api/v1/recurring_invoices/delete.php
 *
 * Delete a recurring invoice, as Delete does on its page - its lines, history
 * and autopay go with it. The invoices it made stay.
 *
 * Parameters (POST, JSON body): api_key, recurring_invoice_id, client_id (its client)
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';

// Parse ID
$recurring_invoice_id = intval($_POST['recurring_invoice_id'] ?? 0);

// Default
$delete_count = false;

if (!empty($recurring_invoice_id)) {

    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT recurring_invoice_id, recurring_invoice_prefix, recurring_invoice_number, recurring_invoice_scope FROM recurring_invoices WHERE recurring_invoice_id = $recurring_invoice_id AND recurring_invoice_client_id = $client_id LIMIT 1"));

    if ($row) {

        mysqli_query($mysqli, "DELETE FROM recurring_invoices WHERE recurring_invoice_id = $recurring_invoice_id AND recurring_invoice_client_id = $client_id LIMIT 1");
        $delete_count = mysqli_affected_rows($mysqli);

        if ($delete_count > 0) {
            // Its autopay goes with it - the nightly run would otherwise keep a card set up for nothing
            mysqli_query($mysqli, "DELETE FROM recurring_payments WHERE recurring_payment_recurring_invoice_id = $recurring_invoice_id");

            // A contract it billed is no longer linked to it
            if (contractBillingReady()) {
                mysqli_query($mysqli, "UPDATE contracts SET contract_recurring_invoice_id = 0, contract_recurring_stopped_at = NULL WHERE contract_recurring_invoice_id = $recurring_invoice_id");
            }

            // The recurring expenses it covered are only our cost again
            mysqli_query($mysqli, "UPDATE recurring_expenses SET recurring_expense_recurring_invoice_id = NULL WHERE recurring_expense_recurring_invoice_id = $recurring_invoice_id");

            mysqli_query($mysqli, "DELETE FROM recurring_invoice_items WHERE item_recurring_invoice_id = $recurring_invoice_id");
            mysqli_query($mysqli, "DELETE FROM history WHERE history_recurring_invoice_id = $recurring_invoice_id");

            $reference = escapeSql(billingNumber('recurring', $row));
            $scope = escapeSql($row['recurring_invoice_scope']);
            logAudit("Recurring Invoice", "Delete", "Deleted recurring invoice $reference - $scope via API ($api_key_name)", $client_id);
            logAudit("API", "Success", "Deleted recurring invoice $reference via API ($api_key_name)", $client_id);
        }
    }
}

// Output
require_once '../delete_output.php';
