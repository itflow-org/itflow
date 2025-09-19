<?php
/*
 * Client Portal - AutoPay Configuration (multi-provider)
 */

require_once "includes/inc_all.php";

if ($session_contact_primary == 0 && !$session_contact_is_billing_contact) {
    header("Location: post.php?logout");
    exit();
}

// Initialize Stripe
require_once '../plugins/stripe-php/init.php';

// Get Stripe provider info
$stripe_provider_query = mysqli_query($mysqli, "
    SELECT * FROM payment_providers WHERE payment_provider_name = 'Stripe' LIMIT 1
");
$stripe_provider = mysqli_fetch_array($stripe_provider_query);

if (!$stripe_provider) {
    echo "Stripe payment error - Stripe provider is not configured.";
    include_once 'includes/footer.php';
    exit();
}

$stripe_provider_id = intval($stripe_provider['payment_provider_id']);
$stripe_public_key = nullable_htmlentities($stripe_provider['payment_provider_public_key']);
$stripe_secret_key = nullable_htmlentities($stripe_provider['payment_provider_private_key']);

// Get client's Stripe customer ID
$stripe_customer_query = mysqli_query($mysqli, "
    SELECT * FROM client_payment_provider
    WHERE client_id = $session_client_id AND payment_provider_id = $stripe_provider_id
    LIMIT 1
");
$stripe_customer = mysqli_fetch_array($stripe_customer_query);
$stripe_customer_id = $stripe_customer ? sanitizeInput($stripe_customer['payment_provider_client']) : null;

// Get saved payment methods
$saved_methods_query = mysqli_query($mysqli, "
    SELECT * FROM client_saved_payment_methods
    WHERE saved_payment_client_id = $session_client_id
    AND saved_payment_provider_id = $stripe_provider_id
");

$saved_methods = [];
while ($row = mysqli_fetch_array($saved_methods_query)) {
    $saved_methods[] = $row;
}

// Stripe not properly configured
if (!$stripe_public_key || !$stripe_secret_key) {
    echo "Stripe payment error - Stripe credentials missing. Please contact support.";
    include_once 'includes/footer.php';
    exit();
}
?>

<h3>Saved Payment Methods</h3>
<hr>
<div class="row">
    <div class="col-md-6">

        <?php if (!$stripe_customer_id) { ?>
            In order to set up automatic payments, you must create a customer record in Stripe.
            First, you must authorize Stripe to store your card details for the purpose of automatic payment.
            <br><br>

            <form action="post.php" method="POST">
               
                <div class="form-group">
                    <button type="submit" class="btn btn-success" name="create_stripe_customer"><strong><i class="fas fa-check mr-2"></i>I grant consent for automatic payments</strong></button>
                </div>
            </form>
            
        <?php } else { ?>

            <b>Manage saved payment methods</b><br><br>

            <?php if (empty($saved_methods)) { ?>
                <p>You currently have no saved payment methods. Please add one below.</p>
            <?php } else { ?>
                <ul class="list-unstyled">
                    <?php
                    try {
                        $stripe = new \Stripe\StripeClient($stripe_secret_key);

                        foreach ($saved_methods as $method) {
                            $stripe_pm_id = $method['saved_payment_provider_method'];
                            $description = nullable_htmlentities($method['saved_payment_description']);
                            $payment_icon = "fas fa-credit-card"; // default icon
                            if (strpos($description, "visa") !== false) {
                                $payment_icon = "fab fa-cc-visa";
                            } elseif (strpos($description, "mastercard") !== false) {
                                $payment_icon = "fab fa-cc-mastercard";
                            } elseif (strpos($description, "american express") !== false || strpos($description, "amex") !== false) {
                                $payment_icon = "fab fa-cc-amex";
                            } elseif (strpos($description, "discover") !== false) {
                                $payment_icon = "fab fa-cc-discover";
                            }

                            $pm = $stripe->paymentMethods->retrieve($stripe_pm_id, []);
                            $brand = nullable_htmlentities($pm->card->brand);
                            $last4 = nullable_htmlentities($pm->card->last4);
                            $exp_month = nullable_htmlentities($pm->card->exp_month);
                            $exp_year = nullable_htmlentities($pm->card->exp_year);

                            echo "<li><i class='$payment_icon fa-2x mr-2'></i>$brand x<strong>$last4</strong> | Exp. $exp_month/$exp_year";
                            echo " – <a class='text-danger' href='post.php?delete_saved_payment={$method['saved_payment_id']}'>Remove</a></li>";
                        }
                    } catch (Exception $e) {
                        $error = $e->getMessage();
                        error_log("Stripe payment error: $error");
                        logApp("Stripe", "error", "Exception retrieving payment methods: $error");
                        echo "<p class='text-danger'>Unable to retrieve payment methods from Stripe.</p>";
                    }
                    ?>
                </ul>
            <?php } ?>
        </div>
        <div class="col-md-6">
            <b>Add a new payment method</b><br><br>

            <input type="hidden" id="stripe_publishable_key" value="<?php echo $stripe_public_key ?>">
            <script src="https://js.stripe.com/v3/"></script>
            <script src="../js/autopay_setup_stripe.js"></script>
            <div id="checkout">
                <!-- Checkout form dynamically loaded -->
            </div>

        <?php } ?>

    </div>
</div>

<?php require_once "includes/footer.php"; ?>
