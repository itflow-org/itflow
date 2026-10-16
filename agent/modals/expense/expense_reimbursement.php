<?php

/*
 * A reimbursement's quick look - who was paid back, how much, when, from
 * which account and how, who entered it, and the expenses it paid back.
 * Undo takes the money out of the account away and makes the expenses to
 * reimburse again. Opened from an expense it paid back and Transactions.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_financial');

$row = reimbursementRow(intval($_GET['id'] ?? 0));
if (!$row) {
    exit(json_encode(['error' => 'That reimbursement no longer exists - it was undone.']));
}

$reimbursement_id = intval($row['reimbursement_id']);
$currency = (string) $row['reimbursement_currency_code'];
$expenses = reimbursementExpenses($reimbursement_id);
$can_undo = lookupUserPermission('module_financial') >= 3;
$expenses_total = array_sum(array_map(function ($e) { return floatval($e['expense_amount']); }, $expenses));

ob_start();

?>
<div class="modal-header bg-dark" data-bs-theme="dark">
    <div class="d-flex align-items-center gap-3 min-w-0 flex-grow-1">
        <span class="itflow-asset-icon itflow-asset-icon-lg bg-success-subtle text-success-emphasis" aria-hidden="true"><i class="fas fa-hand-holding-usd"></i></span>
        <div class="min-w-0 flex-grow-1">
            <h5 class="modal-title fw-semibold text-break">Paid back to <?= escapeHtml($row['user_name'] ?: 'an agent since deleted') ?></h5>
            <div class="small text-body-secondary mt-1"><?= escapeHtml(date('M j, Y', strtotime($row['reimbursement_date']))) ?> · <?= count($expenses) ?> expense<?= count($expenses) === 1 ? '' : 's' ?></div>
        </div>
        <div class="fs-4 fw-semibold itflow-money text-white"><?= escapeHtml(billingMoney($row['reimbursement_amount'], $currency)) ?></div>
    </div>
    <button type="button" class="btn-close btn-close-white ms-3" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

<div class="modal-body">
    <div class="row g-2 itflow-asset-glance">
        <div class="col-sm-6">
            <div class="itflow-glance-item" title="Paid from">
                <i class="fas fa-fw fa-piggy-bank"></i>
                <span><?= escapeHtml($row['account_name'] ?: 'An account since deleted') ?><?= $row['reimbursement_payment_method'] ? ' <span class="text-body-secondary">&middot; ' . escapeHtml($row['reimbursement_payment_method']) . '</span>' : '' ?></span>
            </div>
            <?php if ($row['reimbursement_reference']) { ?>
                <div class="itflow-glance-item" title="Reference">
                    <i class="fas fa-fw fa-hashtag"></i>
                    <span class="font-monospace text-break"><?= escapeHtml($row['reimbursement_reference']) ?></span>
                </div>
            <?php } ?>
        </div>
        <div class="col-sm-6">
            <div class="itflow-glance-item" title="Entered">
                <i class="fas fa-fw fa-clock"></i>
                <span><span title="<?= escapeHtml($row['reimbursement_created_at']) ?>"><?= timeAgo($row['reimbursement_created_at']) ?></span><?php if ($row['created_by_name']) { ?> <span class="text-body-secondary">by <?= escapeHtml($row['created_by_name']) ?></span><?php } ?></span>
            </div>
        </div>
    </div>

    <div class="mt-3">
        <div class="small fw-semibold text-uppercase text-body-secondary mb-1">What it paid back</div>
        <?php foreach ($expenses as $expense) { ?>
            <div class="d-flex justify-content-between gap-2 small py-1 border-bottom">
                <a class="text-truncate ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/expense/expense.php?id=<?= intval($expense['expense_id']) ?>">
                    <span class="text-body-secondary me-1"><?= escapeHtml($expense['expense_date']) ?></span><?= escapeHtml($expense['vendor_name'] ?: 'Expense') ?><?= trim((string) $expense['expense_description']) !== '' ? ' - ' . escapeHtml(truncate(strtok((string) $expense['expense_description'], "\n"), 50)) : '' ?>
                </a>
                <span class="itflow-money text-nowrap"><?= escapeHtml(billingMoney($expense['expense_amount'], $expense['expense_currency_code'])) ?></span>
            </div>
        <?php } ?>
        <?php if (!$expenses) { ?>
            <div class="small text-body-secondary">The expenses it paid back have since been deleted.</div>
        <?php } elseif (abs($expenses_total - floatval($row['reimbursement_amount'])) >= 0.005) { ?>
            <div class="small text-warning-emphasis mt-2"><i class="fas fa-exclamation-triangle me-1"></i>They come to <?= escapeHtml(billingMoney($expenses_total, $currency)) ?> now - one was changed or deleted after it was paid back.</div>
        <?php } ?>
    </div>
</div>

<div class="modal-footer">
    <?php if ($can_undo) { ?>
        <a class="btn btn-outline-danger confirm-link me-auto" href="post.php?expense_reimbursement_delete=<?= $reimbursement_id ?>&amp;csrf_token=<?= $_SESSION['csrf_token'] ?>"
            data-confirm-text="The <?= escapeHtml(billingMoney($row['reimbursement_amount'], $currency)) ?> comes back off <?= escapeHtml($row['account_name'] ?: 'the account') ?>, and its expenses are to reimburse again."><i class="fas fa-undo-alt me-2"></i>Undo</a>
    <?php } ?>
    <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Close</button>
</div>

<?php
require_once '../../../includes/modal_footer.php';
