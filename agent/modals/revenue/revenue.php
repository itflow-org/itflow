<?php

/*
 * Other income's quick look - what it was, the client it came from (and what
 * else came in from them this year), its category, the account it went into,
 * how it was paid, its reference and who entered it. Edit, Copy and Delete.
 * Opened from the Income list, Transactions and the reports. ?id=
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_financial');

$row = incomeRevenueRow(intval($_GET['id'] ?? 0));
if (!$row) {
    echo json_encode(['error' => 'That income no longer exists, or is not one you can see.']);
    exit;
}

$revenue_id = intval($row['revenue_id']);
$cleared = reconcileLock('revenue', $revenue_id);
$client_id = intval($row['revenue_client_id']);
$amount = floatval($row['revenue_amount']);
$currency = (string) $row['revenue_currency_code'];
$description = trim((string) $row['revenue_description']);
$title = trim(strtok($description, "\n"));
if ($title === '') {
    $title = $row['category_name'] ?: 'Other income';
}
$rest = trim(substr($description, strlen(strtok($description, "\n"))));
$can_edit = incomeCanEdit();
$can_delete = incomeCanDelete();
$client_year = $client_id && $row['client_name'] !== null ? incomeTotal(date('Y-01-01'), date('Y-m-d'), $client_id) : null;

ob_start();

?>

<div class="modal-header bg-dark" data-bs-theme="dark">
    <div class="d-flex align-items-center gap-3 min-w-0 flex-grow-1">
        <span class="itflow-asset-icon itflow-asset-icon-lg bg-success-subtle text-success-emphasis" aria-hidden="true"><i class="fas fa-hand-holding-usd"></i></span>
        <div class="min-w-0 flex-grow-1">
            <h5 class="modal-title fw-semibold text-break"><?= escapeHtml($title) ?></h5>
            <div class="small text-body-secondary mt-1 text-truncate"><?= escapeHtml(date('D, M j, Y', strtotime($row['revenue_date']))) ?> &middot; Other income</div>
        </div>
        <div class="text-end">
            <div class="fs-4 fw-semibold text-nowrap <?= $amount < 0 ? 'text-danger' : 'text-white' ?>"><?= escapeHtml(billingMoney($amount, $currency)) ?></div>
            <?php if ($amount < 0) { ?>
                <div class="small text-body-secondary">went back out</div>
            <?php } ?>
        </div>
    </div>
    <button type="button" class="btn-close btn-close-white ms-3" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

<div class="modal-body">

    <!-- At a glance -->
    <div class="row g-2 itflow-asset-glance">
        <div class="col-sm-6">
            <div class="itflow-glance-item" title="From client">
                <i class="fas fa-fw fa-building"></i>
                <?php if ($client_id && $row['client_name'] !== null) { ?>
                    <a class="text-truncate" href="income.php?client_id=<?= $client_id ?>" title="What came in from them"><?= escapeHtml($row['client_name']) ?></a>
                <?php } else { ?>
                    <span class="text-body-secondary">Not from a client</span>
                <?php } ?>
            </div>
            <div class="itflow-glance-item" title="Category">
                <i class="fas fa-fw fa-tag"></i>
                <?php if (intval($row['revenue_category_id']) && $row['category_name'] !== null) { ?>
                    <a class="text-reset text-truncate" href="income.php?category=<?= intval($row['revenue_category_id']) ?>"><?= expenseCategoryDot($row['category_color']) ?><?= escapeHtml($row['category_name']) ?></a>
                <?php } else { ?>
                    <span class="text-warning-emphasis">No category - the reports show it as No category</span>
                <?php } ?>
            </div>
            <div class="itflow-glance-item" title="Entered">
                <i class="fas fa-fw fa-clock"></i>
                <span><span title="<?= escapeHtml($row['revenue_created_at']) ?>"><?= timeAgo($row['revenue_created_at']) ?></span><?php if ($row['created_by_name']) { ?> <span class="text-body-secondary">by <?= escapeHtml($row['created_by_name']) ?></span><?php } ?><?php if (!empty($row['revenue_import_key'])) { ?> <span class="text-body-secondary">from a bank statement</span><?php } ?></span>
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
                <span><?= escapeHtml($row['revenue_payment_method'] ?: '-') ?></span>
            </div>
            <?php if (trim((string) $row['revenue_reference']) !== '') { ?>
                <div class="itflow-glance-item" title="Reference">
                    <i class="fas fa-fw fa-hashtag"></i>
                    <span class="font-monospace text-truncate"><?= escapeHtml($row['revenue_reference']) ?></span>
                </div>
            <?php } ?>
        </div>
    </div>

    <?php if ($rest !== '') { ?>
        <div class="mt-3">
            <div class="small fw-semibold text-uppercase text-body-secondary mb-1">Notes</div>
            <div class="text-break" style="white-space: pre-line;"><?= escapeHtml($rest) ?></div>
        </div>
    <?php } ?>

    <?php if ($client_year !== null) { ?>
        <div class="d-flex align-items-center gap-3 mt-3 p-2 px-3 rounded border bg-body-tertiary small">
            <i class="fas fa-chart-line text-body-secondary"></i>
            <div class="flex-grow-1"><strong><?= escapeHtml(billingMoney($client_year)) ?></strong> in from <?= escapeHtml($row['client_name']) ?> so far this year, invoice payments included</div>
            <a href="income.php?client_id=<?= $client_id ?>">See it all</a>
        </div>
    <?php } ?>

</div>

<div class="modal-footer">
    <?php if ($can_edit) { ?>
        <button type="button" class="btn btn-primary text-bold ajax-modal" data-modal-size="lg" data-modal-url="modals/revenue/revenue_edit.php?id=<?= $revenue_id ?>"><i class="fas fa-edit me-2"></i>Edit</button>
        <button type="button" class="btn btn-outline-secondary ajax-modal" data-modal-size="lg" data-modal-url="modals/revenue/revenue_copy.php?id=<?= $revenue_id ?>"><i class="fas fa-copy me-2"></i>Copy</button>
    <?php } ?>
    <?php if ($can_delete && $cleared) { ?>
        <?= reconcileLockedButtonHtml($cleared) ?>
    <?php } elseif ($can_delete) { ?>
        <a class="btn btn-outline-danger confirm-link" href="post.php?delete_revenue=<?= $revenue_id ?>&amp;csrf_token=<?= $_SESSION['csrf_token'] ?>"
            data-confirm-text="<?= escapeHtml(incomeRevenueLabel($row)) ?> goes for good."><i class="fas fa-trash"></i><span class="d-none d-sm-inline ms-2">Delete</span></a>
    <?php } ?>
    <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Close</button>
</div>

<?php
require_once '../../../includes/modal_footer.php';
