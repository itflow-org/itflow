<?php

require_once "includes/inc_all_client.php";

// Perms
enforceUserPermission('module_sales');

// Initialize stripe
require_once '../includes/stripe_init.php';

// Get Stripe vars
$stripe_vars = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT config_stripe_enable, config_stripe_publishable, config_stripe_secret FROM settings WHERE company_id = 1"));
$config_stripe_enable = intval($stripe_vars['config_stripe_enable']);
$config_stripe_publishable = escapeHtml($stripe_vars['config_stripe_publishable']);
$config_stripe_secret = escapeHtml($stripe_vars['config_stripe_secret']);

// Get client's StripeID from database
$stripe_client_details = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM client_stripe WHERE client_id = $client_id LIMIT 1"));
if ($stripe_client_details) {
    $stripe_id = escapeSql($stripe_client_details['stripe_id']);
    $stripe_pm = escapeSql($stripe_client_details['stripe_pm']);
}

// Stripe not enabled in settings
if (!$config_stripe_enable || !$config_stripe_publishable || !$config_stripe_secret) {
    echo "Stripe payment error - Stripe is not enabled, please talk to your helpdesk for further information.";
    include_once '../includes/footer.php';
    exit();
}

?>

<div class="card card-dark">
    <div class="card-header">
        <h3 class="card-title"><i class="fa fa-redo-alt me-2"></i>AutoPay</h3>
    </div>

    <div class="card-body">
            <!-- Setup pt1: Stripe ID not found / auto-payment not configured -->
            <?php if (!$stripe_client_details || empty($stripe_id)) { ?>

                <b>Save card details</b><br>
                In order to set up automatic payments, you must create a customer record in Stripe.<br>
                First, you must authorize Stripe to store your card details for the purpose of automatic payment.
            <br><br>

                <div class="col-5">
                    <form action="post.php" method="POST">

                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="consent" name="consent" value="1" required>
                                <label for="consent" class="form-check-label">
                                    I grant consent for automatic payments
                                </label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <button type="submit" class="form-control btn-success" name="create_stripe_customer">Create Stripe Customer Record</button>
                        </div>
                    </form>
                </div>

            <?php }

            // Setup pt2: Stripe ID found / payment may be configured -->
            elseif (empty($stripe_pm)) { ?>

                <b>Save card details</b><br>
                Please add the payment details you would like to save.<br>
                By adding payment details here, you grant consent for future automatic payments of invoices.<br><br>

                <input type="hidden" id="stripe_publishable_key" value="<?= $config_stripe_publishable ?>">
                <script src="https://js.stripe.com/v3/"></script>
                <script src="js/autopay_setup_stripe.js"></script>
                <div id="checkout">
                    <!-- Checkout will insert the payment form here -->
                </div>

            <?php }

            // Manage the saved card
            else { ?>

                <b>Manage saved payment methods</b>

                <?php

                try {
                    // Initialize
                    $stripe = new \Stripe\StripeClient($config_stripe_secret);

                    // Get payment method info (last 4 digits etc)
                    $payment_method = $stripe->customers->retrievePaymentMethod(
                        $stripe_id,
                        $stripe_pm,
                        []
                    );

                } catch (Exception $e) {
                    $error = $e->getMessage();
                    error_log("Stripe payment error - encountered exception when fetching payment method info for $stripe_pm: $error");
                    logApp("Stripe", "error", "Exception when fetching payment method info for $stripe_pm: $error");
                }

                $card_name = escapeHtml($payment_method->billing_details->name);
                $card_brand = escapeHtml($payment_method->card->display_brand);
                $card_last4 = escapeHtml($payment_method->card->last4);
                $card_expires = escapeHtml($payment_method->card->exp_month) . "/" . escapeHtml($payment_method->card->exp_year);

                ?>

                <ul><li><?= "$card_name - $card_brand card ending in $card_last4, expires $card_expires" ?></li></ul>

                <hr>
                <b>Actions</b><br>
                - <a href="post.php?stripe_remove_pm&pm=<?= $stripe_pm ?>">Remove saved payment method</a>

            <?php } ?>


        </div>

    </div>
</div>

<?php

require_once "../includes/footer.php";
