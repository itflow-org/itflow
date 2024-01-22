<?php

require_once 'guest_header.php';

function log_to_console($message)
{
    $message = date("H:i:s") . " - $message - ".PHP_EOL;
    print($message);
    flush();
    ob_flush();
}


// Define wording
DEFINE("WORDING_PAYMENT_FAILED", "<br><h2>There was an error verifying your payment. Please contact us for more information.</h2>");

// Setup Stripe
$stripe_vars = mysqli_fetch_array(mysqli_query($mysqli, "SELECT config_stripe_enable, config_stripe_publishable, config_stripe_secret, config_stripe_account, config_stripe_client_pays_fees FROM settings WHERE company_id = 1"));
$config_stripe_enable = intval($stripe_vars['config_stripe_enable']);
$config_stripe_publishable = nullable_htmlentities($stripe_vars['config_stripe_publishable']);
$config_stripe_secret = nullable_htmlentities($stripe_vars['config_stripe_secret']);
$config_stripe_account = intval($stripe_vars['config_stripe_account']);
$config_stripe_client_pays_fees = intval($stripe_vars['config_stripe_client_pays_fees']);

// Check Stripe is configured
if ($config_stripe_enable == 0 || $config_stripe_account == 0 || empty($config_stripe_publishable) || empty($config_stripe_secret)) {
    echo "<br><h2>Stripe payments not enabled/configured</h2>";
    require_once 'guest_footer.php';

    exit();
}

