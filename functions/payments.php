<?php

/*
 * Extract the actual Stripe processing fee from a PaymentIntent.
 * PI must have been created/retrieved with
 * 'expand' => ['latest_charge.balance_transaction'].
 * Returns ['fee' => float, 'currency' => 'USD'] or false if unavailable.
 */
function getStripeGatewayFee($payment_intent)
{
    $bt = $payment_intent->latest_charge->balance_transaction ?? null;

    // Not expanded or not yet created (async payment methods)
    if (!$bt || is_string($bt)) {
        return false;
    }

    return [
        'fee' => round($bt->fee / 100, 2),
        'currency' => strtoupper($bt->currency),
    ];
}
