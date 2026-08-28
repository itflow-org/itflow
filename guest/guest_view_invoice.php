<?php

require_once "includes/inc_all_guest.php";

if (!isset($_GET['invoice_id'], $_GET['url_key'])) {
    echo "<br><h2>Oops, something went wrong! Please raise a ticket if you believe this is an error.</h2>";
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/footer.php';

    exit();
}

$url_key = escapeSql($_GET['url_key']);
$invoice_id = intval($_GET['invoice_id']);

$sql = mysqli_query(
    $mysqli,
    "SELECT client_currency_code, client_id, client_name, client_net_terms, client_website,
        contact_email, contact_extension, contact_mobile, contact_mobile_country_code,
        contact_phone, contact_phone_country_code, invoice_amount, invoice_category_id,
        invoice_currency_code, invoice_date, invoice_discount_amount, invoice_due, invoice_id,
        invoice_note, invoice_number, invoice_prefix, invoice_status, location_address,
        location_city, location_country, location_state, location_zip FROM invoices
    LEFT JOIN clients ON invoice_client_id = client_id
    LEFT JOIN locations ON clients.client_id = locations.location_client_id AND location_primary = 1
    LEFT JOIN contacts ON clients.client_id = contacts.contact_client_id AND contact_primary = 1
    WHERE invoice_id = $invoice_id
    AND invoice_url_key = '$url_key'"
);

if (mysqli_num_rows($sql) !== 1) {
    // Invalid invoice/key
    echo "<br><h2>Oops, something went wrong! Please raise a ticket if you believe this is an error.</h2>";
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/footer.php';

    exit();
}

$row = mysqli_fetch_assoc($sql);

$invoice_id = intval($row['invoice_id']);
$invoice_prefix = escapeHtml($row['invoice_prefix']);
$invoice_number = intval($row['invoice_number']);
$invoice_status = escapeHtml($row['invoice_status']);
$invoice_date = escapeHtml($row['invoice_date']);
$invoice_due = escapeHtml($row['invoice_due']);
$invoice_discount = floatval($row['invoice_discount_amount']);
$invoice_amount = floatval($row['invoice_amount']);
$invoice_currency_code = escapeHtml($row['invoice_currency_code']);
$invoice_note = escapeHtml($row['invoice_note']);
$invoice_category_id = intval($row['invoice_category_id']);
$client_id = intval($row['client_id']);
$client_name = escapeHtml($row['client_name']);
$client_name_escaped = escapeSql($row['client_name']);
$location_address = escapeHtml($row['location_address']);
$location_city = escapeHtml($row['location_city']);
$location_state = escapeHtml($row['location_state']);
$location_zip = escapeHtml($row['location_zip']);
$location_country = escapeHtml($row['location_country']);
$contact_email = escapeHtml($row['contact_email']);
$contact_phone_country_code = escapeHtml($row['contact_phone_country_code']);
$contact_phone = escapeHtml(formatPhoneNumber($row['contact_phone'], $contact_phone_country_code));
$contact_extension = escapeHtml($row['contact_extension']);
$contact_mobile_country_code = escapeHtml($row['contact_mobile_country_code']);
$contact_mobile = escapeHtml(formatPhoneNumber($row['contact_mobile'], $contact_mobile_country_code));
$client_website = escapeHtml($row['client_website']);
$client_currency_code = escapeHtml($row['client_currency_code']);
$client_net_terms = intval($row['client_net_terms']);