// Show payment form
//  Users are directed to this page with the invoice_id and url_key params to make a payment
if (isset($_GET['invoice_id'], $_GET['url_key']) && !isset($_GET['payment_intent'])) {

    $invoice_url_key = sanitizeInput($_GET['url_key']);
    $invoice_id = intval($_GET['invoice_id']);

    // Query invoice details
    $sql = mysqli_query(
        $mysqli,
        "SELECT * FROM invoices
        LEFT JOIN clients ON invoice_client_id = client_id
        WHERE invoice_id = $invoice_id
        AND invoice_url_key = '$invoice_url_key'
        AND invoice_status != 'Draft'
        AND invoice_status != 'Paid'
        AND invoice_status != 'Cancelled'
        LIMIT 1"
    );

    // Ensure we have a valid invoice
    if (!$sql || mysqli_num_rows($sql) !== 1) {
        echo "<br><h2>Oops, something went wrong! Please ensure you have the correct URL and have not already paid this invoice.</h2>";
        require_once 'guest_footer.php';

        exit();
    }

    // Process invoice, client and company details/settings
    $row = mysqli_fetch_array($sql);
    $invoice_id = intval($row['invoice_id']);
    $invoice_prefix = nullable_htmlentities($row['invoice_prefix']);
    $invoice_number = intval($row['invoice_number']);
    $invoice_status = nullable_htmlentities($row['invoice_status']);
    $invoice_date = nullable_htmlentities($row['invoice_date']);
    $invoice_due = nullable_htmlentities($row['invoice_due']);
    $invoice_discount = floatval($row['invoice_discount_amount']);
    $invoice_amount = floatval($row['invoice_amount']);
    $invoice_currency_code = nullable_htmlentities($row['invoice_currency_code']);
    $client_id = intval($row['client_id']);
    $client_name = nullable_htmlentities($row['client_name']);
    
    $sql = mysqli_query($mysqli, "SELECT * FROM companies, settings WHERE companies.company_id = settings.company_id AND companies.company_id = 1");
    $row = mysqli_fetch_array($sql);
    $company_locale = nullable_htmlentities($row['company_locale']);

    // Add up all the payments for the invoice and get the total amount paid to the invoice
    $sql_amount_paid = mysqli_query($mysqli, "SELECT SUM(payment_amount) AS amount_paid FROM payments WHERE payment_invoice_id = $invoice_id");
    $row = mysqli_fetch_array($sql_amount_paid);
    $amount_paid = floatval($row['amount_paid']);
    $balance_to_pay = $invoice_amount - $amount_paid;

    // Check config to see if client pays fees is enabled
    if ($config_stripe_client_pays_fees == 1) {
            $balance_before_fees = $balance_to_pay;
            $percentage_fee = 0.029;
            $flat_fee = 0.30;
            // Calculate the amount to charge the client
            $balance_to_pay = ($balance_to_pay + $flat_fee) / (1 - $percentage_fee);
            // Calculate the fee amount
            $gateway_fee = round($balance_to_pay - $balance_before_fees, 2);

        }
    
    //Round balance to pay to 2 decimal places
    $balance_to_pay = round($balance_to_pay, 2);

    // Get invoice items
    $sql_invoice_items = mysqli_query($mysqli, "SELECT * FROM invoice_items WHERE item_invoice_id = $invoice_id ORDER BY item_id ASC");

    // Set Currency Formatting
    $currency_format = numfmt_create($company_locale, NumberFormatter::CURRENCY);

    ?>

    <!-- Include Stripe JS (must be Stripe-hosted, not local) -->
    <script src="https://js.stripe.com/v3/"></script>

    <!-- jQuery -->
    <script src="plugins/jquery/jquery.min.js"></script>

    <div class="row pt-5">

        <!-- Show invoice details -->
        <div class="col-sm">
            <h3>Payment for Invoice: <?php echo $invoice_prefix . $invoice_number ?></h3>
            <br>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                    <tr>
                        <th>Product</th>
                        <th class="text-center">Qty</th>
                        <th class="text-right">Total</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php

                    $item_total = 0;

                    while ($row = mysqli_fetch_array($sql_invoice_items)) {
                        $item_name = nullable_htmlentities($row['item_name']);
                        $item_quantity = floatval($row['item_quantity']);
                        $item_total = floatval($row['item_total']);
                        ?>

                        <tr>
                            <td><?php echo $item_name; ?></td>
                            <td class="text-center"><?php echo $item_quantity; ?></td>
                            <td class="text-right"><?php echo numfmt_format_currency($currency_format, $item_total, $invoice_currency_code); ?></td>
                        </tr>

                    <?php }
                    if ($config_stripe_client_pays_fees == 1) { ?>
                    
                        <tr>
                            <td>Gateway Fees</td>
                            <td class="text-center">-</td>
                            <td class="text-right"><?php echo numfmt_format_currency($currency_format, $gateway_fee, $invoice_currency_code); ?></td>
                        </tr>
                    <?php } ?>



                    </tbody>
                </table>
            </div>
            <br>
            <i><?php if ($invoice_discount > 0){ echo "Discount: " . numfmt_format_currency($currency_format, $invoice_discount, $invoice_currency_code); } ?>
            </i>
            <br>
            <i><?php if (intval($amount_paid) > 0) { ?> Already paid: <?php echo numfmt_format_currency($currency_format, $amount_paid, $invoice_currency_code); } ?></i>
        </div>
        <!-- End invoice details-->

        <!-- Show Stripe payment form -->
        <div class="col-sm offset-sm-1">
            <h1>Payment Total:</h1>
            <form id="payment-form">
                <h1><?php echo numfmt_format_currency($currency_format, $balance_to_pay, $invoice_currency_code); ?></h1>
                <input type="hidden" id="stripe_publishable_key" value="<?php echo $config_stripe_publishable ?>">
                <input type="hidden" id="invoice_id" value="<?php echo $invoice_id ?>">
                <input type="hidden" id="url_key" value="<?php echo $invoice_url_key ?>">
                <br>
                <div id="link-authentication-element">
                    <!--Stripe.js injects the Link Authentication Element-->
                </div>
                <div id="payment-element">
                    <!--Stripe.js injects the Payment Element-->
                </div>
                <br>
                <button type="submit" id="submit" class="btn btn-primary btn-lg btn-block text-bold" hidden="hidden">
                    <div class="spinner hidden" id="spinner"></div>
                    <span id="button-text"><i class="fas fa-check mr-2"></i>Pay Invoice</span>
                </button>
                <div id="payment-message" class="hidden"></div>
            </form>
        </div>
        <!-- End Stripe payment form -->

    </div>

    <!-- Include local JS that powers stripe -->
    <script src="js/guest_pay_invoice_stripe.js"></script>

    <?php

// Process payment & redirect user back to invoice
//  (Stripe will redirect back to this page upon payment success with the payment_intent and payment_intent_client_secret params set
} elseif (isset($_GET['payment_intent'], $_GET['payment_intent_client_secret'])) {

    // Params from GET
    $pi_id = sanitizeInput($_GET['payment_intent']);
    $pi_cs = $_GET['payment_intent_client_secret'];

    // Initialize stripe
    require_once 'vendor/stripe-php-10.5.0/init.php';

    \Stripe\Stripe::setApiKey($config_stripe_secret);

    // Check details of the PI
    $pi_obj = \Stripe\PaymentIntent::retrieve($pi_id);

    if ($pi_obj->client_secret !== $pi_cs) {
        exit(WORDING_PAYMENT_FAILED);
    } elseif ($pi_obj->status !== "succeeded") {
        exit(WORDING_PAYMENT_FAILED);
    } elseif ($pi_obj->amount !== $pi_obj->amount_received) {
        // The invoice wasn't paid in full
        // this should be flagged for manual review as would indicate something weird happening
        exit(WORDING_PAYMENT_FAILED);
    }

    // Get details from PI
    $pi_date = date('Y-m-d', $pi_obj->created);
    $pi_invoice_id = intval($pi_obj->metadata->itflow_invoice_id);
    $pi_client_id = intval($pi_obj->metadata->itflow_client_id);
    $pi_amount_paid = floatval(($pi_obj->amount_received / 100));
    $pi_currency = strtoupper(sanitizeInput($pi_obj->currency));
    $pi_livemode = $pi_obj->livemode;

    // Get/Check invoice (& client/primary contact)
    $invoice_sql = mysqli_query(
        $mysqli,
        "SELECT * FROM invoices
        LEFT JOIN clients ON invoice_client_id = client_id
        LEFT JOIN contacts ON clients.client_id = contacts.contact_client_id AND contact_primary = 1
        WHERE invoice_id = $pi_invoice_id
        AND invoice_status != 'Draft'
        AND invoice_status != 'Paid'
        AND invoice_status != 'Cancelled'
        LIMIT 1"
    );
    if (!$invoice_sql || mysqli_num_rows($invoice_sql) !== 1) {
        exit(WORDING_PAYMENT_FAILED);
    }

    // Invoice exists - get details
    $row = mysqli_fetch_array($invoice_sql);
    $invoice_id = intval($row['invoice_id']);
    $invoice_prefix = sanitizeInput($row['invoice_prefix']);
    $invoice_number = intval($row['invoice_number']);
    $invoice_amount = floatval($row['invoice_amount']);
    $invoice_currency_code = sanitizeInput($row['invoice_currency_code']);
    $invoice_url_key = sanitizeInput($row['invoice_url_key']);
    $client_id = intval($row['client_id']);
    $client_name = sanitizeInput($row['client_name']);
    $contact_name = sanitizeInput($row['contact_name']);
    $contact_email = sanitizeInput($row['contact_email']);
    
    $sql_company = mysqli_query($mysqli, "SELECT * FROM companies WHERE company_id = 1");
    $row = mysqli_fetch_array($sql_company);

    $company_name = sanitizeInput($row['company_name']);
    $company_phone = sanitizeInput(formatPhoneNumber($row['company_phone']));
    $company_locale = sanitizeInput($row['company_locale']);

    // Set Currency Formatting
    $currency_format = numfmt_create($company_locale, NumberFormatter::CURRENCY);

    // Add up all the payments for the invoice and get the total amount paid to the invoice already (if any)
    $sql_amount_paid_previously = mysqli_query($mysqli, "SELECT SUM(payment_amount) AS amount_paid FROM payments WHERE payment_invoice_id = $invoice_id");
    $row = mysqli_fetch_array($sql_amount_paid_previously);
    $amount_paid_previously = $row['amount_paid'];
    $balance_to_pay = $invoice_amount - $amount_paid_previously;

    // Check config to see if client pays fees is enabled
    if ($config_stripe_client_pays_fees == 1) {
        $percentage_fee = 0.029;
        $flat_fee = 0.30;
        // Calculate the amount to charge the client
        $balance_to_pay = ($balance_to_pay + $flat_fee) / (1 - $percentage_fee);
    }

    // Round balance to pay to 2 decimal places
    $balance_to_pay = round($balance_to_pay, 2);

    // Sanity check that the amount paid is exactly the invoice outstanding balance
    if (intval($balance_to_pay) !== intval($pi_amount_paid)) {
        exit("Something went wrong confirming this payment. Please get in touch.");
    }

    // Apply payment

    // Update Invoice Status
    mysqli_query($mysqli, "UPDATE invoices SET invoice_status = 'Paid' WHERE invoice_id = $invoice_id");

    // Add Payment to History
    mysqli_query($mysqli, "INSERT INTO payments SET payment_date = '$pi_date', payment_amount = $pi_amount_paid, payment_currency_code = '$pi_currency', payment_account_id = $config_stripe_account, payment_method = 'Stripe', payment_reference = 'Stripe - $pi_id', payment_invoice_id = $invoice_id");
    mysqli_query($mysqli, "INSERT INTO history SET history_status = 'Paid', history_description = 'Payment added - $ip - $os - $browser', history_invoice_id = $invoice_id");

    // Add Gateway fees to history if applicable
    if ($config_stripe_client_pays_fees == 1) {
        mysqli_query($mysqli, "INSERT INTO history SET history_status = 'Paid', history_description = 'Gateway fees of $gateway_fee has been billed', history_invoice_id = $invoice_id");
    }

    // Notify
    mysqli_query($mysqli, "INSERT INTO notifications SET notification_type = 'Invoice Paid', notification = 'Invoice $invoice_prefix$invoice_number has been paid - $ip - $os - $browser', notification_action = 'invoice.php?invoice_id=$invoice_id', notification_client_id = $pi_client_id");

    // Logging
    $extended_log_desc = '';
    if (!$pi_livemode) {
        $extended_log_desc = '(DEV MODE)';
    }
    if ($config_stripe_client_pays_fees == 1) {
        $extended_log_desc .= ' (Client Pays Fees [' . numfmt_format_currency($currency_format, $gateway_fee, $invoice_currency_code) . ']])';
    }
    mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Payment', log_action = 'Create', log_description = 'Stripe payment of $pi_currency $pi_amount_paid against invoice $invoice_prefix$invoice_number - $pi_id $extended_log_desc', log_ip = '$ip', log_user_agent = '$user_agent', log_client_id = $pi_client_id");

    

    // Send email receipt
    $sql_settings = mysqli_query($mysqli, "SELECT * FROM settings WHERE company_id = 1");
    $row = mysqli_fetch_array($sql_settings);

    $config_smtp_host = $row['config_smtp_host'];
    $config_smtp_port = intval($row['config_smtp_port']);
    $config_smtp_encryption = $row['config_smtp_encryption'];
    $config_smtp_username = $row['config_smtp_username'];
    $config_smtp_password = $row['config_smtp_password'];
    $config_invoice_from_name = sanitizeInput($row['config_invoice_from_name']);
    $config_invoice_from_email = sanitizeInput($row['config_invoice_from_email']);
    
    $config_base_url = sanitizeInput($config_base_url);

    if (!empty($config_smtp_host)) {
        $subject = "Payment Received - Invoice $invoice_prefix$invoice_number";
        $body = "Hello $contact_name,<br><br>We have received your payment in the amount of " . $pi_currency . $pi_amount_paid . " for invoice <a href=\'https://$config_base_url/guest_view_invoice.php?invoice_id=$invoice_id&url_key=$invoice_url_key\'>$invoice_prefix$invoice_number</a>. Please keep this email as a receipt for your records.<br><br>Amount: " . numfmt_format_currency($currency_format, $pi_amount_paid, $invoice_currency_code) . "<br>Balance: " . numfmt_format_currency($currency_format, '0', $invoice_currency_code) . "<br><br>Thank you for your business!<br><br><br>~<br>$company_name - Billing<br>$config_invoice_from_email<br>$company_phone";

            $data = [
                [
                    'from' => $config_invoice_from_email,
                    'from_name' => $config_invoice_from_name,
                    'recipient' => $contact_email,
                    'recipient_name' => $contact_name,
                    'subject' => $subject,
                    'body' => $body,
                ]
            ];
        $mail = addToMailQueue($mysqli, $data);

        // Email Logging
        if ($mail === true) {
            mysqli_query($mysqli, "INSERT INTO history SET history_status = 'Sent', history_description = 'Emailed Receipt!', history_invoice_id = $invoice_id");
        } else {
            mysqli_query($mysqli, "INSERT INTO history SET history_status = 'Sent', history_description = 'Email Receipt Failed!', history_invoice_id = $invoice_id");

            mysqli_query($mysqli, "INSERT INTO notifications SET notification_type = 'Mail', notification = 'Failed to send email to $contact_email'");
            mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Mail', log_action = 'Error', log_description = 'Failed to send email to $contact_email regarding $subject. $mail'");
        }
    }

    // Redirect user to invoice
    header('Location: //' . $config_base_url . '/guest_view_invoice.php?invoice_id=' . $pi_invoice_id . '&url_key=' . $invoice_url_key);


} else {
    echo "<br><h2>Oops, something went wrong! Please raise a ticket if you believe this is an error.</h2>";
}


require_once 'guest_footer.php';

