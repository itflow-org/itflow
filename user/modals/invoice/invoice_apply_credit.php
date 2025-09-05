<?php

require_once '../../../includes/modal_header.php';

$invoice_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT invoice_prefix, invoice_number, invoice_client_id
    FROM invoices WHERE invoice_id = $invoice_id LIMIT 1
");

$row = mysqli_fetch_array($sql);
$invoice_prefix = nullable_htmlentities($row['invoice_prefix']);
$invoice_number = intval($row['invoice_number']);
$client_id = intval($row['invoice_client_id']);

// Get Credit Balance
$sql_credit_balance = mysqli_query($mysqli, "SELECT SUM(credit_amount) AS credit_balance FROM credits WHERE credit_client_id = $client_id");
$row = mysqli_fetch_array($sql_credit_balance);

$credit_balance = floatval($row['credit_balance']);

// Generate the HTML form content using output buffering.
ob_start();
?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-wallet mr-2"></i><?php echo "$invoice_prefix$invoice_number"; ?>: Apply Credit (Balance: <?php echo numfmt_format_currency($currency_format, $credit_balance, $session_company_currency); ?>)</h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="invoice_id" value="<?php echo $invoice_id; ?>">
    <div class="modal-body">

        <div class="form-group">
            <label>Credit Amount <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-wallet"></i></span>
                </div>
                <input type="text" class="form-control" inputmode="numeric" pattern="[0-9]*\.?[0-9]{0,2}" name="amount_credit_applied" value="<?php echo number_format($credit_balance, 2, '.', ''); ?>" placeholder="0.00" required>
            </div>
        </div>

    </div>

    <div class="modal-footer">
        <button type="submit" name="apply_credit" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Apply Credit</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fas fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php

require_once '../../../includes/modal_footer.php';
