<?php

/*
 * A payment's quick look - the invoice it paid (status, what it was for, what
 * it came to, what has been paid on it in all and what is left), the other
 * payments on it, where the money went and how it was paid. One paid online
 * shows the payment provider's fee and what came in after it, when the fee
 * is in the expenses with the payment's reference. Open the
 * invoice, email the receipt again, Edit and Delete. Opened from the Income
 * list, Transactions and the reports. ?id=
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_financial');

$row = incomePaymentRow(intval($_GET['id'] ?? 0));
if (!$row) {
    echo json_encode(['error' => 'That payment no longer exists, or is not one you can see.']);
    exit;
}

$payment_id = intval($row['payment_id']);
$cleared = reconcileLock('payment', $payment_id);
$client_id = intval($row['invoice_client_id']);
$amount = floatval($row['payment_amount']);
$currency = (string) ($row['payment_currency_code'] ?: $row['invoice_currency_code']);
$has_invoice = $row['invoice_number'] !== null;
$can_sales = lookupUserPermission('module_sales') >= 1;
$can_edit = incomeCanEditPayments() && $has_invoice;
$online = preg_match('/^Stripe - pi_/', (string) $row['payment_reference']) === 1;
$state = $has_invoice ? invoiceState($row, $row['invoice_paid']) : null;
$can_receipt = $has_invoice && lookupUserPermission('module_sales') >= 2 && $client_id;
$receipt_to = $can_receipt ? array_column(billingEmailRecipients($client_id), 'email') : [];
$money = function ($value, $code = null) use ($currency) {
    return escapeHtml(billingMoney($value, $code ?? $currency));
};

ob_start();

?>

<div class="modal-header bg-dark" data-bs-theme="dark">
    <div class="d-flex align-items-center gap-3 min-w-0 flex-grow-1">
        <span class="itflow-asset-icon itflow-asset-icon-lg bg-success-subtle text-success-emphasis" aria-hidden="true"><i class="fas fa-file-invoice-dollar"></i></span>
        <div class="min-w-0 flex-grow-1">
            <h5 class="modal-title fw-semibold text-break"><?= $has_invoice ? 'Payment on ' . escapeHtml($row['invoice_prefix'] . $row['invoice_number']) : 'Payment' ?></h5>
            <div class="small text-body-secondary mt-1 text-truncate"><?= escapeHtml(date('D, M j, Y', strtotime($row['payment_date']))) ?><?= $row['client_name'] !== null ? ' &middot; ' . escapeHtml($row['client_name']) : '' ?></div>
        </div>
        <div class="text-end">
            <div class="fs-4 fw-semibold text-white text-nowrap"><?= $money($amount) ?></div>
            <?php if ($online) { ?>
                <div class="small text-body-secondary"><i class="fas fa-globe me-1"></i>Paid online</div>
            <?php } ?>
        </div>
    </div>
    <button type="button" class="btn-close btn-close-white ms-3" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

<div class="modal-body">

    <!-- The invoice it paid -->
    <?php if ($has_invoice) { ?>
        <div class="p-3 rounded border bg-body-tertiary mb-3">
            <div class="d-flex flex-wrap align-items-start gap-3">
                <div class="flex-grow-1 min-w-0">
                    <div class="fw-semibold">
                        <?php if ($can_sales) { ?>
                            <a href="invoice.php?invoice_id=<?= intval($row['invoice_id']) ?>"><?= escapeHtml($row['invoice_prefix'] . $row['invoice_number']) ?></a>
                        <?php } else { ?>
                            <?= escapeHtml($row['invoice_prefix'] . $row['invoice_number']) ?>
                        <?php } ?>
                        <span class="ms-1"><?= invoiceStatusBadge($row['invoice_status']) ?></span>
                        <?= invoiceDuePill($state) ?>
                    </div>
                    <?php if (trim((string) $row['invoice_scope']) !== '') { ?>
                        <div class="small text-body-secondary text-truncate mt-1"><?= escapeHtml($row['invoice_scope']) ?></div>
                    <?php } ?>
                    <div class="small text-body-secondary mt-1">Dated <?= escapeHtml($row['invoice_date']) ?>, due <?= escapeHtml($row['invoice_due']) ?></div>
                </div>
                <div class="text-end small">
                    <div><span class="text-body-secondary">Invoice</span> <span class="itflow-money fw-semibold ms-2"><?= $money($row['invoice_amount'], $row['invoice_currency_code']) ?></span></div>
                    <div><span class="text-body-secondary">Paid in all</span> <span class="itflow-money ms-2"><?= $money($row['invoice_paid'], $row['invoice_currency_code']) ?></span></div>
                    <div class="<?= $state['balance'] > 0 ? 'text-danger-emphasis' : 'text-success-emphasis' ?>">
                        <span><?= $state['balance'] > 0 ? 'Still owed' : 'Nothing owed' ?></span>
                        <?php if ($state['balance'] > 0) { ?><span class="itflow-money fw-semibold ms-2"><?= $money($state['balance'], $row['invoice_currency_code']) ?></span><?php } ?>
                    </div>
                </div>
            </div>
            <?php if ($row['others']) { ?>
                <div class="border-top mt-3 pt-2">
                    <div class="small fw-semibold text-uppercase text-body-secondary mb-1">The other payment<?= count($row['others']) === 1 ? '' : 's' ?> on it</div>
                    <?php foreach ($row['others'] as $other) { ?>
                        <div class="d-flex justify-content-between small">
                            <a href="#" class="ajax-modal" data-modal-size="lg" data-modal-url="modals/payment/payment.php?id=<?= intval($other['payment_id']) ?>"><?= escapeHtml($other['payment_date']) ?><?= $other['payment_method'] ? ' - ' . escapeHtml($other['payment_method']) : '' ?></a>
                            <span class="itflow-money"><?= $money($other['payment_amount'], $other['payment_currency_code'] ?: $row['invoice_currency_code']) ?></span>
                        </div>
                    <?php } ?>
                </div>
            <?php } ?>
        </div>
    <?php } else { ?>
        <div class="alert alert-warning small">The invoice this was paid on has since been deleted.</div>
    <?php } ?>

    <!-- At a glance -->
    <div class="row g-2 itflow-asset-glance">
        <div class="col-sm-6">
            <div class="itflow-glance-item" title="Client">
                <i class="fas fa-fw fa-building"></i>
                <?php if ($client_id && $row['client_name'] !== null) { ?>
                    <a class="text-truncate" href="income.php?client_id=<?= $client_id ?>" title="What came in from them"><?= escapeHtml($row['client_name']) ?></a>
                <?php } else { ?>
                    <span class="text-body-secondary">No client</span>
                <?php } ?>
            </div>
            <div class="itflow-glance-item" title="Category - the invoice's">
                <i class="fas fa-fw fa-tag"></i>
                <?php if (intval($row['invoice_category_id']) && $row['category_name'] !== null) { ?>
                    <span class="text-truncate"><?= expenseCategoryDot($row['category_color']) ?><?= escapeHtml($row['category_name']) ?></span>
                <?php } else { ?>
                    <span class="text-warning-emphasis">No category<?= $has_invoice ? ' - set one on the invoice' : '' ?></span>
                <?php } ?>
            </div>
            <div class="itflow-glance-item" title="Entered">
                <i class="fas fa-fw fa-clock"></i>
                <span><span title="<?= escapeHtml($row['payment_created_at']) ?>"><?= timeAgo($row['payment_created_at']) ?></span><?php if (!empty($row['payment_import_key'])) { ?> <span class="text-body-secondary">from a bank statement</span><?php } ?></span>
            </div>
            <?= reconcileGlanceHtml($cleared) ?>
        </div>
        <div class="col-sm-6">
            <div class="itflow-glance-item" title="Paid into">
                <i class="fas fa-fw fa-piggy-bank"></i>
                <span class="text-truncate"><?= escapeHtml($row['account_name'] ?: 'No account') ?><?= $row['account_archived_at'] ? ' <span class="text-body-secondary">(archived)</span>' : '' ?></span>
            </div>
            <div class="itflow-glance-item" title="Paid by">
                <i class="fas fa-fw fa-credit-card"></i>
                <span><?= escapeHtml($row['payment_method'] ?: '-') ?></span>
            </div>
            <?php if (trim((string) $row['payment_reference']) !== '') { ?>
                <div class="itflow-glance-item" title="Reference">
                    <i class="fas fa-fw fa-hashtag"></i>
                    <span class="font-monospace text-truncate"><?= escapeHtml($row['payment_reference']) ?></span>
                </div>
            <?php } ?>
        </div>
    </div>

    <!-- Paid online: what the payment provider kept, when its fee is in the expenses -->
    <?php if ($online && $row['fee']) {
        $fee = floatval($row['fee']['expense_amount']); ?>
        <div class="d-flex flex-wrap align-items-center gap-3 mt-3 p-2 px-3 rounded border">
            <i class="fas fa-globe text-info"></i>
            <div class="flex-grow-1 small">
                The payment provider's fee was <a href="#" class="ajax-modal" data-modal-size="lg" data-modal-url="modals/expense/expense.php?id=<?= intval($row['fee']['expense_id']) ?>"><?= $money($fee, $row['fee']['expense_currency_code'] ?: $currency) ?></a>
                - <strong><?= $money($amount - $fee) ?></strong> came in after it
            </div>
        </div>
    <?php } ?>

</div>

<div class="modal-footer">
    <?php if ($has_invoice && $can_sales) { ?>
        <a class="btn btn-primary text-bold" href="invoice.php?invoice_id=<?= intval($row['invoice_id']) ?>"><i class="fas fa-file-invoice me-2"></i>Open Invoice</a>
    <?php } ?>
    <?php if ($can_receipt) { ?>
        <a class="btn btn-outline-secondary confirm-link <?= $receipt_to ? '' : 'disabled' ?>" href="post.php?payment_send_receipt=<?= $payment_id ?>&amp;csrf_token=<?= $_SESSION['csrf_token'] ?>"
            title="<?= $receipt_to ? 'To ' . escapeHtml(implode(', ', $receipt_to)) : 'No billing contact with an email' ?>"
            data-confirm-text="The receipt for <?= $money($amount) ?> goes to <?= escapeHtml(implode(', ', $receipt_to)) ?>."><i class="fas fa-paper-plane"></i><span class="d-none d-sm-inline ms-2">Email Receipt</span></a>
    <?php } ?>
    <?php if ($can_edit) { ?>
        <button type="button" class="btn btn-outline-secondary ajax-modal" data-modal-url="modals/payment/payment_edit.php?id=<?= $payment_id ?>"><i class="fas fa-edit"></i><span class="d-none d-sm-inline ms-2">Edit</span></button>
        <?php if ($cleared) { ?>
            <?= reconcileLockedButtonHtml($cleared) ?>
        <?php } else { ?>
            <a class="btn btn-outline-danger confirm-link" href="post.php?delete_payment=<?= $payment_id ?>&amp;csrf_token=<?= $_SESSION['csrf_token'] ?>"
                data-confirm-text="The payment of <?= $money($amount) ?> comes off <?= escapeHtml($row['invoice_prefix'] . $row['invoice_number']) ?>, which is owed again."><i class="fas fa-trash"></i><span class="d-none d-sm-inline ms-2">Delete</span></a>
        <?php } ?>
    <?php } ?>
    <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Close</button>
</div>

<?php
require_once '../../../includes/modal_footer.php';
