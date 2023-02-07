<?php

require_once('guest_header.php');

// Define wording
DEFINE("WORDING_PAYMENT_FAILED", "<br><h2>There was an error verifying your payment. Please contact us for more information.</h2>");

// Setup Stripe
//  Defaulting to company id of 1 (as multi-company is being removed)
$stripe_vars = mysqli_fetch_array(mysqli_query($mysqli, "SELECT config_stripe_enable, config_stripe_publishable, config_stripe_secret, config_stripe_account FROM settings WHERE company_id = 1"));
$config_stripe_enable = intval($stripe_vars['config_stripe_enable']);
$config_stripe_publishable = htmlentities($stripe_vars['config_stripe_publishable']);
$config_stripe_secret = htmlentities($stripe_vars['config_stripe_secret']);
$config_stripe_account = intval($stripe_vars['config_stripe_account']);

$os = trim(strip_tags(mysqli_real_escape_string($mysqli, getOS($user_agent))));
$browser = trim(strip_tags(mysqli_real_escape_string($mysqli, getWebBrowser($user_agent))));

// Check Stripe is configured
if ($config_stripe_enable == 0 || $config_stripe_account == 0 || empty($config_stripe_publishable) || empty($config_stripe_secret)) {
    echo "<br><h2>Stripe payments not enabled/configured</h2>";
    require_once('guest_footer.php');
    exit();
}

