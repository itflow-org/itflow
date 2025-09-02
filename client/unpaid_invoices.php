<?php
/*
 * Client Portal
 * Invoices for PTC
 */

header("Content-Security-Policy: default-src 'self'");

require_once "includes/inc_all.php";


if ($session_contact_primary == 0 && !$session_contact_is_billing_contact) {
    header("Location: post.php?logout");
    exit();
}

$invoices_sql = mysqli_query($mysqli, "SELECT * FROM invoices WHERE invoice_client_id = $session_client_id AND (invoice_status = 'Viewed' OR invoice_status = 'Sent' OR invoice_status = 'Partial') ORDER BY invoice_date DESC");


// Payment Provider Active Query
$sql_payment_provider = mysqli_query($mysqli, "SELECT * FROM payment_providers WHERE payment_provider_active = 1 LIMIT 1;");
$row = mysqli_fetch_array($sql_payment_provider);
$payment_provider_active = intval($row['payment_provider_active']);

// Saved Payment Methods
$sql_saved_payment_methods = mysqli_query($mysqli, "
    SELECT * FROM client_saved_payment_methods
    LEFT JOIN payment_providers 
        ON client_saved_payment_methods.saved_payment_provider_id = payment_providers.payment_provider_id
    WHERE saved_payment_client_id = $session_client_id
    AND payment_provider_active = 1;
");

// Get Balance
// Billing Card Queries
 //Add up all the payments for the invoice and get the total amount paid to the invoice
$sql_invoice_amounts = mysqli_query($mysqli, "SELECT SUM(invoice_amount) AS invoice_amounts FROM invoices WHERE invoice_client_id = $session_client_id AND invoice_status != 'Draft' AND invoice_status != 'Cancelled' AND invoice_status != 'Non-Billable'");
$row = mysqli_fetch_array($sql_invoice_amounts);

$invoice_amounts = floatval($row['invoice_amounts']);

$sql_amount_paid = mysqli_query($mysqli, "SELECT SUM(payment_amount) AS amount_paid FROM payments, invoices WHERE payment_invoice_id = invoice_id AND invoice_client_id = $session_client_id");
$row = mysqli_fetch_array($sql_amount_paid);

$amount_paid = floatval($row['amount_paid']);

$balance = $invoice_amounts - $amount_paid;

?>

<div class="row">
    <div class="col-5">
        <h3>Unpaid Invoices</h3>
    </div>
    <div class="col-5">
        <?php if ($payment_provider_active) { ?>
        <button type="button" class="btn btn-outline-success dropdown-toggle float-right" data-toggle="dropdown"><i class="fa fa-fw fa-credit-card mr-2"></i>Pay Balance <strong>(<?php echo numfmt_format_currency($currency_format, $balance, $session_company_currency); ?>)</strong></button>
        <div class="dropdown-menu">
            <a class="dropdown-item" href="//<?php echo $config_base_url ?>/guest/guest_pay_invoice_stripe.php?invoice_id=<?php echo "$invoice_id&url_key=$invoice_url_key"; ?>">Enter Card Manually</a>
            <?php 
            if (mysqli_num_rows($sql_saved_payment_methods) > 0) { ?>
                <h6 class="dropdown-header text-left">Pay with a Saved Card</h6>
            <?php
            while ($row = mysqli_fetch_array($sql_saved_payment_methods)) {
                $saved_payment_id = intval($row['saved_payment_id']);
                $saved_payment_description = nullable_htmlentities($row['saved_payment_description']);
                $payment_provider_name = nullable_htmlentities($row['payment_provider_name']);
                ?>

                <a class="dropdown-item confirm-link" href="post.php?add_payment_by_provider=<?php echo $saved_payment_provider_id; ?>&invoice_id=<?php echo $invoice_id; ?>"><?php echo "$payment_provider_name | $saved_payment_description"; ?></a>
            <?php }
            } ?>
        </div>
        <?php } // End Payment Provider Active Check ?>
    </div>
</div>
<div class="row">

    <div class="col-md-10">

        <table class="table tabled-bordered border border-dark">
            <thead class="thead-dark">
            <tr>
                <th>#</th>
                <th>Scope</th>
                <th>Amount</th>
                <th>Date</th>
                <th>Due</th>
                <th></th>
            </tr>
            </thead>
            <tbody>

            <?php
            while ($row = mysqli_fetch_array($invoices_sql)) {
                $invoice_id = intval($row['invoice_id']);
                $invoice_prefix = nullable_htmlentities($row['invoice_prefix']);
                $invoice_number = intval($row['invoice_number']);
                $invoice_scope = nullable_htmlentities($row['invoice_scope']);
                $invoice_status = nullable_htmlentities($row['invoice_status']);
                $invoice_date = nullable_htmlentities($row['invoice_date']);
                $invoice_due = nullable_htmlentities($row['invoice_due']);
                $invoice_amount = floatval($row['invoice_amount']);
                $invoice_url_key = nullable_htmlentities($row['invoice_url_key']);

                if (empty($invoice_scope)) {
                    $invoice_scope_display = "-";
                } else {
                    $invoice_scope_display = $invoice_scope;
                }

                $now = time();
                if (($invoice_status == "Sent" || $invoice_status == "Partial" || $invoice_status == "Viewed") && strtotime($invoice_due) + 86400 < $now) {
                    $overdue_color = "text-danger font-weight-bold";
                } else {
                    $overdue_color = "";
                }

                if ($invoice_status == "Sent") {
                    $invoice_badge_color = "warning text-white";
                } elseif ($invoice_status == "Viewed") {
                    $invoice_badge_color = "info";
                } elseif ($invoice_status == "Partial") {
                    $invoice_badge_color = "primary";
                } elseif ($invoice_status == "Paid") {
                    $invoice_badge_color = "success";
                } elseif ($invoice_status == "Cancelled") {
                    $invoice_badge_color = "danger";
                } else{
                    $invoice_badge_color = "secondary";
                }
                ?>

                <tr>
                    <td><a target="_blank" href="//<?php echo $config_base_url ?>/guest/guest_view_invoice.php?invoice_id=<?php echo "$invoice_id&url_key=$invoice_url_key"?>"> <?php echo "$invoice_prefix$invoice_number"; ?></a></td>
                    <td><?php echo $invoice_scope_display; ?></td>
                    <td><?php echo numfmt_format_currency($currency_format, $invoice_amount, $session_company_currency); ?></td>
                    <td><?php echo $invoice_date; ?></td>
                    <td class="<?php echo $overdue_color; ?>"><?php echo $invoice_due; ?></td>
                    <td>
                        <?php if ($payment_provider_active) { ?>
                        <button type="button" class="btn btn-sm btn-outline-success dropdown-toggle" data-toggle="dropdown"><i class="fa fa-fw fa-credit-card mr-2"></i>Pay</button>
                        <div class="dropdown-menu">
                            <a class="dropdown-item" href="//<?php echo $config_base_url ?>/guest/guest_pay_invoice_stripe.php?invoice_id=<?php echo "$invoice_id&url_key=$invoice_url_key"; ?>">Enter Card Manually</a>
                            
                            <?php 
                            // Saved Payment Methods
                            $sql_saved_payment_methods = mysqli_query($mysqli, "
                                SELECT * FROM client_saved_payment_methods
                                LEFT JOIN payment_providers 
                                    ON client_saved_payment_methods.saved_payment_provider_id = payment_providers.payment_provider_id
                                WHERE saved_payment_client_id = $session_client_id
                                AND payment_provider_active = 1;
                            ");
                            if (mysqli_num_rows($sql_saved_payment_methods) > 0) { ?>
                                <h6 class="dropdown-header text-left">Pay with a Saved Card</h6>
                            <?php
                            while ($row = mysqli_fetch_array($sql_saved_payment_methods)) {
                                $saved_payment_id = intval($row['saved_payment_id']);
                                $saved_payment_description = nullable_htmlentities($row['saved_payment_description']);
                                $payment_provider_name = nullable_htmlentities($row['payment_provider_name']);
                                ?>

                                <a class="dropdown-item confirm-link" href="post.php?add_payment_by_provider=<?php echo $saved_payment_provider_id; ?>&invoice_id=<?php echo $invoice_id; ?>"><?php echo "$payment_provider_name | $saved_payment_description"; ?></a>
                            <?php }
                            } ?>
                        </div>
                    <?php } ?>
                    </td>

                </tr>
            <?php } ?>

            </tbody>
        </table>

    </div>

</div>


<?php
require_once "includes/footer.php";

