<?php

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_sales', 2);

$invoice_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT invoice_prefix, invoice_number, invoice_client_id
    FROM invoices WHERE invoice_id = $invoice_id LIMIT 1
");

$row = mysqli_fetch_assoc($sql);
$invoice_prefix = escapeHtml($row['invoice_prefix']);
$invoice_number = intval($row['invoice_number']);
$client_id = intval($row['invoice_client_id']);

// Get Credit Balance
$sql_credit_balance = mysqli_query($mysqli, "SELECT SUM(credit_amount) AS credit_balance FROM credits WHERE credit_client_id = $client_id");
$row = mysqli_fetch_assoc($sql_credit_balance);

$credit_balance = floatval($row['credit_balance']);

enforceClientAccess();

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-wallet me-2"></i><?= "$invoice_prefix$invoice_number" ?>: Apply Credit (Balance: <?= numfmt_format_currency($currency_format, $credit_balance, $session_company_currency) ?>)</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="invoice_id" value="<?= $invoice_id ?>">
    <div class="modal-body">

        <div class="mb-3">
            <label>Credit Amount <strong class="text-danger">*</strong></label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-wallet"></i></span>
                <input type="text" class="form-control" inputmode="decimal" pattern="[0-9]*\.?[0-9]{0,2}" name="amount_credit_applied" value="<?= number_format($credit_balance, 2, '.', '') ?>" placeholder="0.00" required>
            </div>
        </div>

    </div>

    <div class="modal-footer">
        <button type="submit" name="apply_credit" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Apply Credit</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php

require_once '../../../includes/modal_footer.php';