// Show payment form
//  Users are directed to this page with the invoice_id and url_key params to make a payment
if (isset($_GET['invoice_id'], $_GET['url_key']) && !isset($_GET['payment_intent'])) {

    $invoice_url_key = mysqli_real_escape_string($mysqli, $_GET['url_key']);
    $invoice_id = intval($_GET['invoice_id']);

    // Query invoice details
    $sql = mysqli_query(
        $mysqli,
        "SELECT * FROM invoices
        LEFT JOIN clients ON invoice_client_id = client_id
        LEFT JOIN companies ON invoices.company_id = companies.company_id
        LEFT JOIN settings ON settings.company_id = companies.company_id
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
        require_once('guest_footer.php');
        exit();
    }

    // Process invoice, client and company details/settings
    $row = mysqli_fetch_array($sql);
    $invoice_id = $row['invoice_id'];
    $invoice_prefix = htmlentities($row['invoice_prefix']);
    $invoice_number = htmlentities($row['invoice_number']);
    $invoice_status = htmlentities($row['invoice_status']);
    $invoice_date = $row['invoice_date'];
    $invoice_due = $row['invoice_due'];
    $invoice_amount = floatval($row['invoice_amount']);
    $invoice_currency_code = htmlentities($row['invoice_currency_code']);
    $client_id = $row['client_id'];
    $client_name = htmlentities($row['client_name']);
    $company_locale = htmlentities($row['company_locale']);

    // Add up all the payments for the invoice and get the total amount paid to the invoice
    $sql_amount_paid = mysqli_query($mysqli, "SELECT SUM(payment_amount) AS amount_paid FROM payments WHERE payment_invoice_id = $invoice_id");
    $row = mysqli_fetch_array($sql_amount_paid);
    $amount_paid = $row['amount_paid'];
    $balance_to_pay = $invoice_amount - $amount_paid;

    // Get invoice items
    $sql_invoice_items = mysqli_query($mysqli, "SELECT * FROM invoice_items WHERE item_invoice_id = $invoice_id ORDER BY item_id ASC");

    // Set Currency Formatting
    $currency_format = numfmt_create($company_locale, NumberFormatter::CURRENCY);

    ?>

    <!-- Include Stripe JS (must be Stripe-hosted, not local) -->
    <script src="https://js.stripe.com/v3/"></script>

    <!-- jQuery -->
    <script src="plugins/jquery/jquery.min.js"></script>

    <br><br>

    <div class="row">

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
                        $item_name = htmlentities($row['item_name']);
                        $item_quantity = floatval($row['item_quantity']);
                        $item_total = floatval($row['item_total']);
                        ?>

                        <tr>
                            <td><?php echo $item_name; ?></td>
                            <td><?php echo $item_quantity; ?></td>
                            <td class="text-right"><?php echo numfmt_format_currency($currency_format, $item_total, $invoice_currency_code); ?></td>
                        </tr>

                    <?php } ?>

                    </tbody>
                </table>
            </div>
            <i><?php if (intval($amount_paid) > 0) { ?> Already paid: <?php echo numfmt_format_currency($currency_format, $amount_paid, $invoice_currency_code); } ?></i>
        </div>
        <!-- End invoice details-->

        <!-- Show Stripe payment form -->
        <div class="col-sm offset-1">
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
                <button type="submit" id="submit" class="btn btn-primary text-bold" hidden="hidden">
                    <div class="spinner hidden" id="spinner"></div>
                    <span id="button-text">Pay Invoice</span>
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
    $pi_id = mysqli_real_escape_string($mysqli, $_GET['payment_intent']);
    $pi_cs = $_GET['payment_intent_client_secret'];

    // Initialize stripe
    require_once('vendor/stripe-php-10.5.0/init.php');
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
    $pi_currency = mysqli_real_escape_string($mysqli, $pi_obj->currency);
    $pi_livemode = $pi_obj->livemode;

    // Get/Check invoice (& client/primary contact)
    $invoice_sql = mysqli_query(
        $mysqli,
        "SELECT * FROM invoices
        LEFT JOIN clients ON invoice_client_id = client_id
        LEFT JOIN contacts ON contact_id = primary_contact
        LEFT JOIN companies ON invoices.company_id = companies.company_id
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
    $invoice_prefix = htmlentities($row['invoice_prefix']);
    $invoice_number = htmlentities($row['invoice_number']);
    $invoice_amount = floatval($row['invoice_amount']);
    $invoice_currency_code = htmlentities($row['invoice_currency_code']);
    $invoice_url_key = htmlentities($row['invoice_url_key']);
    $invoice_company_id = intval($row['company_id']);
    $client_id = $row['client_id'];
    $client_name = htmlentities($row['client_name']);
    $contact_name = $row['contact_name'];
    $contact_email = $row['contact_email'];
    $company_name = htmlentities($row['company_name']);
    $company_phone = htmlentities($row['company_phone']);
    $company_locale = htmlentities($row['company_locale']);

    // Set Currency Formatting
    $currency_format = numfmt_create($company_locale, NumberFormatter::CURRENCY);

    // Add up all the payments for the invoice and get the total amount paid to the invoice already (if any)
    $sql_amount_paid_previously = mysqli_query($mysqli, "SELECT SUM(payment_amount) AS amount_paid FROM payments WHERE payment_invoice_id = $invoice_id");
    $row = mysqli_fetch_array($sql_amount_paid_previously);
    $amount_paid_previously = $row['amount_paid'];
    $balance_to_pay = $invoice_amount - $amount_paid_previously;

    // Sanity check that the amount paid is exactly the invoice outstanding balance
    if (intval($balance_to_pay) !== intval($pi_amount_paid)) {
        exit("Something went wrong confirming this payment. Please get in touch.");
    }

    // Apply payment

    // Update Invoice Status
    mysqli_query($mysqli, "UPDATE invoices SET invoice_status = 'Paid' WHERE invoice_id = $invoice_id AND company_id = $invoice_company_id");

    // Add Payment to History
    mysqli_query($mysqli, "INSERT INTO payments SET payment_date = '$pi_date', payment_amount = '$pi_amount_paid', payment_currency_code = '$pi_currency', payment_account_id = $config_stripe_account, payment_method = 'Stripe', payment_reference = 'Stripe - $pi_id', payment_invoice_id = $invoice_id, company_id = $invoice_company_id");
    mysqli_query($mysqli, "INSERT INTO history SET history_status = 'Paid', history_description = 'Payment added - $ip - $os - $browser', history_invoice_id = $invoice_id, company_id = $invoice_company_id");

    // Logging
    $extended_log_desc = '';
    if (!$pi_livemode) {
        $extended_log_desc = '(DEV MODE)';
    }
    mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Payment', log_action = 'Create', log_description = 'Stripe payment of $pi_currency $pi_amount_paid against invoice $invoice_prefix$invoice_number - $pi_id $extended_log_desc', log_ip = '$ip', log_user_agent = '$user_agent', log_client_id = $pi_client_id, company_id = $invoice_company_id");

    // Send email receipt
    $sql_settings = mysqli_query($mysqli, "SELECT * FROM settings WHERE company_id = $invoice_company_id");
    $row = mysqli_fetch_array($sql_settings);

    $config_smtp_host = $row['config_smtp_host'];
    $config_smtp_port = $row['config_smtp_port'];
    $config_smtp_encryption = $row['config_smtp_encryption'];
    $config_smtp_username = $row['config_smtp_username'];
    $config_smtp_password = $row['config_smtp_password'];
    $config_mail_from_email = $row['config_mail_from_email'];
    $config_mail_from_name = $row['config_mail_from_name'];
    $config_invoice_from_name = $row['config_invoice_from_name'];
    $config_invoice_from_email = $row['config_invoice_from_email'];

    if (!empty($config_smtp_host)) {
        $subject = "Payment Received - Invoice $invoice_prefix$invoice_number";
        $body    = "Hello $contact_name,<br><br>We have received your payment in the amount of " . $pi_currency . $pi_amount_paid . " for invoice <a href='https://$config_base_url/guest_view_invoice.php?invoice_id=$invoice_id&url_key=$invoice_url_key'>$invoice_prefix$invoice_number</a>. Please keep this email as a receipt for your records.<br><br>Amount: " . numfmt_format_currency($currency_format, $pi_amount_paid, $invoice_currency_code) . "<br>Balance: " . numfmt_format_currency($currency_format, '0', $invoice_currency_code) . "<br><br>Thank you for your business!<br><br><br>~<br>$company_name<br>Billing Department<br>$config_invoice_from_email<br>$company_phone";

        $mail = sendSingleEmail($config_smtp_host, $config_smtp_username, $config_smtp_password, $config_smtp_encryption, $config_smtp_port,
            $config_invoice_from_email, $config_invoice_from_name,
            $contact_email, $contact_name,
            $subject, $body
        );

        // Email Logging
        if ($mail === true) {
            mysqli_query($mysqli, "INSERT INTO history SET history_status = 'Sent', history_description = 'Emailed Receipt!', history_invoice_id = $invoice_id, company_id = $invoice_company_id");
        } else {
            mysqli_query($mysqli, "INSERT INTO history SET history_status = 'Sent', history_description = 'Email Receipt Failed!', history_invoice_id = $invoice_id, company_id = $invoice_company_id");

            mysqli_query($mysqli, "INSERT INTO notifications SET notification_type = 'Mail', notification = 'Failed to send email to $contact_email', notification_timestamp = NOW(), company_id = $invoice_company_id");
            mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Mail', log_action = 'Error', log_description = 'Failed to send email to $contact_email regarding $subject. $mail', log_ip = '$ip', log_user_agent = '$user_agent', company_id = $invoice_company_id");
        }
    }

    // Redirect user to invoice
    header('Location: //' . $config_base_url . '/guest_view_invoice.php?invoice_id=' . $pi_invoice_id . '&url_key=' . $invoice_url_key);


} else {
    echo "<br><h2>Oops, something went wrong! Please raise a ticket if you believe this is an error.</h2>";
}


require_once('guest_footer.php');
