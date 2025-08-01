<?php

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_GET['delete_saved_payment'])) {
    validateCSRFToken($_GET['csrf_token']);

    $saved_payment_id = intval($_GET['delete_saved_payment']);

    $sql = mysqli_query($mysqli, "
        SELECT 
            client_saved_payment_methods.saved_payment_id,
            client_saved_payment_methods.saved_payment_client_id,
            client_saved_payment_methods.saved_payment_provider_id,
            client_saved_payment_methods.saved_payment_provider_method,
            client_saved_payment_methods.saved_payment_description,
            client_payment_provider.payment_provider_client,
            payment_providers.payment_provider_name,
            payment_providers.payment_provider_private_key
        FROM client_saved_payment_methods
        LEFT JOIN client_payment_provider
            ON client_payment_provider.client_id = client_saved_payment_methods.saved_payment_client_id
            AND client_payment_provider.payment_provider_id = client_saved_payment_methods.saved_payment_provider_id
        LEFT JOIN payment_providers
            ON payment_providers.payment_provider_id = client_saved_payment_methods.saved_payment_provider_id
        WHERE client_saved_payment_methods.saved_payment_id = $saved_payment_id"
    );

    $row = mysqli_fetch_array($sql);
    $client_id = intval($row['saved_payment_client_id']);
    $provider_id = intval($row['saved_payment_provider_id']);
    $payment_provider_name = nullable_htmlentities($row['payment_provider_name']);
    $saved_payment_description = nullable_htmlentities($row['saved_payment_description']);
    $provider_client = nullable_htmlentities($row['payment_provider_client']);
    $payment_method = $row['saved_payment_provider_method'];

    $private_key = $row['payment_provider_private_key'];

    // Seperate logic for each Payment Provider
    if ($payment_provider_name == 'Stripe') {

        try {
            // Initialize stripe
            require_once 'plugins/stripe-php/init.php';
            $stripe = new \Stripe\StripeClient($private_key);

            // Detach PM
            $stripe->paymentMethods->detach($payment_method, []);

        } catch (Exception $e) {
            $error = $e->getMessage();
            error_log("Stripe payment error - encountered exception when removing payment method info for $payment_method: $error");
            logApp("Stripe", "error", "Exception removing payment method for $payment_method: $error");
        }

    }

    // Remove payment method from ITFlow
    mysqli_query($mysqli, "DELETE FROM client_saved_payment_methods WHERE saved_payment_id = $saved_payment_id");

    // SQL Cascade delete will Remove All Associated Auto Payment Methods on recurring invoices in the recurring payments table.

    logAction("Payment Provider", "Update", "$session_name deleted saved payment method $saved_payment_description (PM: $payment_method)", $client_id);
    
    flash_alert("Payment method <strong>$saved_payment_description</strong> removed", 'error');
    
    redirect();

}
