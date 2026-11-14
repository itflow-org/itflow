<?php
/*
 * API - Recurring Invoices - Update
 * POST /api/v1/recurring_invoices/update.php
 *
 * Change a recurring invoice - only what you send. Pause or resume it with
 * recurring_invoices/status.php; its lines change through recurring_invoice_items/.
 *
 * Parameters (POST, JSON body):
 *   api_key, recurring_invoice_id, client_id (its client)
 *   frequency    month, quarter or year
 *   next_date    Y-m-d - its next run. The day of the month it bills on moves with it
 *   scope, note, category_id (an income category), discount (an amount off each invoice)
 *   auto_send    1: each invoice it makes is emailed by itself; 0: left as a draft
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';

// Parse ID
$recurring_invoice_id = intval($_POST['recurring_invoice_id'] ?? 0);

// Default
$update_count = false;

if (!empty($recurring_invoice_id)) {

    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT recurring_invoice_id, recurring_invoice_prefix, recurring_invoice_number, recurring_invoice_status FROM recurring_invoices WHERE recurring_invoice_id = $recurring_invoice_id AND recurring_invoice_client_id = $client_id LIMIT 1"));

    if ($row) {

        $set = [];
        $error = '';

        if (array_key_exists('frequency', $_POST)) {
            if (!in_array((string) $_POST['frequency'], array_keys(recurringInvoiceFrequencies()), true)) {
                $error = 'frequency is month, quarter or year.';
            }
            $set[] = "recurring_invoice_frequency = '" . validateRecurringFrequency((string) $_POST['frequency']) . "'";
        }
        if (array_key_exists('next_date', $_POST)) {
            $next_date = trim((string) $_POST['next_date']);
            $date = DateTime::createFromFormat('!Y-m-d', $next_date);
            if (!$date || $date->format('Y-m-d') !== $next_date) {
                $error = 'next_date is a date - 2026-11-05.';
            } else {
                // The day it bills on stays put unless the next date moves - set before
                // next_date, as MySQL reads a column already set in the same UPDATE as its new value
                $set[] = "recurring_invoice_anchor_day = IF(recurring_invoice_next_date = '$next_date' AND recurring_invoice_anchor_day > 0, recurring_invoice_anchor_day, DAY('$next_date'))";
                $set[] = "recurring_invoice_next_date = '$next_date'";
            }
        }
        if (array_key_exists('scope', $_POST)) {
            $set[] = "recurring_invoice_scope = '" . escapeSql(mb_substr(trim((string) $_POST['scope']), 0, 255)) . "'";
        }
        if (array_key_exists('note', $_POST)) {
            $set[] = "recurring_invoice_note = '" . mysqli_real_escape_string($mysqli, (string) $_POST['note']) . "'";
        }
        if (array_key_exists('category_id', $_POST)) {
            $category_id = intval($_POST['category_id']);
            if ($category_id && !mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM categories WHERE category_id = $category_id AND category_type = 'Income' AND category_archived_at IS NULL"))) {
                $error = 'category_id is not one of your income categories.';
            }
            $set[] = "recurring_invoice_category_id = $category_id";
        }
        if (array_key_exists('discount', $_POST)) {
            $discount = billingNumberInput($_POST['discount']);
            if ($discount === null || $discount < 0) {
                $error = 'discount is an amount off each invoice, 0 or more.';
            }
            $set[] = "recurring_invoice_discount_amount = " . round(floatval($discount), 2);
        }
        if (array_key_exists('auto_send', $_POST)) {
            $set[] = "recurring_invoice_auto_send = " . (intval($_POST['auto_send']) ? 1 : 0);
        }

        if ($error === '' && !$set) {
            $error = 'Send at least one of frequency, next_date, scope, note, category_id, discount or auto_send.';
        }
        if ($error !== '') {
            http_response_code(400);
            $return_arr['success'] = "False";
            $return_arr['message'] = $error;
            echo json_encode($return_arr);
            exit();
        }

        $update_sql = mysqli_query($mysqli, "UPDATE recurring_invoices SET " . implode(', ', $set) . " WHERE recurring_invoice_id = $recurring_invoice_id AND recurring_invoice_client_id = $client_id LIMIT 1");

        if ($update_sql) {
            // A recurring invoice saved as it was still counts as updated
            $update_count = 1;

            billingRecalc('recurring', $recurring_invoice_id);
            logHistory(intval($row['recurring_invoice_status']) ? 'Active' : 'Paused', "Edited via API ($api_key_name)", 0, 0, $recurring_invoice_id);

            $reference = escapeSql(billingNumber('recurring', $row));
            logAudit("Recurring Invoice", "Edit", "Edited recurring invoice $reference via API ($api_key_name)", $client_id, $recurring_invoice_id);
            logAudit("API", "Success", "Edited recurring invoice $reference via API ($api_key_name)", $client_id);
        }
    }
}

// Output
require_once '../update_output.php';
