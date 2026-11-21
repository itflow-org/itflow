<?php

/*
 * Do one thing to recurring invoices - the ones ticked on the list, or one
 * from its own Pause button: ?action=pause (until a day, or until resumed),
 * next_date or frequency, with recurring_invoice_ids[]. Posts
 * bulk_recurring_invoices (agent/post/billing.php).
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_sales', 2);

$action = (string) ($_GET['action'] ?? '');
if (!in_array($action, ['pause', 'next_date', 'frequency'], true)) {
    exit(json_encode(['error' => 'Pick what to do with them.']));
}

// The ones the agent can reach - billingRow() is scoped to their clients
$rows = [];
foreach (array_unique(array_filter(array_map('intval', (array) ($_GET['recurring_invoice_ids'] ?? [])))) as $recurring_invoice_id) {
    $row = billingRow('recurring', $recurring_invoice_id);
    if ($row && empty($row['recurring_invoice_archived_at'])) {
        $rows[$recurring_invoice_id] = $row;
    }
}
if (!$rows) {
    exit(json_encode(['error' => 'Tick the recurring invoices first.']));
}
$one = count($rows) === 1 ? reset($rows) : null;
$what = $one ? escapeHtml(billingNumber('recurring', $one)) . ' &middot; ' . escapeHtml($one['client_name']) : count($rows) . ' recurring invoices';
$title = ['pause' => 'Pause', 'next_date' => 'Next Date', 'frequency' => 'Billed'][$action];
$icon = ['pause' => 'fa-pause', 'next_date' => 'fa-calendar-alt', 'frequency' => 'fa-sync-alt'][$action];
$pause_ends = recurringPauseReady();

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw <?= $icon ?> me-2"></i><?= $title ?> <span class="fw-normal text-white-50 ms-1"><?= $what ?></span></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="bulk_action" value="<?= $action ?>">
    <?php foreach (array_keys($rows) as $recurring_invoice_id) { ?>
        <input type="hidden" name="recurring_invoice_ids[]" value="<?= $recurring_invoice_id ?>">
    <?php } ?>

    <div class="modal-body">
        <?php if ($action === 'pause') { ?>
            <p class="text-body-secondary small">No invoices are made from <?= $one ? 'it' : 'them' ?> while paused.</p>
            <?php if ($pause_ends) { ?>
                <label class="form-label">Until</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-fw fa-calendar-check"></i></span>
                    <input type="date" class="form-control" name="paused_until" min="<?= date('Y-m-d', strtotime('+1 day')) ?>" max="2999-12-31">
                </div>
                <div class="form-text">
                    On that day <?= $one ? 'it runs' : 'they run' ?> again by <?= $one ? 'itself' : 'themselves' ?>, from <?= $one ? 'its' : 'their' ?> next date after it - nothing is billed for the runs in between.
                    Leave it empty to pause until resumed.
                </div>
            <?php } ?>
        <?php } elseif ($action === 'next_date') { ?>
            <label class="form-label">Next date <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw fa-calendar"></i></span>
                <input type="date" class="form-control" name="next_date" max="2999-12-31" required value="<?= $one ? escapeHtml($one['recurring_invoice_next_date']) : '' ?>">
            </div>
            <div class="form-text">The day the next invoice is made. The runs after it follow on from it, on the same day of the month.</div>
        <?php } else { ?>
            <label class="form-label">Billed <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw fa-sync-alt"></i></span>
                <select class="form-select select2" name="frequency" required>
                    <?php foreach (recurringInvoiceFrequencies() as $frequency => $label) { ?>
                        <option value="<?= $frequency ?>" <?= $one && $one['recurring_invoice_frequency'] === $frequency ? 'selected' : '' ?>><?= $label ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="form-text">The next dates stay as they are, and so do the lines - a monthly one made yearly bills the same amount once a year, so change its lines to match.</div>
        <?php } ?>

        <?php if (!$one) { ?>
            <div class="small text-body-secondary mt-3">
                <?= escapeHtml(implode(', ', array_map(function ($row) { return billingNumber('recurring', $row) . ' (' . $row['client_name'] . ')'; }, array_slice($rows, 0, 8)))) ?><?= count($rows) > 8 ? ' and ' . (count($rows) - 8) . ' more' : '' ?>
            </div>
        <?php } ?>
    </div>
    <div class="modal-footer">
        <button type="submit" name="bulk_recurring_invoices" class="btn btn-primary fw-bold"><i class="fas fa-check me-2"></i><?= $action === 'pause' ? 'Pause' : 'Save' ?></button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
