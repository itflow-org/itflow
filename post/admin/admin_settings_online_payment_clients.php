<?php

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_GET['stripe_remove_pm'])) {
    validateCSRFToken($_GET['csrf_token']);

    if (!$config_stripe_enable) {
        $_SESSION['alert_message'] = "Stripe not enabled";
        header("Location: " . $_SERVER["HTTP_REFERER"]);
        exit();
    }

    $client_id = intval($_GET['client_id']);
    $payment_method = sanitizeInput($_GET['pm']);

    try {
        // Initialize stripe
        require_once 'plugins/stripe-php-10.5.0/init.php';
        $stripe = new \Stripe\StripeClient($config_stripe_secret);

        // Detach PM
        $stripe->paymentMethods->detach($payment_method, []);

    } catch (Exception $e) {
        $error = $e->getMessage();
        error_log("Stripe payment error - encountered exception when removing payment method info for $payment_method: $error");
        logApp("Stripe", "error", "Exception removing payment method for $payment_method: $error");
    }

    // Remove payment method from ITFlow
    mysqli_query($mysqli, "UPDATE client_stripe SET stripe_pm = NULL WHERE client_id = $client_id LIMIT 1");

    // Logging & Redirect
    logAction("Stripe", "Update", "$session_name deleted saved Stripe payment method (PM: $payment_method)", $client_id);
    $_SESSION['alert_message'] = "Payment method removed";
    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['stripe_reset_customer'])) {
    validateCSRFToken($_GET['csrf_token']);

    $client_id = intval($_GET['client_id']);

    // Delete the customer id and payment method id stored in ITFlow, allowing the client to set these up again
    mysqli_query($mysqli, "DELETE FROM client_stripe WHERE client_id = $client_id");

    // Logging
    logAction("Stripe", "Delete", "$session_name reset Stripe settings for client", $client_id);

    $_SESSION['alert_message'] = "Reset client Stripe settings";
    header("Location: " . $_SERVER["HTTP_REFERER"]);
}