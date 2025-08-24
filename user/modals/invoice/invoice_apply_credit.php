<?php

require_once '../../../includes/modal_header_new.php';

$invoice_id = intval($_GET['id']);

$sql = mysqli_query(
    $mysqli,
    "SELECT * FROM invoices
    LEFT JOIN clients ON invoice_client_id = client_id
    LEFT JOIN contacts ON client_id = contact_client_id AND contact_primary = 1
    WHERE invoice_id = $invoice_id
    LIMIT 1"
);

$row = mysqli_fetch_array($sql);
$invoice_id = intval($row['invoice_id']);
$invoice_prefix = nullable_htmlentities($row['invoice_prefix']);
$invoice_number = intval($row['invoice_number']);
$invoice_amount = floatval($row['invoice_amount']);
$client_id = intval($row['client_id']);
$client_name = nullable_htmlentities($row['client_name']);
$client_currency_code = nullable_htmlentities($row['client_currency_code']);
$contact_name = nullable_htmlentities($row['contact_name']);
$contact_email = nullable_htmlentities($row['contact_email']);

// Get Credit Balance
$sql_credit_balance = mysqli_query($mysqli, "SELECT SUM(credit_amount) AS credit_balance FROM credits WHERE credit_client_id = $client_id");
$row = mysqli_fetch_array($sql_credit_balance);

$credit_balance = floatval($row['credit_balance']);

//Add up all the payments for the invoice and get the total amount paid to the invoice
$sql_amount_paid = mysqli_query($mysqli, "SELECT SUM(payment_amount) AS amount_paid FROM payments WHERE payment_invoice_id = $invoice_id");
$row = mysqli_fetch_array($sql_amount_paid);
$amount_paid = floatval($row['amount_paid']);

$invoice_balance = $invoice_amount - $amount_paid;

// Generate the HTML form content using output buffering.
ob_start();
?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-wallet mr-2"></i><?php echo "$invoice_prefix$invoice_number"; ?>: Apply Credit (Balance: <?php echo numfmt_format_currency($currency_format, $credit_balance, $client_currency_code); ?>)</h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="invoice_id" value="<?php echo $invoice_id; ?>">
    <input type="hidden" name="invoice_balance" value="<?php echo $invoice_balance; ?>">
    <input type="hidden" name="currency_code" value="<?php echo $client_currency_code; ?>">
    <div class="modal-body">

        <div class="form-group">
            <label>Credit Amount <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-wallet"></i></span>
                </div>
                <input type="text" class="form-control" inputmode="numeric" pattern="[0-9]*\.?[0-9]{0,2}" name="amount" value="<?php echo number_format($credit_balance, 2, '.', ''); ?>" placeholder="0.00" required>
            </div>
        </div>

        <?php if (!empty($config_smtp_host) && !empty($contact_email)) { ?>

            <div class="form-group">
                <label>Email Receipt</label>
                <div class="custom-control custom-checkbox">
                    <input type="checkbox" class="custom-control-input" id="customControlAutosizing" name="email_receipt" value="1" checked>
                    <label class="custom-control-label" for="customControlAutosizing"><?php echo $contact_email; ?></label>
                </div>
            </div>

        <?php } ?>

    </div>

    <div class="modal-footer">
        <button type="submit" name="apply_credit" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Apply Credit</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fas fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php

require_once '../../../includes/modal_footer_new.php';
