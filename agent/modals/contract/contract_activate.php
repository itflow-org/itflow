<?php

/*
 * Make a draft contract active - from the day picked, with an end date from
 * its term when it has none.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_sales', 2);

$contract = contractRow(intval($_GET['id'] ?? 0));
if ($contract) {
    $client_id = intval($contract['contract_client_id']);
    enforceClientAccess();
}

ob_start();

if (!$contract || $contract['contract_status'] !== 'Draft') { ?>
    <div class="modal-body text-center text-secondary py-5"><?= $contract ? 'It is already in force.' : 'That contract is gone - it may have been deleted.' ?></div>
<?php } else {
    $start = $contract['contract_start_date'] ?: date('Y-m-d'); ?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-check me-2"></i>Make <strong><?= escapeHtml($contract['contract_name']) ?></strong> active</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="contract_id" value="<?= intval($contract['contract_id']) ?>">
    <div class="modal-body">
        <label class="form-label">In force from</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-calendar-day"></i></span>
            <input type="date" class="form-control" name="start_date" value="<?= escapeHtml($start) ?>" required>
        </div>
        <small class="text-secondary">
            <?= $contract['contract_end_date'] ? 'It ends on ' . escapeHtml(date('M j, Y', strtotime($contract['contract_end_date']))) . '.' : 'It runs for ' . escapeHtml(contractTermLabel($contract['contract_term_months'])) . ' from then.' ?>
            <?= intval($contract['contract_auto_renew']) ? 'Then it renews itself.' : '' ?>
        </small>
    </div>
    <div class="modal-footer">
        <button type="submit" name="activate_contract" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Make active</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php }
require_once '../../../includes/modal_footer.php';
