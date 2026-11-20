<?php

/*
 * Add a payment to an invoice - what was paid, when, how, and into which
 * account, starting at what is owed, with what is left after as you type.
 * A receipt can go to the client's primary contact. Under the invoice, the
 * client's other unpaid invoices, oldest due first, each a click from its own
 * Add Payment, with Batch Payment for paying several. ?id= (the invoice)
 */

require_once '../../../includes/modal_header.php';

enforceBillingMoney('take');

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
// The client's other unpaid invoices, oldest due first
$others = [];
$others_owed = 0;
$others_older = 0;
$sql_others = mysqli_query($mysqli, "SELECT invoice_id, invoice_prefix, invoice_number, invoice_scope, invoice_due, invoice_currency_code,
        invoice_amount - COALESCE((SELECT SUM(payment_amount) FROM payments WHERE payment_invoice_id = invoice_id), 0) AS balance
    FROM invoices
    WHERE invoice_client_id = $client_id AND invoice_id != $invoice_id AND invoice_status IN ('Sent', 'Viewed', 'Partial')
    ORDER BY invoice_due ASC, invoice_id ASC");
while ($row = mysqli_fetch_assoc($sql_others)) {
    if (floatval($row['balance']) > 0) {
        $others[] = $row;
        $others_owed += floatval($row['balance']);
        $others_older += $row['invoice_due'] < $invoice['invoice_due'] ? 1 : 0;
    }
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
        <?php if ($others) { ?>
            <div class="mb-3 small" data-pay-others>
                <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                    <span class="fw-semibold text-uppercase text-body-secondary">Their other unpaid invoices</span>
                    <span class="text-body-secondary"><?= count($others) ?> &middot; <?= escapeHtml(billingMoney($others_owed, $invoice['invoice_currency_code'])) ?> owed</span>
                    <a class="ms-auto ajax-modal" href="#" data-bs-dismiss="modal" data-modal-url="modals/payment/payment_bulk_add.php?client_id=<?= $client_id ?>" title="One payment, spread over their unpaid invoices oldest first"><i class="fas fa-layer-group me-1"></i>Batch Payment</a>
                </div>
                <?php if ($others_older) { ?>
                    <div class="text-warning-emphasis mb-1"><i class="fas fa-exclamation-triangle me-1"></i><?= $others_older === 1 ? 'One was' : "$others_older were" ?> due before this one.</div>
                <?php } ?>
                <ul class="list-group">
                    <?php foreach (array_slice($others, 0, 5) as $other) {
                        $other_days = intval(floor((strtotime(date('Y-m-d')) - strtotime($other['invoice_due'])) / 86400)); ?>
                        <li class="list-group-item d-flex align-items-center gap-2 py-1">
                            <div class="min-w-0 flex-grow-1">
                                <div class="text-truncate"><span class="fw-semibold"><?= escapeHtml(billingNumber('invoice', $other)) ?></span><?php if (trim((string) $other['invoice_scope']) !== '') { ?><span class="text-body-secondary" title="<?= escapeHtml($other['invoice_scope']) ?>"> - <?= escapeHtml($other['invoice_scope']) ?></span><?php } ?></div>
                                <div class="<?= $other_days > 0 ? 'text-danger-emphasis' : 'text-body-secondary' ?>"><?= $other_days > 0 ? "$other_days day" . ($other_days === 1 ? '' : 's') . ' overdue' : 'Due ' . escapeHtml($other['invoice_due']) ?></div>
                            </div>
                            <span class="itflow-money text-nowrap"><?= escapeHtml(billingMoney($other['balance'], $other['invoice_currency_code'])) ?></span>
                            <a class="btn btn-sm btn-outline-secondary ajax-modal text-nowrap" href="#" data-bs-dismiss="modal" data-modal-url="modals/payment/payment_add.php?id=<?= intval($other['invoice_id']) ?>">Pay this one</a>
                        </li>
                    <?php } ?>
                </ul>
                <?php if (count($others) > 5) { ?>
                    <div class="text-body-secondary mt-1">and <?= count($others) - 5 ?> more - <a href="invoices.php?client_id=<?= $client_id ?>&amp;status=Unpaid">see them all</a></div>
                <?php } ?>
            </div>
        <?php } ?>
        <?php require __DIR__ . '/payment_form.php'; ?>
    </div>
    <div class="modal-footer">
        <button type="submit" name="add_payment" class="btn btn-success text-bold"><i class="fas fa-check me-2"></i>Add Payment</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
