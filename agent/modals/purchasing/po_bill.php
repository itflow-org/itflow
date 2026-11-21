<?php

/*
 * Record a purchase order's vendor bill as an expense - from its vendor, for
 * its one client if it has one, linked to the PO. The account, category and
 * how it was paid start as the vendor's last expense had them. Posts po_bill.
 */

require_once '../../../includes/modal_header.php';

poRequireLevel(2);
enforceUserPermission('module_financial', 2);

$po = poRow(intval($_GET['po_id'] ?? 0));
if (!$po || !poAccessible($po['po_id'])) {
    exit(json_encode(['error' => 'That purchase order no longer exists.']));
}
if (poExpense($po)) {
    exit(json_encode(['error' => 'Its vendor bill is recorded already.']));
}
if (!in_array($po['po_status'], ['ordered', 'partial', 'received'], true)) {
    exit(json_encode(['error' => 'The vendor bill is recorded once it is ordered.']));
}
$vendor_id = intval($po['po_vendor_id']);
$defaults = expenseVendorDefaults()[$vendor_id] ?? ['category' => 0, 'account' => 0, 'method' => ''];
$category = intval($defaults['category']);
$account = intval($defaults['account']) ?: intval($config_default_expense_account ?? 0);
$method = (string) ($defaults['method'] !== '' ? $defaults['method'] : ($config_default_expense_payment_method ?? ''));
$accounts = paymentAccounts(0);

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-receipt me-2"></i>Vendor Bill <span class="fw-normal text-white-50 ms-1"><?= escapeHtml(poNumber($po)) ?></span></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="po_id" value="<?= intval($po['po_id']) ?>">

    <div class="modal-body">
        <p class="small text-body-secondary">An expense from <?= escapeHtml((string) $po['vendor_name']) ?>, linked to <?= escapeHtml(poNumber($po)) ?> - what it put on the shelf is noted as bought on it.</p>
        <div class="row g-3">
            <div class="col-sm-6">
                <label class="form-label">Date <strong class="text-danger">*</strong></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-fw fa-calendar"></i></span>
                    <input type="date" class="form-control" name="date" required value="<?= date('Y-m-d') ?>">
                </div>
            </div>
            <div class="col-sm-6">
                <label class="form-label">Amount <strong class="text-danger">*</strong></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-fw fa-dollar-sign"></i></span>
                    <input type="text" class="form-control text-end" name="amount" inputmode="decimal" required value="<?= number_format(floatval($po['po_total']), 2, '.', '') ?>">
                </div>
                <div class="form-text">The purchase order comes to <?= escapeHtml(poMoney($po['po_total'], $po['po_currency_code'])) ?> - type what the bill says, tax included.</div>
            </div>
            <div class="col-sm-6">
                <label class="form-label">Category <strong class="text-danger">*</strong></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-fw fa-list"></i></span>
                    <select class="form-select select2" name="category" required>
                        <option value="">- Select a category -</option>
                        <?= categoryOptionsHtml('Expense', $category) ?>
                    </select>
                </div>
            </div>
            <?php if (!moduleOn('bookkeeping')) { // Bookkeeping off - out of the default account ?>
                <input type="hidden" name="account" value="<?= bookkeepingAccount('expense') ?>">
            <?php } else { ?>
            <div class="col-sm-6">
                <label class="form-label">Paid from <strong class="text-danger">*</strong></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-fw fa-piggy-bank"></i></span>
                    <select class="form-select select2" name="account" required>
                        <option value="">- Select an account -</option>
                        <?php foreach ($accounts as $account_id => $row) { ?>
                            <option value="<?= $account_id ?>" <?= $account === $account_id ? 'selected' : '' ?> data-icon="fas fa-piggy-bank" data-description="<?= escapeHtml(billingMoney($row['balance'], $row['account_currency_code'])) ?>"><?= escapeHtml($row['account_name']) ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>
            <?php } ?>
            <div class="col-sm-6">
                <label class="form-label">Paid by</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-fw fa-credit-card"></i></span>
                    <select class="form-select select2" name="payment_method">
                        <option value="">- Select a payment method -</option>
                        <?php foreach (paymentMethods('out') as $option) { ?>
                            <option <?= $option === $method ? 'selected' : '' ?>><?= escapeHtml($option) ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>
            <div class="col-sm-6">
                <label class="form-label">Their invoice number</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-fw fa-hashtag"></i></span>
                    <input type="text" class="form-control" name="reference" maxlength="200" placeholder="<?= escapeHtml(poNumber($po)) ?> when empty">
                </div>
            </div>
            <div class="col-12">
                <label class="form-label">Description</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-fw fa-align-left"></i></span>
                    <input type="text" class="form-control" name="description" value="<?= escapeHtml(poNumber($po) . ' - ' . $po['vendor_name']) ?>">
                </div>
            </div>
        </div>
    </div>

    <div class="modal-footer">
        <button type="submit" name="po_bill" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Record</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
