<?php

/*
 * A transfer's quick look - the account it left and the one it reached,
 * each with what it holds now and opening its ledger, how it was moved, the
 * notes, and who entered it. A transfer into a credit card is paying it.
 * Edit and Delete. Opened from the ledger and the Transfers pill. ?id=
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_financial');

$row = transferRow(intval($_GET['id'] ?? 0));
if (!$row) {
    echo json_encode(['error' => 'That transfer no longer exists.']);
    exit;
}

$transfer_id = intval($row['transfer_id']);
$cleared_out = reconcileLock('expense', $row['transfer_expense_id']);
$cleared_in = reconcileLock('revenue', $row['transfer_revenue_id']);
$what = transferWhat($row);
$from = accountRow($row['transfer_from_id']);
$to = accountRow($row['transfer_to_id']);
$types = accountTypes();
$side = function ($account, $fallback, $label) use ($types) {
    if (!$account) {
        return '<div class="small text-body-secondary">' . $label . '</div><div class="text-body-secondary">' . escapeHtml($fallback ?: 'An account since deleted') . '</div>';
    }
    return '<div class="small text-body-secondary">' . $label . '</div>'
        . '<a class="fw-semibold" href="transactions.php?account=' . intval($account['account_id']) . '"><i class="fas fa-fw ' . $types[$account['type']]['icon'] . ' me-1 text-body-secondary"></i>' . escapeHtml($account['account_name']) . '</a>'
        . '<div class="small text-body-secondary">Holds ' . escapeHtml(accountBalanceText($account)) . ' now' . ($account['account_archived_at'] ? ' - archived' : '') . '</div>';
};

ob_start();

?>

<div class="modal-header bg-dark" data-bs-theme="dark">
    <div class="d-flex align-items-center gap-3 min-w-0 flex-grow-1">
        <span class="itflow-asset-icon itflow-asset-icon-lg bg-secondary-subtle text-secondary-emphasis" aria-hidden="true"><i class="fas <?= $what === 'Card payment' ? 'fa-credit-card' : 'fa-exchange-alt' ?>"></i></span>
        <div class="min-w-0 flex-grow-1">
            <h5 class="modal-title fw-semibold text-break"><?= $what ?></h5>
            <div class="small text-body-secondary mt-1 text-truncate"><?= escapeHtml(date('D, M j, Y', strtotime($row['transfer_date']))) ?> &middot; money moved between your accounts</div>
        </div>
        <div class="text-end">
            <div class="fs-4 fw-semibold text-white text-nowrap"><?= escapeHtml(billingMoney($row['transfer_amount'], $row['transfer_currency_code'])) ?></div>
        </div>
    </div>
    <button type="button" class="btn-close btn-close-white ms-3" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

<div class="modal-body">

    <!-- From one account to the other -->
    <div class="d-flex flex-wrap align-items-center gap-3 p-3 rounded border bg-body-tertiary mb-3">
        <div class="flex-grow-1 min-w-0"><?= $side($from, $row['from_account_name'], 'Out of') ?></div>
        <i class="fas fa-arrow-right fa-lg text-body-secondary" aria-hidden="true"></i>
        <div class="flex-grow-1 min-w-0"><?= $side($to, $row['to_account_name'], 'Into') ?></div>
    </div>

    <div class="row g-2 itflow-asset-glance">
        <div class="col-sm-6">
            <div class="itflow-glance-item" title="Moved by">
                <i class="fas fa-fw fa-money-check-alt"></i>
                <span><?= escapeHtml($row['transfer_method'] ?: '-') ?></span>
            </div>
        </div>
        <div class="col-sm-6">
            <div class="itflow-glance-item" title="Entered">
                <i class="fas fa-fw fa-clock"></i>
                <span><span title="<?= escapeHtml($row['transfer_created_at']) ?>"><?= timeAgo($row['transfer_created_at']) ?></span><?php if ($row['created_by_name']) { ?> <span class="text-body-secondary">by <?= escapeHtml($row['created_by_name']) ?></span><?php } ?><?php if (!empty($row['revenue_import_key'])) { ?> <span class="text-body-secondary">from a bank statement</span><?php } ?></span>
            </div>
            <?= reconcileGlanceHtml($cleared_out) ?>
            <?= reconcileGlanceHtml($cleared_in) ?>
        </div>
    </div>

    <?php if (trim((string) $row['transfer_notes']) !== '') { ?>
        <div class="mt-3">
            <div class="small fw-semibold text-uppercase text-body-secondary mb-1">Notes</div>
            <div class="text-break" style="white-space: pre-line;"><?= escapeHtml($row['transfer_notes']) ?></div>
        </div>
    <?php } ?>

</div>

<div class="modal-footer">
    <?php if (lookupUserPermission('module_financial') >= 2) { ?>
        <button type="button" class="btn btn-primary text-bold ajax-modal" data-modal-size="lg" data-modal-url="modals/transfer/transfer_edit.php?id=<?= $transfer_id ?>"><i class="fas fa-edit me-2"></i>Edit</button>
    <?php } ?>
    <?php if (lookupUserPermission('module_financial') >= 3) { ?>
        <?php if ($cleared_out || $cleared_in) { ?>
            <?= reconcileLockedButtonHtml($cleared_out ?: $cleared_in) ?>
        <?php } else { ?>
            <a class="btn btn-outline-danger confirm-link" href="post.php?delete_transfer=<?= $transfer_id ?>&amp;csrf_token=<?= $_SESSION['csrf_token'] ?>"
                data-confirm-text="<?= escapeHtml(transferLabel($row)) ?> - both sides go, and the balances go back to before it."><i class="fas fa-trash"></i><span class="d-none d-sm-inline ms-2">Delete</span></a>
        <?php } ?>
    <?php } ?>
    <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Close</button>
</div>

<?php
require_once '../../../includes/modal_footer.php';
