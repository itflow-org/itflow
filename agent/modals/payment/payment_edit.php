<?php

/*
 * Edit a payment - its amount, date, method, reference and account. The
 * invoice it is on follows: paid, part paid or owing again. ?id=
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_sales', 3);
enforceUserPermission('module_financial', 3);

$payment_id = intval($_GET['id'] ?? 0);
$payment = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT payments.*, invoice_id FROM payments LEFT JOIN invoices ON invoice_id = payment_invoice_id WHERE payment_id = $payment_id LIMIT 1"));
$invoice = $payment ? billingRow('invoice', intval($payment['payment_invoice_id'])) : null;
if (!$payment || !$invoice) {
    exit(json_encode(['error' => 'That payment no longer exists.']));
}
$client_id = intval($invoice['client_id']);
enforceClientAccess();

$paid_elsewhere = invoicePaid($invoice['invoice_id']) - floatval($payment['payment_amount']);

$pay = [
    'id' => 'payEdit' . $payment_id,
    'amount' => $payment['payment_amount'],
    'owed' => round(floatval($invoice['invoice_amount']) - $paid_elsewhere, 2),
    'currency' => $payment['payment_currency_code'] ?: $invoice['invoice_currency_code'],
    'date' => $payment['payment_date'],
    'account_id' => intval($payment['payment_account_id']),
    'method' => $payment['payment_method'],
    'reference' => $payment['payment_reference'],
    'email' => '',
];

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-credit-card me-2"></i>Edit Payment <span class="fw-normal text-white-50 ms-1"><?= escapeHtml(billingNumber('invoice', $invoice)) ?> &middot; <?= escapeHtml($invoice['client_name']) ?></span></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="payment_id" value="<?= $payment_id ?>">

    <div class="modal-body">
        <?php require __DIR__ . '/payment_form.php'; ?>
    </div>
    <div class="modal-footer">
        <button type="submit" name="edit_payment" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
