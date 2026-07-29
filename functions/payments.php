<?php

/*
 * ITFlow - Payment helpers
 */

/**
 * Recalculate and store an invoice's status from the sum of its payments.
 *
 * Draft and Cancelled invoices are left alone - those states are not a function
 * of the payment total.
 */
function updateInvoiceStatusFromPayments($invoice_id)
{
    global $mysqli;

    $invoice_id = intval($invoice_id);

    $sql = mysqli_query($mysqli, "SELECT invoice_amount, invoice_status FROM invoices WHERE invoice_id = $invoice_id LIMIT 1");
    $row = mysqli_fetch_assoc($sql);

    if (!$row) {
        return null;
    }

    $invoice_amount = floatval($row['invoice_amount']);
    $invoice_status = $row['invoice_status'];

    if ($invoice_status === 'Draft' || $invoice_status === 'Cancelled') {
        return $invoice_status;
    }

    $sql = mysqli_query($mysqli, "SELECT SUM(payment_amount) AS amount_paid FROM payments WHERE payment_invoice_id = $invoice_id");
    $row = mysqli_fetch_assoc($sql);
    $amount_paid = floatval($row['amount_paid']);

    // Compare in whole cents - float equality on decimal(15,2) values is not reliable
    $paid_cents = (int) round($amount_paid * 100);
    $total_cents = (int) round($invoice_amount * 100);

    if ($paid_cents <= 0) {
        $new_status = 'Sent';
    } elseif ($paid_cents >= $total_cents) {
        $new_status = 'Paid';
    } else {
        $new_status = 'Partial';
    }

    mysqli_query($mysqli, "UPDATE invoices SET invoice_status = '$new_status' WHERE invoice_id = $invoice_id");

    return $new_status;
}
