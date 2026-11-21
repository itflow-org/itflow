<?php

/*
 * A bulk action on the invoices ticked on the invoices list - Mark Sent, Send
 * by Email, Mark Paid, Cancel or Delete (functions/invoice_actions.php): which
 * invoices it is done to, which it leaves alone and why, and what it needs to
 * know. ?action= and ?set= (the key the ticked invoices are kept under).
 * Saved by invoice_bulk in agent/post/invoice.php.
 */

require_once '../../../includes/modal_header.php';

$action = (string) ($_GET['action'] ?? '');
$actions = invoiceBulkActions();
if (!isset($actions[$action]) || !invoiceBulkCan($action)) {
    exit(json_encode(['error' => 'That bulk action isn\'t one your role can use.']));
}
[$label, $icon, $about, $button] = $actions[$action];
$set = (string) ($_GET['set'] ?? '');
$invoice_ids = invoiceBulkRecall($set);
if (!$invoice_ids) {
    exit(json_encode(['error' => 'The invoices ticked have been forgotten - tick them again.']));
}
$plan = invoiceBulkPlan($action, $invoice_ids);
$do = count($plan['do']);
$skip = count($plan['skip']);
$shown = 40;

// What Mark Paid takes in, by currency
$owed = [];
foreach ($plan['do'] as $row) {
    $owed[$row['invoice_currency_code']] = ($owed[$row['invoice_currency_code']] ?? 0) + $row['balance_cents'];
}
$owed_text = implode(', ', array_map(function ($currency, $cents) {
    return billingMoney($cents / 100, $currency);
}, array_keys($owed), $owed));

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw <?= $icon ?> me-2"></i><?= escapeHtml($label) ?> <span class="fw-normal text-white-50 ms-1"><?= count($invoice_ids) ?> invoice<?= count($invoice_ids) === 1 ? '' : 's' ?> ticked</span></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off" data-invoice-bulk-form>
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="action" value="<?= escapeHtml($action) ?>">
    <input type="hidden" name="set" value="<?= escapeHtml($set) ?>">

    <div class="modal-body">
        <p class="text-body-secondary small"><?= escapeHtml($about) ?></p>

        <?php if ($do) { ?>
            <div class="fw-semibold mb-1"><?= $do ?> invoice<?= $do === 1 ? '' : 's' ?> <?= $action === 'pay' ? 'paid in full - ' . escapeHtml($owed_text) : 'to be ' . escapeHtml($actions[$action][4]) ?></div>
            <ul class="list-unstyled small border rounded px-2 py-1 mb-3 overflow-auto" style="max-height: 11rem;" data-invoice-bulk-do>
                <?php foreach (array_slice($plan['do'], 0, $shown) as $row) { ?>
                    <li class="d-flex gap-2 py-1">
                        <span class="fw-semibold text-nowrap"><?= escapeHtml($row['invoice_prefix'] . $row['invoice_number']) ?></span>
                        <span class="text-truncate flex-grow-1"><?= escapeHtml($row['client_name']) ?></span>
                        <span class="text-nowrap itflow-money"><?= escapeHtml(billingMoney(($action === 'pay' ? $row['balance_cents'] : intval(round(floatval($row['invoice_amount']) * 100))) / 100, $row['invoice_currency_code'])) ?></span>
                    </li>
                <?php } ?>
                <?php if ($do > $shown) { ?>
                    <li class="py-1 text-body-secondary">and <?= $do - $shown ?> more</li>
                <?php } ?>
            </ul>
        <?php } else { ?>
            <div class="alert alert-secondary small" data-invoice-bulk-none>None of them can be - see why below.</div>
        <?php } ?>

        <?php if ($skip) { ?>
            <div class="fw-semibold mb-1"><?= $skip ?> left alone</div>
            <ul class="list-unstyled small border rounded px-2 py-1 mb-3 overflow-auto" style="max-height: 9rem;" data-invoice-bulk-skip>
                <?php foreach (array_slice($plan['skip'], 0, $shown) as [$row, $why]) { ?>
                    <li class="d-flex gap-2 py-1">
                        <span class="fw-semibold text-nowrap"><?= escapeHtml($row['invoice_prefix'] . $row['invoice_number']) ?></span>
                        <span class="text-truncate flex-grow-1"><?= escapeHtml($row['client_name']) ?></span>
                        <span class="text-body-secondary text-nowrap"><?= escapeHtml($why) ?></span>
                    </li>
                <?php } ?>
                <?php if ($skip > $shown) { ?>
                    <li class="py-1 text-body-secondary">and <?= $skip - $shown ?> more</li>
                <?php } ?>
            </ul>
        <?php } ?>

        <?php if ($do && $action === 'sent') { ?>
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label">How were they sent? <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-paper-plane"></i></span>
                        <select class="form-select select2" name="sent_method" required>
                            <?php foreach (getSentMethods() as $sent_method) { ?>
                                <option value="<?= escapeHtml($sent_method) ?>"><?= escapeHtml($sent_method) ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>
                <div class="col-12">
                    <label class="form-label">Note</label>
                    <div class="input-group">
                        <span class="input-group-text align-items-start pt-2"><i class="fa fa-fw fa-sticky-note"></i></span>
                        <textarea class="form-control" name="note" rows="2" maxlength="500" placeholder="Goes in each one's history"></textarea>
                    </div>
                </div>
            </div>
        <?php } ?>

        <?php if ($do && $action === 'pay') {
            $pay_accounts = paymentAccounts();
            $pay_methods = paymentMethods('in'); ?>
            <div class="row g-3">
                <div class="col-sm-6">
                    <label class="form-label">Date <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                        <input type="date" class="form-control" name="date" max="2999-12-31" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>
                <div class="col-sm-6">
                    <label class="form-label">Method <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-money-check-alt"></i></span>
                        <select class="form-select select2" name="payment_method" required>
                            <option value="">- Select a payment method -</option>
                            <?php foreach ($pay_methods as $method) { ?>
                                <option <?= $config_default_payment_method === $method ? 'selected' : '' ?>><?= escapeHtml($method) ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>
                <div class="col-sm-6">
                    <label class="form-label">Reference</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-hashtag"></i></span>
                        <input type="text" class="form-control" name="reference" placeholder="Check number, transaction ID" maxlength="200">
                    </div>
                </div>
                <?php if (!moduleOn('bookkeeping')) { // Bookkeeping off - into the default account ?>
                    <input type="hidden" name="account" value="<?= bookkeepingAccount('payment') ?>">
                <?php } else { ?>
                <div class="col-sm-6">
                    <label class="form-label">Into account <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-piggy-bank"></i></span>
                        <select class="form-select select2" name="account" required>
                            <option value="">- Select an account -</option>
                            <?php foreach ($pay_accounts as $account_id => $account) { ?>
                                <option value="<?= $account_id ?>" <?= intval($config_default_payment_account) === $account_id ? 'selected' : '' ?> data-icon="fas fa-piggy-bank"
                                    data-description="<?= escapeHtml(billingMoney($account['balance'], $account['account_currency_code'])) ?>"><?= escapeHtml($account['account_name']) ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>
                <?php } ?>
                <?php if (!empty($config_smtp_provider)) { ?>
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input type="checkbox" class="form-check-input" role="switch" id="invoiceBulkReceipt" name="email_receipt" value="1" checked>
                            <label class="form-check-label" for="invoiceBulkReceipt">Email each client one receipt listing what was paid</label>
                        </div>
                    </div>
                <?php } ?>
            </div>
        <?php } ?>
    </div>
    <div class="modal-footer">
        <?php if ($do) { ?>
            <button type="submit" name="invoice_bulk" class="btn <?= $button ?> text-bold" data-invoice-bulk-submit><i class="fas <?= $icon ?> me-2"></i><?= escapeHtml($label) ?> <?= $do ?></button>
        <?php } ?>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i><?= $do && $action !== 'cancel' ? 'Cancel' : 'Close' ?></button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
