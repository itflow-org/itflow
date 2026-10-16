<?php

/*
 * Add a payment to an invoice - what was paid, when, how, and into which
 * account, starting at what is owed, with what is left after as you type.
 * A receipt can go to the client's primary contact. ?id= (the invoice)
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_sales', 2);
enforceUserPermission('module_financial', 2);

$invoice_id = intval($_GET['id'] ?? 0);
$invoice = billingRow('invoice', $invoice_id);
if (!$invoice) {
    exit(json_encode(['error' => 'That invoice no longer exists.']));
}
$client_id = intval($invoice['client_id']);
enforceClientAccess();

$state = invoiceState($invoice, invoicePaid($invoice_id));
if (!invoiceCanPay($invoice, $state)) {
    exit(json_encode(['error' => billingNumber('invoice', $invoice) . ' has nothing owed on it - it is ' . strtolower($invoice['invoice_status']) . '.']));
}
$contact_email = (string) mysqli_fetch_row(mysqli_query($mysqli, "SELECT contact_email FROM contacts WHERE contact_client_id = $client_id AND contact_primary = 1 AND contact_archived_at IS NULL LIMIT 1"))[0];

$pay = [
    'id' => 'payForm' . $invoice_id,
    'amount' => $state['balance'],
    'owed' => $state['balance'],
    'currency' => $invoice['invoice_currency_code'],
    'email' => $contact_email,
];

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-credit-card me-2"></i>Add Payment <span class="fw-normal text-white-50 ms-1"><?= escapeHtml(billingNumber('invoice', $invoice)) ?> &middot; <?= escapeHtml($invoice['client_name']) ?></span></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="invoice_id" value="<?= $invoice_id ?>">
    <input type="hidden" name="balance" value="<?= number_format($state['balance'], 2, '.', '') ?>">
    <input type="hidden" name="currency_code" value="<?= escapeHtml($invoice['invoice_currency_code']) ?>">

    <div class="modal-body">
        <div class="d-flex align-items-center gap-3 p-3 mb-3 rounded bg-body-tertiary">
            <?= billingTile('invoice') ?>
            <div class="flex-grow-1 min-w-0">
                <div class="fw-semibold"><?= escapeHtml(billingNumber('invoice', $invoice)) ?><?= trim((string) $invoice['invoice_scope']) !== '' ? ' - ' . escapeHtml($invoice['invoice_scope']) : '' ?></div>
                <div class="small"><?= invoiceDuePill($state) ?: '<span class="text-body-secondary">Due ' . escapeHtml($invoice['invoice_due']) . '</span>' ?></div>
            </div>
            <div class="text-end">
                <div class="fw-semibold itflow-money"><?= escapeHtml(billingMoney($state['balance'], $invoice['invoice_currency_code'])) ?></div>
                <div class="small text-body-secondary">owed<?= $state['paid'] > 0 ? ' of ' . escapeHtml(billingMoney($invoice['invoice_amount'], $invoice['invoice_currency_code'])) : '' ?></div>
            </div>
        </div>
        <?php require __DIR__ . '/payment_form.php'; ?>
    </div>
    <div class="modal-footer">
        <button type="submit" name="add_payment" class="btn btn-success text-bold"><i class="fas fa-check me-2"></i>Add Payment</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
