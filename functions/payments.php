<?php

/*
 * ITFlow - Payment & refund helpers
 *
 * Refunds are stored in the payments table as negative rows, linked back to the
 * payment they reverse via payment_refund_of_id. Every balance calculation in the
 * app is a SUM(payment_amount), so a negative row reduces the invoice's amount
 * paid and the account balance without any of those call sites needing to change.
 */

/**
 * Total amount already refunded against a single payment (returned positive).
 */
function getPaymentRefundedTotal($payment_id)
{
    global $mysqli;

    $payment_id = intval($payment_id);

    $sql = mysqli_query($mysqli, "SELECT SUM(payment_amount) AS refunded FROM payments WHERE payment_refund_of_id = $payment_id");
    $row = mysqli_fetch_assoc($sql);

    return abs(floatval($row['refunded']));
}

/**
 * How much of a payment can still be refunded.
 */
function getPaymentRefundableAmount($payment_id)
{
    global $mysqli;

    $payment_id = intval($payment_id);

    $sql = mysqli_query($mysqli, "SELECT payment_amount, payment_refund_of_id FROM payments WHERE payment_id = $payment_id LIMIT 1");
    $row = mysqli_fetch_assoc($sql);

    if (!$row || !is_null($row['payment_refund_of_id'])) {
        return 0.00;
    }

    $payment_amount = floatval($row['payment_amount']);

    if ($payment_amount <= 0) {
        return 0.00;
    }

    return round($payment_amount - getPaymentRefundedTotal($payment_id), 2);
}

/**
 * Pull the Stripe PaymentIntent ID out of a payment reference.
 *
 * All four Stripe booking sites write 'Stripe - pi_xxx' into payment_reference,
 * which is the only place the PI is recorded. Returns null for anything else,
 * which is how a manually entered payment is identified as non-refundable via Stripe.
 */
function getStripePaymentIntentId($payment_reference)
{
    if (preg_match('/^Stripe - (pi_[A-Za-z0-9]+)$/', trim($payment_reference), $matches)) {
        return $matches[1];
    }

    return null;
}

/**
 * Recalculate and store an invoice's status from the sum of its payments.
 *
 * Refund rows are negative, so an invoice that is refunded in full drops back to
 * Sent rather than being stranded on Partial. Draft and Cancelled invoices are
 * left alone - those states are not a function of the payment total.
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