$sql = mysqli_query($mysqli, "SELECT company_address, company_city, company_country, company_email, company_locale,
    company_logo, company_name, company_phone, company_phone_country_code, company_state,
    company_tax_id, company_website, company_zip, config_invoice_footer FROM companies, settings WHERE companies.company_id = settings.company_id AND companies.company_id = 1");
$row = mysqli_fetch_assoc($sql);

$company_name = escapeHtml($row['company_name']);
$company_address = escapeHtml($row['company_address']);
$company_city = escapeHtml($row['company_city']);
$company_state = escapeHtml($row['company_state']);
$company_zip = escapeHtml($row['company_zip']);
$company_country = escapeHtml($row['company_country']);
$company_phone_country_code = escapeHtml($row['company_phone_country_code']);
$company_phone = escapeHtml(formatPhoneNumber($row['company_phone'], $company_phone_country_code));
$company_email = escapeHtml($row['company_email']);
$company_website = escapeHtml($row['company_website']);
$company_tax_id = escapeHtml($row['company_tax_id']);
if ($config_invoice_show_tax_id && !empty($company_tax_id)) {
    $company_tax_id_display = "Tax ID: $company_tax_id";
} else {
    $company_tax_id_display = "";
}
$company_logo = escapeHtml($row['company_logo']);
if (!empty($company_logo)) {
    $company_logo_base64 = base64_encode(file_get_contents("../uploads/settings/$company_logo"));
}
$company_locale = escapeHtml($row['company_locale']);
$config_invoice_footer = escapeHtml($row['config_invoice_footer']);

// Get Payment Provide Details
$sql = mysqli_query($mysqli, "SELECT payment_provider_id, payment_provider_name, payment_provider_threshold FROM payment_providers WHERE payment_provider_active = 1 LIMIT 1");
$row = mysqli_fetch_assoc($sql);
$payment_provider_id = intval($row['payment_provider_id']);
$payment_provider_name = escapeHtml($row['payment_provider_name']);
$payment_provider_threshold = floatval($row['payment_provider_threshold']);

//Set Currency Format
$currency_format = numfmt_create($company_locale, NumberFormatter::CURRENCY);

$invoice_tally_total = 0; // Default

//Set Badge color based off of invoice status
$invoice_badge_color = getInvoiceBadgeColor($invoice_status);

//Update status to Viewed only if invoice_status = "Sent"
if ($invoice_status == 'Sent') {
    mysqli_query($mysqli, "UPDATE invoices SET invoice_status = 'Viewed' WHERE invoice_id = $invoice_id");
}

//Mark viewed in history
mysqli_query($mysqli, "INSERT INTO history SET history_status = '$invoice_status', history_description = 'Invoice viewed - $ip - $os - $browser', history_invoice_id = $invoice_id");

if ($invoice_status !== 'Paid') {

    appNotify("Invoice Viewed", "Invoice $invoice_prefix$invoice_number has been viewed by $client_name_escaped - $ip - $os - $browser", "/agent/invoice.php?invoice_id=$invoice_id", $client_id);

}
$sql_payments = mysqli_query($mysqli, "SELECT * FROM payments, accounts WHERE payment_account_id = account_id AND payment_invoice_id = $invoice_id ORDER BY payments.payment_id DESC");

//Add up all the payments for the invoice and get the total amount paid to the invoice
$sql_amount_paid = mysqli_query($mysqli, "SELECT SUM(payment_amount) AS amount_paid FROM payments WHERE payment_invoice_id = $invoice_id");
$row = mysqli_fetch_assoc($sql_amount_paid);
$amount_paid = floatval($row['amount_paid']);

// Calculate the balance owed
$balance = $invoice_amount - $amount_paid;

//check to see if overdue
$invoice_color = $invoice_badge_color; // Default
if ($invoice_status !== "Paid" && $invoice_status !== "Draft" && $invoice_status !== "Cancelled" && $invoice_status !== "Non-Billable") {
    $unixtime_invoice_due = strtotime($invoice_due) + 86400;
    if ($unixtime_invoice_due < time()) {
        $invoice_color = "text-danger";
    }
}

// Invoice individual items
$sql_invoice_items = mysqli_query($mysqli, "SELECT item_description, item_id, item_name, item_price, item_quantity, item_tax, item_total FROM invoice_items WHERE item_invoice_id = $invoice_id ORDER BY item_order ASC");


// Get Total Account Balance
//Add up all the payments for the invoice and get the total amount paid to the invoice
$sql_invoice_amounts = mysqli_query($mysqli, "SELECT SUM(invoice_amount) AS invoice_amounts FROM invoices WHERE invoice_client_id = $client_id AND invoice_status != 'Draft' AND invoice_status != 'Cancelled' AND invoice_status != 'Non-Billable'");
$row = mysqli_fetch_assoc($sql_invoice_amounts);

$account_balance = floatval($row['invoice_amounts']);

$sql_amount_paid = mysqli_query($mysqli, "SELECT SUM(payment_amount) AS amount_paid FROM payments, invoices WHERE payment_invoice_id = invoice_id AND invoice_client_id = $client_id");
$row = mysqli_fetch_assoc($sql_amount_paid);

$account_amount_paid = floatval($row['amount_paid']);

$account_balance = $account_balance - $account_amount_paid;
//set Text color on balance
if ($balance > 0) {
    $balance_text_color = "text-danger fw-bold";
} else {
    $balance_text_color = "";
}

?>

<div class="card my-3">
    <div class="card-header bg-light d-print-none">
        <div class="row">
            <div class="col-6">
                <h4 class="mt-1">Account Balance: <b><?= numfmt_format_currency($currency_format, $account_balance, $invoice_currency_code) ?></b></h4>
            </div>
            <div class="col-6">
                <div class="float-end">
                    <a class="btn btn-default" href="#" onclick="window.print();"><i class="fas fa-fw fa-print me-2"></i>Print</a>
                    <a class="btn btn-default" href="guest_post.php?export_invoice_pdf=<?= $invoice_id ?>&url_key=<?= $url_key ?>">
                        <i class="fa fa-fw fa-download me-2"></i>Download
                    </a>
                    <?php
                    if ($invoice_status !== "Paid" &&
                        $invoice_status  !== "Cancelled" &&
                        $invoice_status !== "Draft" &&
                        $payment_provider_id &&
                        (
                            $payment_provider_threshold == 0 ||
                            $payment_provider_threshold > $invoice_amount
                        )
                    ){ ?>
                        <a class="btn btn-success" href="guest_pay_invoice_stripe.php?invoice_id=<?= $invoice_id ?>&url_key=<?= $url_key ?>"><i class="fa fa-fw fa-credit-card me-2"></i>Pay Now </a>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
    <div class="card-body">

        <div class="row mb-3">
            <?php if (file_exists("../uploads/settings/$company_logo")) { ?>
            <div class="col-sm-2">
                <img class="img-fluid" src="<?= "../uploads/settings/$company_logo" ?>" alt="Company logo">
            </div>
            <?php } ?>
            <div class="col-sm-6 <?php if (!file_exists("../uploads/settings/$company_logo")) { echo "col-sm-8"; } ?>">
                <ul class="list-unstyled">
                    <li><h4><strong><?= $company_name ?></strong></h4></li>
                    <li><?= formatAddress($company_address, $company_city, $company_state, $company_zip, $company_country, '<br>') ?></li>
                    <li><?= "$company_email | $company_phone" ?></li>
                    <li><?= $company_website ?></li>
                    <?php if ($company_tax_id_display) { ?>
                    <li><?= $company_tax_id_display ?></li>
                    <?php } ?>
                </ul>
            </div>

            <div class="col-sm-4">
                <h3 class="text-end"><strong>INVOICE</strong></h3>
                <h5 class="badge text-bg-<?= $invoice_badge_color ?> p-2 float-end">
                    <?= "$invoice_status" ?>
                </h5>
                <table class="table table-sm table-borderless">
                    <tr>
                        <th>Invoice #:</th>
                        <td class="text-end"><?= "$invoice_prefix$invoice_number" ?></td>
                    </tr>
                    <tr>
                        <th>Date:</th>
                        <td class="text-end"><?= $invoice_date ?></td>
                    </tr>
                    <tr>
                        <th>Due:</th>
                        <td class="text-end"><?= $invoice_due ?></td>
                    </tr>
                </table>
            </div>

        </div>
        <div class="row mb-3 bg-light p-3">
            <div class="col">
                <h6><strong>Bill To:</strong></h6>
                <ul class="list-unstyled mb-0">
                    <li><?= $client_name ?></li>
                    <li><?= formatAddress($location_address, $location_city, $location_state, $location_zip, $location_country, '<br>') ?></li>
                    <li><?= "$contact_email | $contact_phone $contact_extension" ?></li>
                </ul>
            </div>
        </div>

        <div class="row mb-3">
            <div class="col-md-12">
                <div class="card">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="bg-light">
                            <tr>
                                <th>Item</th>
                                <th>Description</th>
                                <th class="text-center">Qty</th>
                                <th class="text-end">Unit Price</th>
                                <th class="text-end">Tax</th>
                                <th class="text-end">Amount</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php

                            $total_tax = 0.00;
                            $sub_total = 0.00 - $invoice_discount;

                            while ($row = mysqli_fetch_assoc($sql_invoice_items)) {
                                $item_id = intval($row['item_id']);
                                $item_name = escapeHtml($row['item_name']);
                                $item_description = escapeHtml($row['item_description']);
                                $item_quantity = floatval($row['item_quantity']);
                                $item_price = floatval($row['item_price']);
                                $item_tax = floatval($row['item_tax']);
                                $item_total = floatval($row['item_total']);
                                $total_tax = $item_tax + $total_tax;
                                $sub_total = $item_price * $item_quantity + $sub_total;

                                ?>

                                <tr>
                                    <td><?= $item_name ?></td>
                                    <td><?= nl2br($item_description) ?></td>
                                    <td class="text-center"><?= $item_quantity ?></td>
                                    <td class="text-end"><?= numfmt_format_currency($currency_format, $item_price, $invoice_currency_code) ?></td>
                                    <td class="text-end"><?= numfmt_format_currency($currency_format, $item_tax, $invoice_currency_code) ?></td>
                                    <td class="text-end"><?= numfmt_format_currency($currency_format, $item_total, $invoice_currency_code) ?></td>
                                </tr>

                            <?php } ?>

                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-3">
            <div class="col-sm-7">
                <?php if (!empty($invoice_note)) { ?>
                    <div class="card">
                        <div class="card-body">
                            <?= nl2br($invoice_note) ?>
                        </div>
                    </div>
                <?php } ?>
            </div>
            <div class="col-sm-3 offset-sm-2">
                <table class="table table-hover mb-0">
                    <tbody>
                    <tr>
                        <td>Subtotal:</td>
                        <td class="text-end"><?= numfmt_format_currency($currency_format, $sub_total, $invoice_currency_code) ?></td>
                    </tr>
                    <?php
                    if ($invoice_discount > 0) {
                        ?>
                        <tr>
                            <td>Discount:</td>
                            <td class="text-end">-<?= numfmt_format_currency($currency_format, $invoice_discount, $invoice_currency_code) ?></td>
                        </tr>
                    <?php
                    }
                    ?>
                    <?php if ($total_tax > 0) { ?>
                        <tr>
                            <td>Tax:</td>
                            <td class="text-end"><?= numfmt_format_currency($currency_format, $total_tax, $invoice_currency_code) ?></td>
                        </tr>
                    <?php } ?>
                    <tr>
                        <td>Total:</td>
                        <td class="text-end"><?= numfmt_format_currency($currency_format, $invoice_amount, $invoice_currency_code) ?></td>
                    </tr>
                    <?php if ($amount_paid > 0) { ?>
                        <tr>
                            <td><div class="text-success">Paid:</div></td>
                            <td class="text-end text-success"><?= numfmt_format_currency($currency_format, $amount_paid, $invoice_currency_code) ?></td>
                        </tr>
                    <?php
                    }
                    ?>
                    <tr class="h5 text-bold">
                        <td>Balance:</td>
                        <td class="text-end"><?= numfmt_format_currency($currency_format, $balance, $invoice_currency_code) ?></td>
                    </tr>

                    </tbody>
                </table>
            </div>
        </div>

        <hr class="mt-5">

        <div class="text-center text-secondary"><?= nl2br($config_invoice_footer) ?></div>
    </div>
</div>

<?php

/*
 * ACCOUNT STATEMENT
 *
 * Replaces the old Current Invoices / Outstanding Invoices pair. Those split
 * the same list on due date and both showed invoice_amount, which is the
 * ORIGINAL total - a $1,000 invoice with $900 paid appeared here as $1,000
 * owing. This shows amount, paid and balance, and the running total both cards
 * were accumulating into $invoice_tally_total but never printing.
 *
 * Payments are summed in a derived table rather than joined directly, or an
 * invoice with two payments against it would be counted twice.
 *
 * Draft / Cancelled / Non-Billable are not money owed, and the balance test
 * drops anything fully paid, so Paid invoices fall out without naming them.
 */

$sql_statement = mysqli_query(
    $mysqli,
    "SELECT invoice_amount, invoice_currency_code, invoice_date, invoice_due, invoice_id,
        invoice_number, invoice_prefix, invoice_scope, invoice_url_key,
        IFNULL(amount_paid, 0) AS amount_paid
    FROM invoices
    LEFT JOIN (
        SELECT payment_invoice_id, SUM(payment_amount) AS amount_paid FROM payments
        WHERE payment_archived_at IS NULL
        GROUP BY payment_invoice_id
    ) AS invoice_payments ON payment_invoice_id = invoice_id
    WHERE invoice_client_id = $client_id
    AND invoice_status NOT IN ('Draft', 'Cancelled', 'Non-Billable')
    AND invoice_amount - IFNULL(invoice_payments.amount_paid, 0) > 0
    ORDER BY invoice_date ASC, invoice_number ASC"
);

$statement_count = mysqli_num_rows($sql_statement);

if ($statement_count > 0) { ?>

<div class="card d-print-none">
    <div class="card-header bg-dark">
        <strong class="text-white">
            <i class="fa fa-fw fa-file-alt me-2"></i>Account Statement
        </strong>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead>
                <tr>
                    <th class="text-center">Invoice</th>
                    <th class="d-none d-md-table-cell">Scope</th>
                    <th>Date</th>
                    <th>Due</th>
                    <th class="text-end">Amount</th>
                    <th class="text-end">Paid</th>
                    <th class="text-end">Balance</th>
                </tr>
                </thead>
                <tbody>
                <?php

                /*
                 * Distinct variable names on purpose. The two cards this
                 * replaces reused $invoice_id / $invoice_prefix / $invoice_due
                 * for their loop rows, which clobbered the page's own invoice -
                 * that is why the old highlight test had to read $_GET instead
                 * of comparing against $invoice_id.
                 */
                while ($row = mysqli_fetch_assoc($sql_statement)) {
                    $statement_invoice_id = intval($row['invoice_id']);
                    $statement_invoice_prefix = escapeHtml($row['invoice_prefix']);
                    $statement_invoice_number = intval($row['invoice_number']);
                    $statement_invoice_scope = escapeHtml($row['invoice_scope']);
                    $statement_invoice_date = escapeHtml($row['invoice_date']);
                    $statement_invoice_due = escapeHtml($row['invoice_due']);
                    $statement_invoice_url_key = escapeHtml($row['invoice_url_key']);
                    $statement_invoice_amount = floatval($row['invoice_amount']);
                    $statement_invoice_currency_code = escapeHtml($row['invoice_currency_code']);
                    $statement_amount_paid = floatval($row['amount_paid']);
                    $statement_invoice_balance = $statement_invoice_amount - $statement_amount_paid;

                    $invoice_tally_total = $invoice_tally_total + $statement_invoice_balance;

                    $statement_days = floor((time() - strtotime($statement_invoice_due)) / (60 * 60 * 24));

                    if ($statement_days > 0) {
                        $statement_due_class = 'text-danger';
                        $statement_due_note = "(overdue by $statement_days days)";
                    } else {
                        $statement_due_class = '';
                        $statement_due_note = '(due in ' . abs($statement_days) . ' days)';
                    }

                    ?>

                    <tr <?php if ($statement_invoice_id === $invoice_id) { echo "class='table-primary'"; } ?>>
                        <th class="text-center">
                            <a href="guest_view_invoice.php?invoice_id=<?= $statement_invoice_id ?>&url_key=<?= $statement_invoice_url_key ?>">
                                <?= "$statement_invoice_prefix$statement_invoice_number" ?>
                            </a>
                        </th>
                        <td class="d-none d-md-table-cell"><?= $statement_invoice_scope ?></td>
                        <td><?= $statement_invoice_date ?></td>
                        <td class="<?= $statement_due_class ?>"><?= "$statement_invoice_due $statement_due_note" ?></td>
                        <td class="text-end font-monospace"><?= numfmt_format_currency($currency_format, $statement_invoice_amount, $statement_invoice_currency_code) ?></td>
                        <td class="text-end font-monospace"><?= numfmt_format_currency($currency_format, $statement_amount_paid, $statement_invoice_currency_code) ?></td>
                        <td class="text-end font-monospace text-bold"><?= numfmt_format_currency($currency_format, $statement_invoice_balance, $statement_invoice_currency_code) ?></td>
                    </tr>

                    <?php

                }

                ?>

                </tbody>
                <tfoot>
                <tr>
                    <th colspan="6" class="text-end">Total Balance Due</th>
                    <th class="text-end font-monospace"><?= numfmt_format_currency($currency_format, $invoice_tally_total, $client_currency_code) ?></th>
                </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<?php } // End account statement

require_once $_SERVER['DOCUMENT_ROOT']  . '/includes/footer.php';
