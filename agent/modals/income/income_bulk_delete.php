<?php

/*
 * Delete the other income among the ticked Income rows. A payment is taken
 * off from its invoice, so ticked payments are left alone. Posts
 * bulk_delete_income with income_ids[].
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_financial', 3);

$ids = incomeBulkIds($_GET['income_ids'] ?? []);
$count = count($ids['revenue']);
$payments = count($ids['payment']);
if (!$count) {
    echo json_encode(['error' => $payments ? "A payment is deleted from its invoice - tick other income to delete it here." : 'Tick the income to delete first.']);
    exit;
}
$total = [];
$sql = mysqli_query($mysqli, "SELECT revenue_currency_code, SUM(revenue_amount) FROM revenues WHERE revenue_id IN (" . implode(',', $ids['revenue']) . ") AND revenue_archived_at IS NULL
    AND " . incomeRevenueSql('revenue_id') . ' ' . clientScopeSql('revenue_client_id') . " GROUP BY revenue_currency_code");
while ($row = mysqli_fetch_row($sql)) {
    $total[] = billingMoney($row[1], $row[0]);
}

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-trash me-2"></i>Delete <?= $count ?> Other Income</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <?php foreach ($ids['revenue'] as $id) { ?>
        <input type="hidden" name="income_ids[]" value="revenue-<?= $id ?>">
    <?php } ?>
    <div class="modal-body">
        <div class="d-flex gap-3 align-items-start">
            <i class="fas fa-exclamation-triangle fa-2x text-danger"></i>
            <div>
                <div class="fw-semibold"><?= $count === 1 ? 'This income goes' : "These $count go" ?> for good<?= $total ? ' - ' . escapeHtml(implode(' and ', $total)) : '' ?>.</div>
                <div class="text-body-secondary small mt-1">It comes out of the account balances and the reports. This can't be undone.</div>
                <?php if ($payments) { ?>
                    <div class="text-body-secondary small mt-1">The <?= $payments === 1 ? 'payment' : "$payments payments" ?> ticked with <?= $count === 1 ? 'it' : 'them' ?> <?= $payments === 1 ? 'stays' : 'stay' ?> - a payment is deleted from its invoice.</div>
                <?php } ?>
            </div>
        </div>
    </div>
    <div class="modal-footer">
        <button type="submit" name="bulk_delete_income" class="btn btn-danger text-bold"><i class="fas fa-trash me-2"></i>Delete <?= $count ?></button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
