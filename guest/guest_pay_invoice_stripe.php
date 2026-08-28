<?php

require_once 'includes/inc_all_guest.php';

DEFINE("WORDING_PAYMENT_FAILED", "<br><h2>There was an error verifying your payment. Please contact us for more information before attempting payment again.</h2>");

// --- Get Stripe config from payment_providers table ---
$stripe_provider = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT payment_provider_account, payment_provider_private_key, payment_provider_public_key FROM payment_providers"));


$stripe_publishable      = escapeHtml($stripe_provider['payment_provider_public_key']);
$stripe_secret           = escapeHtml($stripe_provider['payment_provider_private_key']);
$stripe_account          = intval($stripe_provider['payment_provider_account']);

// Show payment form
if (isset($_GET['invoice_id'], $_GET['url_key']) && !isset($_GET['payment_intent'])) {

    $invoice_url_key = escapeSql($_GET['url_key']);
    $invoice_id      = intval($_GET['invoice_id']);

    // Query invoice details
    $sql = mysqli_query(
        $mysqli,
        "SELECT client_id, client_name, invoice_amount, invoice_currency_code, invoice_date,
            invoice_discount_amount, invoice_due, invoice_id, invoice_number, invoice_prefix,
            invoice_status FROM invoices
         LEFT JOIN clients ON invoice_client_id = client_id
         WHERE invoice_id = $invoice_id
         AND invoice_url_key = '$invoice_url_key'
         AND invoice_status NOT IN ('Draft', 'Paid', 'Cancelled')
         LIMIT 1"
    );

    // Ensure valid invoice
    if (!$sql || mysqli_num_rows($sql) !== 1) {
        echo "<br><h2>Oops, something went wrong! Please ensure you have the correct URL and have not already paid this invoice.</h2>";
        require_once 'includes/guest_footer.php';
        error_log("Stripe payment error - Invoice with ID $invoice_id not found or not eligible.");
        exit();
    }

    $row = mysqli_fetch_assoc($sql);
    $invoice_id            = intval($row['invoice_id']);
    $invoice_prefix        = escapeHtml($row['invoice_prefix']);
    $invoice_number        = intval($row['invoice_number']);
    $invoice_status        = escapeHtml($row['invoice_status']);
    $invoice_date          = escapeHtml($row['invoice_date']);
    $invoice_due           = escapeHtml($row['invoice_due']);
    $invoice_discount      = floatval($row['invoice_discount_amount']);
    $invoice_amount        = floatval($row['invoice_amount']);
    $invoice_currency_code = escapeHtml($row['invoice_currency_code']);
    $client_id             = intval($row['client_id']);
    $client_name           = escapeHtml($row['client_name']);

    // Company info for currency formatting, etc
    $sql_company = mysqli_query($mysqli, "SELECT * FROM companies WHERE company_id = 1");
    $company_row = mysqli_fetch_assoc($sql_company);
    $company_locale = escapeHtml($company_row['company_locale']);

    // Add up all payments made to the invoice
    $sql_amount_paid = mysqli_query($mysqli, "SELECT SUM(payment_amount) AS amount_paid FROM payments WHERE payment_invoice_id = $invoice_id");
    $amount_paid = floatval(mysqli_fetch_assoc($sql_amount_paid)['amount_paid']);
    $balance_to_pay = round($invoice_amount - $amount_paid, 2);

    // Get invoice items
    $sql_invoice_items = mysqli_query($mysqli, "SELECT item_name, item_quantity, item_total FROM invoice_items WHERE item_invoice_id = $invoice_id ORDER BY item_id ASC");

    // Currency formatting
    $currency_format = numfmt_create($company_locale, NumberFormatter::CURRENCY);

    ?>

    <!-- Stripe & jQuery -->
    <script src="https://js.stripe.com/v3/"></script>

    <div class="row py-5">
        <div class="col-sm">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Payment for Invoice: <strong><?= "$invoice_prefix$invoice_number" ?></strong></h3>
                </div>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                        <tr>
                            <th>Product</th>
                            <th class="text-center">Qty</th>
                            <th class="text-end">Total</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php
                        while ($row = mysqli_fetch_assoc($sql_invoice_items)) {
                            $item_name = escapeHtml($row['item_name']);
                            $item_quantity = floatval($row['item_quantity']);
                            $item_total = floatval($row['item_total']);
                        ?>
                            <tr>
                                <td><?= $item_name ?></td>
                                <td class="text-center"><?= $item_quantity ?></td>
                                <td class="text-end"><?= numfmt_format_currency($currency_format, $item_total, $invoice_currency_code) ?></td>
                            </tr>
                        <?php } ?>
                        <?php if ($invoice_discount > 0) { ?>
                            <tr class="text-end">
                                <td colspan="2">Discount</td>
                                <td>
                                    <?= numfmt_format_currency($currency_format, $invoice_discount, $invoice_currency_code) ?>
                                </td>
                            </tr>
                        <?php } ?>
                        <?php if (intval($amount_paid) > 0) { ?>
                            <tr class="text-end">
                                <td colspan="2">Paid</td>
                                <td>
                                    <?= numfmt_format_currency($currency_format, $amount_paid, $invoice_currency_code) ?>
                                </td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-sm offset-sm-1">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Payment Total: <strong><?= numfmt_format_currency($currency_format, $balance_to_pay, $invoice_currency_code) ?></strong></h3>
                </div>
                <div class="card-body">
                    <form id="payment-form">
                        <input type="hidden" id="stripe_publishable_key" value="<?= $stripe_publishable ?>">
                        <input type="hidden" id="invoice_id" value="<?= $invoice_id ?>">
                        <input type="hidden" id="url_key" value="<?= $invoice_url_key ?>">
                        <div id="payment-element"></div>
                        <br>
                        <button type="submit" id="submit" class="btn btn-primary btn-lg w-100 text-bold" hidden="hidden">
                            <div class="spinner hidden" id="spinner"></div>
                            <span id="button-text"><i class="fas fa-check me-2"></i>Pay Invoice</span>
                        </button>
                        <div id="payment-message" class="d-none"></div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="../js/guest_pay_invoice_stripe.js"></script>

    <?php

// Payment result processing
} elseif (isset($_GET['payment_intent'], $_GET['payment_intent_client_secret'])) {

    $pi_id = escapeSql($_GET['payment_intent']);
    $pi_cs = $_GET['payment_intent_client_secret'];

    require_once '../includes/stripe_init.php';
    \Stripe\Stripe::setApiKey($stripe_secret);

    $pi_obj = \Stripe\PaymentIntent::retrieve($pi_id);

    if ($pi_obj->client_secret !== $pi_cs) {
        error_log("Stripe payment error - Payment intent ID/Secret mismatch for $pi_id");
        exit(WORDING_PAYMENT_FAILED);
    } elseif ($pi_obj->status !== "succeeded") {
        exit(WORDING_PAYMENT_FAILED);
    } elseif ($pi_obj->amount !== $pi_obj->amount_received) {
        error_log("Stripe payment error - payment amount does not match amount paid for $pi_id");
        exit(WORDING_PAYMENT_FAILED);
    }

    // PI details
    $pi_date = date('Y-m-d', $pi_obj->created);
    $pi_invoice_id = intval($pi_obj->metadata->itflow_invoice_id);
    $pi_client_id = intval($pi_obj->metadata->itflow_client_id);
    $pi_amount_paid = floatval(($pi_obj->amount_received / 100));
    $pi_currency = strtoupper(escapeSql($pi_obj->currency));
    $pi_livemode = $pi_obj->livemode;

    // Get/Check invoice (& client/primary contact)
    $invoice_sql = mysqli_query(
        $mysqli,
        "SELECT client_id, client_name, contact_email, contact_name, invoice_amount, invoice_currency_code,
            invoice_id, invoice_number, invoice_prefix, invoice_url_key FROM invoices
         LEFT JOIN clients ON invoice_client_id = client_id
         LEFT JOIN contacts ON clients.client_id = contacts.contact_client_id AND contact_primary = 1
         WHERE invoice_id = $pi_invoice_id
         AND invoice_status NOT IN ('Draft', 'Paid', 'Cancelled')
         LIMIT 1"
    );
    if (!$invoice_sql || mysqli_num_rows($invoice_sql) !== 1) {
        error_log("Stripe payment error - Invoice with ID $pi_invoice_id is unknown/not eligible. PI $pi_id");
        exit(WORDING_PAYMENT_FAILED);
    }

    $row = mysqli_fetch_assoc($invoice_sql);
    $invoice_id = intval($row['invoice_id']);
    $invoice_prefix = escapeSql($row['invoice_prefix']);
    $invoice_number = intval($row['invoice_number']);
    $invoice_amount = floatval($row['invoice_amount']);
    $invoice_currency_code = escapeSql($row['invoice_currency_code']);
    $invoice_url_key = escapeSql($row['invoice_url_key']);
    $client_id = intval($row['client_id']);
    $client_name = escapeSql($row['client_name']);
    $contact_name = escapeSql($row['contact_name']);
    $contact_email = escapeSql($row['contact_email']);

    $sql_company = mysqli_query($mysqli, "SELECT company_locale, company_name, company_phone FROM companies WHERE company_id = 1");
    $row = mysqli_fetch_assoc($sql_company);
    $company_name = escapeSql($row['company_name']);
    $company_phone = escapeSql(formatPhoneNumber($row['company_phone']));
    $company_locale = escapeSql($row['company_locale']);

    $currency_format = numfmt_create($company_locale, NumberFormatter::CURRENCY);

    $sql_amount_paid_previously = mysqli_query($mysqli, "SELECT SUM(payment_amount) AS amount_paid FROM payments WHERE payment_invoice_id = $invoice_id");
    $amount_paid_previously = floatval(mysqli_fetch_assoc($sql_amount_paid_previously)['amount_paid']);
    $balance_to_pay = $invoice_amount - $amount_paid_previously;

    // Compare in whole cents - intval() truncates, which silently accepted an underpayment
    // of up to 99c as payment in full while rejecting a 1c overpayment.
    if ((int) round($balance_to_pay * 100) !== (int) round($pi_amount_paid * 100)) {
        error_log("Stripe payment error - Invoice balance does not match amount paid for $pi_id");
        exit(WORDING_PAYMENT_FAILED);
    }

    // Claim the invoice - the conditional UPDATE is the lock, and the row lock it takes
    // is what serialises concurrent requests carrying the same payment intent. A request
    // that loses the race matches 0 rows and must not book the payment a second time.
    mysqli_query($mysqli, "UPDATE invoices SET invoice_status = 'Paid' WHERE invoice_id = $invoice_id AND invoice_status NOT IN ('Draft', 'Paid', 'Cancelled')");
    if (mysqli_affected_rows($mysqli) !== 1) {
        error_log("Stripe payment - invoice $invoice_id was already settled by a concurrent request; skipping duplicate booking of $pi_id");
        header('Location: //' . $config_base_url . '/guest/guest_view_invoice.php?invoice_id=' . $invoice_id . '&url_key=' . $invoice_url_key);
        exit();
    }

    // Add Payment to History
    mysqli_query($mysqli, "INSERT INTO payments SET payment_date = '$pi_date', payment_amount = $pi_amount_paid, payment_currency_code = '$pi_currency', payment_account_id = $stripe_account, payment_method = 'Stripe', payment_reference = 'Stripe - $pi_id', payment_invoice_id = $invoice_id");
    
    mysqli_query($mysqli, "INSERT INTO history SET history_status = 'Paid', history_description = 'Online Payment added (client) - $ip - $os - $browser', history_invoice_id = $invoice_id");

    // Notify
    appNotify("Invoice Paid", "Invoice $invoice_prefix$invoice_number has been paid by $client_name - $ip - $os - $browser", "/agent/invoice.php?invoice_id=$invoice_id", $pi_client_id);

    triggerCustomAction('invoice_pay', $invoice_id);

    $extended_log_desc = '';
    if (!$pi_livemode) {
        $extended_log_desc = '(DEV MODE)';
    }
    mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Payment', log_action = 'Create', log_description = 'Stripe payment of $pi_currency $pi_amount_paid against invoice $invoice_prefix$invoice_number - $pi_id $extended_log_desc', log_ip = '$ip', log_user_agent = '$user_agent', log_client_id = $pi_client_id");

    // Email Receipt
    $sql_settings = mysqli_query($mysqli, "SELECT config_invoice_from_email, config_invoice_from_name,
        config_invoice_paid_notification_email, config_smtp_host FROM settings WHERE company_id = 1");
    $settings = mysqli_fetch_assoc($sql_settings);

    $config_smtp_host = $settings['config_smtp_host'];
    $config_invoice_from_name = escapeSql($settings['config_invoice_from_name']);
    $config_invoice_from_email = escapeSql($settings['config_invoice_from_email']);
    $config_invoice_paid_notification_email = escapeSql($settings['config_invoice_paid_notification_email']);

    if (!empty($config_smtp_host)) {
        $subject = "Payment Received - Invoice $invoice_prefix$invoice_number";
        $body = "Hello $contact_name,<br><br>We have received online payment for the amount of " . $pi_currency . $pi_amount_paid . " for invoice <a href=\'https://$config_base_url/guest/guest_view_invoice.php?invoice_id=$invoice_id&url_key=$invoice_url_key\'>$invoice_prefix$invoice_number</a>. Please keep this email as a receipt for your records.<br><br>Amount: " . numfmt_format_currency($currency_format, $pi_amount_paid, $invoice_currency_code) . "<br><br>Thank you for your business!<br><br><br>~<br>$company_name - Billing<br>$config_invoice_from_email<br>$company_phone";

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
        // Internal notification
        if (!empty($config_invoice_paid_notification_email)) {
            $subject_internal = "Payment Received - $client_name - Invoice $invoice_prefix$invoice_number";
            $body_internal = "This is a notification that an invoice has been paid in ITFlow. Below is a copy of the receipt sent to the client:-<br><br>--------<br><br>$body";
            $data[] = [
                'from' => $config_invoice_from_email,
                'from_name' => $config_invoice_from_name,
                'recipient' => $config_invoice_paid_notification_email,
                'recipient_name' => $contact_name,
                'subject' => $subject_internal,
                'body' => $body_internal,
            ];
        }
        $mail = addToMailQueue($data);
        // Email logging
        mysqli_query($mysqli, "INSERT INTO history SET history_status = 'Sent', history_description = 'Emailed Receipt!', history_invoice_id = $invoice_id");
    }

    // Redirect user to invoice
    header('Location: //' . $config_base_url . '/guest/guest_view_invoice.php?invoice_id=' . $invoice_id . '&url_key=' . $invoice_url_key);

} else {
    exit(WORDING_PAYMENT_FAILED);
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/footer.php';
