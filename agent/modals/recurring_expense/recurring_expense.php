<?php

/*
 * A recurring expense's quick look - what it costs and how often, the
 * schedule with the next few dates and when it ends, the account, how it is
 * paid, the client and billing it on to them (each time, with a markup, or
 * covered by one of their recurring invoices, with the margin left), and
 * the expenses it has recorded. Record Now, Move On
 * (when it has fallen behind), Pause or Resume, Edit and Delete. Opened from
 * the recurring expenses list, the vendor page and an expense it recorded.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_financial');

$row = recurringExpenseRow(intval($_GET['id'] ?? 0));
if (!$row) {
    echo json_encode(['error' => 'That recurring expense no longer exists.']);
    exit;
}

$id = intval($row['recurring_expense_id']);
$client_id = intval($row['recurring_expense_client_id']);
$can_edit = lookupUserPermission('module_financial') >= 2;
$can_delete = lookupUserPermission('module_financial') >= 3;
$state = recurringExpenseState($row);
$currency = (string) $row['recurring_expense_currency_code'];
$amount = floatval($row['recurring_expense_amount']);
$monthly = $amount / recurringInvoiceMonths($row['recurring_expense_frequency']);
$next = (string) $row['recurring_expense_next_date'];
$behind = $state === 'active' && $next < date('Y-m-d');
$upcoming = recurringExpenseUpcoming($row, 4);
$made = recurringExpenseMade($id, 8);
$can_sales = lookupUserPermission('module_sales') >= 1;
$covered_by = intval($row['recurring_expense_recurring_invoice_id']);
$cover = $covered_by && $row['recurring_invoice_number'] !== null ? recurringInvoiceCosts($covered_by) : null;
$markup = floatval($row['recurring_expense_markup']);
$post_url = function ($action, $extra = '') use ($id) {
    return "post.php?$action=$id$extra&amp;csrf_token=" . $_SESSION['csrf_token'];
};

ob_start();

?>

<div class="modal-header bg-dark" data-bs-theme="dark">
    <div class="d-flex align-items-center gap-3 min-w-0 flex-grow-1">
        <?= vendorTile($row['vendor_name'] ?: 'Recurring', 'lg') ?>
        <div class="min-w-0 flex-grow-1">
            <h5 class="modal-title d-flex flex-wrap align-items-center column-gap-2">
                <span class="fw-semibold text-break"><?= escapeHtml($row['vendor_name'] ?: 'No vendor') ?></span>
                <?= recurringExpenseNextPill($row) ?>
            </h5>
            <div class="small text-body-secondary mt-1 text-truncate"><?= escapeHtml(implode(' · ', array_filter([$row['recurring_expense_description'], $row['category_name']]))) ?></div>
        </div>
        <div class="text-end">
            <div class="fs-4 fw-semibold itflow-money text-white"><?= escapeHtml(billingMoney($amount, $currency)) ?></div>
            <div class="small text-body-secondary"><?= escapeHtml(strtolower(recurringInvoiceFrequencies()[$row['recurring_expense_frequency']] ?? 'monthly')) ?></div>
        </div>
    </div>
    <button type="button" class="btn-close btn-close-white ms-3" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

<div class="modal-body">

    <?php if ($behind && $next < date('Y-m-d', strtotime('-' . RECURRING_EXPENSE_GRACE_DAYS . ' days'))) { ?>
        <div class="alert alert-warning small mb-3">
            <i class="fas fa-exclamation-triangle me-2"></i>It was due on <?= escapeHtml(date('M j, Y', strtotime($next))) ?> - too far back for the nightly run to record by itself.
            Record Now records that run and moves on to the next, so press it for each missed run. Or Move On skips them all without recording them.
        </div>
    <?php } elseif ($behind) { ?>
        <div class="alert alert-info small mb-3"><i class="fas fa-info-circle me-2"></i>It was due on <?= escapeHtml(date('M j, Y', strtotime($next))) ?> - the next nightly run records it, or Record Now does it now.</div>
    <?php } ?>

    <!-- At a glance -->
    <div class="row g-2 itflow-asset-glance">
        <div class="col-sm-6">
            <div class="itflow-glance-item" title="Schedule">
                <i class="fas fa-fw fa-redo-alt"></i>
                <span><?= escapeHtml(recurringExpenseScheduleText($row)) ?><?= $row['recurring_expense_frequency'] !== 'month' ? ' <span class="text-body-secondary">&middot; ' . escapeHtml(billingMoney($monthly, $currency)) . ' a month</span>' : '' ?></span>
            </div>
            <div class="itflow-glance-item" title="Coming up">
                <i class="fas fa-fw fa-calendar-alt"></i>
                <?php if ($upcoming) { ?>
                    <span><?= escapeHtml(implode(', ', array_map(function ($d) { return date('M j', strtotime($d)); }, $upcoming))) ?><?= count($upcoming) === 4 ? '...' : ' <span class="text-body-secondary">&middot; then it ends</span>' ?></span>
                <?php } elseif ($state === 'ended') { ?>
                    <span class="text-body-secondary">Ended<?= $row['recurring_expense_last_sent'] ? ' - the last one was on ' . escapeHtml(date('M j, Y', strtotime($row['recurring_expense_last_sent']))) : '' ?></span>
                <?php } else { ?>
                    <span class="text-body-secondary">Paused - next on <?= escapeHtml(date('M j, Y', strtotime($next))) ?> when it is resumed</span>
                <?php } ?>
            </div>
            <div class="itflow-glance-item" title="Category">
                <i class="fas fa-fw fa-list"></i>
                <span><?= expenseCategoryDot($row['category_color']) ?><?= escapeHtml($row['category_name'] ?: 'No category') ?></span>
            </div>
            <?php if ($row['recurring_expense_reference']) { ?>
                <div class="itflow-glance-item" title="Reference">
                    <i class="fas fa-fw fa-hashtag"></i>
                    <span class="font-monospace text-break"><?= escapeHtml($row['recurring_expense_reference']) ?></span>
                </div>
            <?php } ?>
        </div>
        <div class="col-sm-6">
            <?php if (intval($row['recurring_expense_vendor_id'])) { ?>
                <div class="itflow-glance-item" title="Vendor">
                    <i class="fas fa-fw fa-building"></i>
                    <a class="text-truncate" href="vendor.php?vendor_id=<?= intval($row['recurring_expense_vendor_id']) ?>"><?= escapeHtml($row['vendor_name']) ?></a>
                </div>
            <?php } ?>
            <div class="itflow-glance-item" title="Paid from">
                <i class="fas fa-fw fa-piggy-bank"></i>
                <span><?= escapeHtml($row['account_name'] ?: 'No account') ?><?= $row['recurring_expense_payment_method'] ? ' <span class="text-body-secondary">&middot; ' . escapeHtml($row['recurring_expense_payment_method']) . '</span>' : '' ?></span>
            </div>
            <div class="itflow-glance-item" title="Client">
                <i class="fas fa-fw fa-user"></i>
                <?php if ($client_id) { ?>
                    <a class="text-truncate" href="client_overview.php?client_id=<?= $client_id ?>"><?= escapeHtml($row['client_name']) ?></a>
                <?php } else { ?>
                    <span class="text-body-secondary">Not for a client</span>
                <?php } ?>
            </div>
            <?php if ($client_id) { ?>
                <div class="itflow-glance-item" title="Billing it on">
                    <i class="fas fa-fw fa-file-invoice-dollar"></i>
                    <?php if ($cover) {
                        $cover_number = $row['recurring_invoice_prefix'] . $row['recurring_invoice_number']; ?>
                        <span>Covered by <?= $can_sales ? '<a href="recurring_invoice.php?recurring_invoice_id=' . $covered_by . '">' . escapeHtml($cover_number) . '</a>' : escapeHtml($cover_number) ?>
                            <span class="text-body-secondary">&middot; <?= escapeHtml(billingMoney($cover['revenue'], $currency)) ?> a month before tax<?= $cover['percent'] !== null ? ', ' . $cover['percent'] . '% margin after ' . (count(array_filter(array_column($cover['rows'], 'running'))) === 1 ? 'this cost' : 'its costs') : '' ?></span></span>
                    <?php } elseif ($covered_by) { ?>
                        <span class="text-body-secondary">Covered by a recurring invoice since deleted</span>
                    <?php } elseif (intval($row['recurring_expense_billable'])) { ?>
                        <span>Billed on each time at <?= escapeHtml(billingMoney(expenseBillPrice($amount, $markup), $currency)) ?> <span class="text-body-secondary">&middot; <?= escapeHtml(rtrim(rtrim(number_format($markup, 2, '.', ''), '0'), '.')) ?>% markup</span></span>
                    <?php } else { ?>
                        <span class="text-body-secondary">Our cost - not billed on</span>
                    <?php } ?>
                </div>
            <?php } ?>
            <div class="itflow-glance-item" title="This year">
                <i class="fas fa-fw fa-coins"></i>
                <span><?= escapeHtml(billingMoney($row['made_year'], $currency)) ?> recorded this year <span class="text-body-secondary">&middot; <?= escapeHtml(billingMoney($amount * 12 / recurringInvoiceMonths($row['recurring_expense_frequency']), $currency)) ?> a year at this amount</span></span>
            </div>
        </div>
    </div>

    <!-- What it has recorded -->
    <div class="mt-3">
        <div class="d-flex justify-content-between align-items-center mb-1">
            <div class="small fw-semibold text-uppercase text-body-secondary">Recorded</div>
            <?php if (intval($row['made_count']) > count($made)) { ?>
                <a class="small" href="expenses.php?recurring_expense_id=<?= $id ?>">All <?= intval($row['made_count']) ?></a>
            <?php } ?>
        </div>
        <?php if ($made) { ?>
            <?php foreach ($made as $expense) { ?>
                <div class="d-flex justify-content-between gap-2 small py-1 border-bottom">
                    <a href="#" class="ajax-modal" data-modal-size="lg" data-modal-url="modals/expense/expense.php?id=<?= intval($expense['expense_id']) ?>"><?= escapeHtml($expense['expense_date']) ?></a>
                    <span class="flex-grow-1 text-end">
                        <?php if ($expense['expense_receipt']) { ?><i class="fas fa-paperclip text-success me-2" title="Receipt attached"></i><?php } ?>
                        <span class="itflow-money"><?= escapeHtml(billingMoney($expense['expense_amount'], $expense['expense_currency_code'])) ?></span>
                    </span>
                </div>
            <?php } ?>
        <?php } else { ?>
            <div class="small text-body-secondary">Nothing yet - the first one is recorded on <?= escapeHtml(date('M j, Y', strtotime($next))) ?>.</div>
        <?php } ?>
    </div>

</div>

<div class="modal-footer">
    <?php if ($can_edit) { ?>
        <?php if ($state !== 'ended') { ?>
            <a class="btn btn-primary text-bold confirm-link" href="<?= $post_url('recurring_expense_record', '&amp;run=' . $next) ?>"
                data-confirm-title="Record the <?= escapeHtml(date('M j', strtotime($next))) ?> run now?"
                data-confirm-text="An expense of <?= escapeHtml(billingMoney($amount, $currency)) ?> dated <?= escapeHtml(date('M j, Y', strtotime(min($next, date('Y-m-d'))))) ?>, and the next one moves to <?= escapeHtml(date('M j, Y', strtotime(recurringExpenseAfter($row, $next)))) ?>."
                data-confirm-button="Record"><i class="fas fa-bolt me-2"></i>Record Now</a>
        <?php } ?>
        <?php if ($behind) { ?>
            <a class="btn btn-outline-secondary confirm-link" href="<?= $post_url('recurring_expense_catch_up') ?>"
                data-confirm-title="Skip the missed runs?" data-confirm-text="The next date moves on to <?= escapeHtml(date('M j, Y', strtotime(recurringExpenseCatchUp($row)))) ?>. Nothing is recorded for the runs it missed." data-confirm-button="Move on">
                <i class="fas fa-forward me-2"></i>Move On</a>
        <?php } ?>
        <div class="btn-group">
            <?php if ($state === 'active') { ?>
                <a class="btn btn-outline-secondary" href="<?= $post_url('recurring_expense_set_status', '&amp;status=0') ?>"><i class="fas fa-pause me-2"></i>Pause</a>
            <?php } elseif ($state === 'paused') { ?>
                <a class="btn btn-outline-secondary" href="<?= $post_url('recurring_expense_set_status', '&amp;status=1') ?>"><i class="fas fa-play me-2"></i>Resume</a>
            <?php } ?>
            <button type="button" class="btn btn-outline-secondary ajax-modal" data-modal-size="lg" data-modal-url="modals/recurring_expense/recurring_expense_edit.php?id=<?= $id ?>"><i class="fas fa-edit me-2"></i>Edit</button>
        </div>
    <?php } ?>
    <?php if ($can_delete) { ?>
        <a class="btn btn-outline-danger confirm-link" href="<?= $post_url('delete_recurring_expense') ?>"
            data-confirm-text="<?= escapeHtml(recurringExpenseLabel($row)) ?> goes for good. The expenses it recorded stay."><i class="fas fa-trash"></i><span class="d-none d-sm-inline ms-2">Delete</span></a>
    <?php } ?>
    <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Close</button>
</div>

<?php
require_once '../../../includes/modal_footer.php';
