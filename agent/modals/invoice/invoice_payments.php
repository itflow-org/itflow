<?php

/*
 * An invoice's payments at a glance, from the invoices list - each payment,
 * what has been paid and what is left. ?invoice_id=
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_sales');

$invoice_id = intval($_GET['invoice_id'] ?? 0);
$invoice = billingRow('invoice', $invoice_id);
if (!$invoice) {
    exit(json_encode(['error' => 'That invoice no longer exists.']));
}
$client_id = intval($invoice['client_id']);
enforceClientAccess();

$payments = invoicePayments($invoice_id);
$state = invoiceState($invoice, invoicePaid($invoice_id));
$currency = (string) $invoice['invoice_currency_code'];
$total = floatval($invoice['invoice_amount']);
$progress = $state['paid'] > 0 && $total > 0 ? min(100, round($state['paid'] / $total * 100)) : 0;
$can_edit = lookupUserPermission('module_sales') >= 2 && lookupUserPermission('module_financial') >= 2;
$can_manage = lookupUserPermission('module_sales') >= 3 && lookupUserPermission('module_financial') >= 3;
$can_pay = $can_edit && invoiceCanPay($invoice, $state);

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-credit-card me-2"></i>Payments <span class="fw-normal text-white-50 ms-1"><?= escapeHtml(billingNumber('invoice', $invoice)) ?> &middot; <?= escapeHtml($invoice['client_name']) ?></span></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
    <div class="d-flex justify-content-between align-items-end mb-2">
        <div>
            <div class="itflow-billing-stat-label"><?= $state['balance'] > 0 ? 'Still owed' : 'Paid in full' ?></div>
            <div class="itflow-billing-stat-value <?= $state['state'] === 'overdue' ? 'text-danger-emphasis' : '' ?>"><?= escapeHtml(billingMoney($state['balance'] > 0 ? $state['balance'] : $state['paid'], $currency)) ?></div>
        </div>
        <div class="text-end small text-body-secondary">
            <?= escapeHtml(billingMoney($state['paid'], $currency)) ?> paid of <?= escapeHtml(billingMoney($total, $currency)) ?>
            <div><?= invoiceStatusBadge($invoice['invoice_status'], $state['state'] === 'overdue') ?></div>
        </div>
    </div>
    <div class="progress itflow-billing-progress mb-3" role="progressbar" aria-label="Paid" aria-valuenow="<?= $progress ?>" aria-valuemin="0" aria-valuemax="100">
        <div class="progress-bar bg-success" style="width: <?= $progress ?>%"></div>
    </div>

    <?php if ($payments) { ?>
        <div class="list-group">
            <?php foreach ($payments as $payment) { ?>
                <div class="list-group-item d-flex align-items-center gap-3">
                    <span class="itflow-asset-icon itflow-asset-icon-sm bg-success-subtle text-success-emphasis" aria-hidden="true"><i class="fas fa-fw fa-check"></i></span>
                    <div class="flex-grow-1 min-w-0">
                        <div class="fw-semibold itflow-money"><?= escapeHtml(billingMoney($payment['payment_amount'], $payment['payment_currency_code'])) ?></div>
                        <div class="small text-body-secondary text-truncate"><?= escapeHtml(implode(' · ', array_filter([$payment['payment_method'], $payment['payment_reference'], $payment['account_name']]))) ?></div>
                    </div>
                    <div class="small text-body-secondary text-nowrap"><?= escapeHtml($payment['payment_date']) ?></div>
                    <?php if ($can_manage) { ?>
                        <a class="btn btn-sm btn-link text-body-secondary ajax-modal" href="#" data-modal-url="modals/payment/payment_edit.php?id=<?= intval($payment['payment_id']) ?>" title="Edit payment"><i class="fas fa-fw fa-edit"></i></a>
                    <?php } ?>
                </div>
            <?php } ?>
        </div>
    <?php } else { ?>
        <div class="text-body-secondary">No payments yet.</div>
    <?php } ?>
</div>
<div class="modal-footer">
    <?php if ($can_pay) { ?>
        <button type="button" class="btn btn-success ajax-modal me-auto" data-modal-url="modals/payment/payment_add.php?id=<?= $invoice_id ?>"><i class="fas fa-plus me-2"></i>Add Payment</button>
    <?php } ?>
    <a href="invoice.php?invoice_id=<?= $invoice_id ?>" class="btn btn-primary text-bold"><i class="fas fa-file-invoice-dollar me-2"></i>Open Invoice</a>
    <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Close</button>
</div>

<?php
require_once '../../../includes/modal_footer.php';
