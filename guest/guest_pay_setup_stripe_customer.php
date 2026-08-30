<?php

require_once 'includes/inc_all_guest.php';

// --- Get Stripe config from payment_providers table ---
$stripe_provider = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT payment_provider_id, payment_provider_account, payment_provider_private_key, payment_provider_public_key FROM payment_providers"));

$stripe_provider_id = intval($stripe_provider['payment_provider_id']);
$stripe_publishable = escapeHtml($stripe_provider['payment_provider_public_key']);
$stripe_secret = escapeHtml($stripe_provider['payment_provider_private_key']);
$stripe_account  = intval($stripe_provider['payment_provider_account']);

// Show setup form
if (isset($_GET['invoice_id'], $_GET['url_key'])) {

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
    $client_id             = intval($row['client_id']);
    $client_name           = escapeHtml($row['client_name']);

    // Company info for currency formatting, etc
    $sql_company = mysqli_query($mysqli, "SELECT * FROM companies WHERE company_id = 1");
    $company_row = mysqli_fetch_assoc($sql_company);
    $company_locale = escapeHtml($company_row['company_locale']);

    // Get client's Stripe customer ID
    $stripe_customer_query = mysqli_query($mysqli, "
        SELECT payment_provider_client FROM client_payment_provider
        WHERE client_id = $client_id AND payment_provider_id = $stripe_provider_id
        LIMIT 1
    ");
    $stripe_customer = mysqli_fetch_assoc($stripe_customer_query);
    $stripe_customer_id = $stripe_customer ? escapeSql($stripe_customer['payment_provider_client']) : null;    

    if (!$stripe_customer_id) { ?>
            <br><br>
            <h2>Setup Stripe payments for <?php echo $client_name; ?></h2>
            In order to make online payments, please create a Stripe customer record for <?php echo $client_name; ?>.
            <p>If you save payment details in future, you also grant consent for automatic payments.</p>

            <div class="row g-3">
                <div class="col-12 col-md-8 col-lg-6">
                    <form action="guest_post.php" method="POST">
                        <input type="hidden" name="invoice_id" value="<?php echo $invoice_id; ?>">
                        <input type="hidden" name="url_key" value="<?php echo escapeHtml($invoice_url_key); ?>">

                        <div class="mb-3">
                            <div class="form-floating">
                                <input type="text" class="form-control" id="stripe_cust_name" disabled value="<?php echo $client_name; ?>">
                                <label for="stripe_cust_name">Client</label>
                            </div>
                        </div>                        

                        <div class="mb-3">
                            <div class="form-floating">
                                <input type="text" class="form-control" id="stripe_name" name="name" placeholder="Name" autocomplete="name" required>
                                <label for="stripe_name">Your Name</label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="form-floating">
                                <input type="email" class="form-control" id="stripe_email" name="email" placeholder="Email" autocomplete="email" required>
                                <label for="stripe_email">Your Email</label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <small class="text-muted d-block mt-2">Stripe processes your information in accordance with its Privacy Policy and Terms.</small>
                        </div>

                        <div class="mb-3">
                            <button type="submit" class="btn btn-success" name="create_stripe_customer"><strong><i class="fas fa-check me-2"></i>Continue</strong></button>
                        </div>
                    </form>
                </div>
            </div>

        <?php }
        else {
            echo "<br><h2>Stripe customer record already exists.</h2>";
            echo "<p>You can now proceed to pay your invoice.</p>";
            echo "<a href='guest_view_invoice.php?invoice_id=$invoice_id&url_key=" . urlencode($invoice_url_key) . "' class='btn btn-primary'>View Invoice</a>";
        }

} else {
    exit("Error.");
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/footer.php';
