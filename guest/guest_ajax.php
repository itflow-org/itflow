<?php

/*
 * guest_ajax.php
 * Similar to post.php/ajax.php, but for unauthenticated requests using Asynchronous JavaScript
 * Always returns data in JSON format, unless otherwise specified
 */

require_once "../config.php";

// Set Timezone
require_once "../includes/inc_set_timezone.php";
require_once "../functions.php";
require_once "../plugins/totp/totp.php";


/*
 * Creates & Returns a Stripe Payment Intent for a particular invoice ID
 */

if (isset($_GET['stripe_create_pi'])) {

    // Response header
    header('Content-Type: application/json');

    // Params from POST (guest_pay_invoice_stripe.js)
    $jsonStr = file_get_contents('php://input');
    $jsonObj = json_decode($jsonStr, true);
    $invoice_id = intval($jsonObj['invoice_id']);
    $url_key = sanitizeInput($jsonObj['url_key']);

    // Query invoice details
    $invoice_sql = mysqli_query(
        $mysqli,
        "SELECT * FROM invoices
        LEFT JOIN clients ON invoice_client_id = client_id
        WHERE invoice_id = $invoice_id
        AND invoice_url_key = '$url_key'
        AND invoice_status != 'Draft'
        AND invoice_status != 'Paid'
        AND invoice_status != 'Cancelled'
        LIMIT 1"
    );
    if (!$invoice_sql || mysqli_num_rows($invoice_sql) !== 1) {
        exit("Invalid Invoice ID/SQL query");
    }

    // Invoice exists - get details for payment
    $row = mysqli_fetch_array($invoice_sql);
    $invoice_prefix = nullable_htmlentities($row['invoice_prefix']);
    $invoice_number = intval($row['invoice_number']);
    $invoice_amount = floatval($row['invoice_amount']);
    $invoice_currency_code = nullable_htmlentities($row['invoice_currency_code']);
    $client_id = intval($row['client_id']);
    $client_name = nullable_htmlentities($row['client_name']);

    $config_sql = mysqli_query($mysqli, "SELECT * FROM settings WHERE company_id = 1");
    $config_row = mysqli_fetch_array($config_sql);
    $config_stripe_percentage_fee = floatval($config_row['config_stripe_percentage_fee']);
    $config_stripe_flat_fee = floatval($config_row['config_stripe_flat_fee']);

    // Add up all the payments for the invoice and get the total amount paid to the invoice
    $sql_amount_paid = mysqli_query($mysqli, "SELECT SUM(payment_amount) AS amount_paid FROM payments WHERE payment_invoice_id = $invoice_id");
    $row = mysqli_fetch_array($sql_amount_paid);
    $amount_paid = floatval($row['amount_paid']);
    $balance_to_pay = $invoice_amount - $amount_paid;

    $balance_to_pay = round($balance_to_pay, 2);

    if (intval($balance_to_pay) == 0) {
        exit("No balance outstanding");
    }

    // Setup Stripe
    require_once '../plugins/stripe-php/init.php';


    $row = mysqli_fetch_array(mysqli_query($mysqli, "SELECT config_stripe_enable, config_stripe_secret, config_stripe_account FROM settings WHERE company_id = 1"));
    if ($row['config_stripe_enable'] == 0 || $row['config_stripe_account'] == 0) {
        exit("Stripe not enabled / configured");
    }

    $config_stripe_secret = $row['config_stripe_secret'];
    $pi_description = "ITFlow: $client_name payment of $invoice_currency_code $balance_to_pay for $invoice_prefix$invoice_number";

    // Create a PaymentIntent with amount, currency and client details
    try {
        \Stripe\Stripe::setApiKey($config_stripe_secret);

        $paymentIntent = \Stripe\PaymentIntent::create([
            'amount' => intval($balance_to_pay * 100), // Times by 100 as Stripe expects values in cents
            'currency' => $invoice_currency_code,
            'description' => $pi_description,
            'metadata' => [
                'itflow_client_id' => $client_id,
                'itflow_client_name' => $client_name,
                'itflow_invoice_number' => $invoice_prefix . $invoice_number,
                'itflow_invoice_id' => $invoice_id,
            ],
            'automatic_payment_methods' => [
                'enabled' => true,
            ],
        ]);

        $output = [
            'clientSecret' => $paymentIntent->client_secret,
        ];

        echo json_encode($output);

    } catch (Error $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }

}

if (isset($_GET['get_totp_token'])) {
    $otp = TokenAuth6238::getTokenCode(strtoupper($_GET['totp_secret']));

    echo json_encode($otp);
}
