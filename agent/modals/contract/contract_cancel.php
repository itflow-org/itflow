<?php

/*
 * Cancel a contract - its last day (it stays in force until then, and won't
 * renew) and why. It is kept, under Ended.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_sales', 2);

$contract = contractRow(intval($_GET['id'] ?? 0));
if ($contract) {
    $client_id = intval($contract['contract_client_id']);
    enforceClientAccess();
}

ob_start();

if (!$contract || $contract['contract_status'] !== 'Active') { ?>
    <div class="modal-body text-center text-secondary py-5"><?= $contract ? 'Only an active contract can be cancelled.' : 'That contract is gone - it may have been deleted.' ?></div>
<?php } else {
    $notice = contractNoticeDate($contract);
    $last_day = date('Y-m-d');
    if ($contract['contract_end_date'] && $notice !== '' && $notice >= date('Y-m-d')) {
        $last_day = (string) $contract['contract_end_date'];
    } ?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-ban me-2"></i>Cancel <strong><?= escapeHtml($contract['contract_name']) ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="contract_id" value="<?= intval($contract['contract_id']) ?>">
    <div class="modal-body">
        <?php if ($notice !== '') { ?>
            <p class="small <?= $notice >= date('Y-m-d') ? 'text-secondary' : 'text-warning-emphasis' ?>">
                <i class="fas fa-fw fa-bell me-1"></i><?= intval($contract['contract_notice_days']) ?> days notice - <?= $notice >= date('Y-m-d') ? 'given by ' . escapeHtml(date('M j, Y', strtotime($notice))) . ', it ends on ' . escapeHtml(date('M j, Y', strtotime($contract['contract_end_date']))) . '.' : 'the notice date, ' . escapeHtml(date('M j, Y', strtotime($notice))) . ', has passed.' ?>
            </p>
        <?php } ?>
        <div class="mb-3">
            <label class="form-label">Last day in force</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-calendar-times"></i></span>
                <input type="date" class="form-control" name="last_day" value="<?= escapeHtml($last_day) ?>" required>
            </div>
        </div>
        <div class="mb-1">
            <label class="form-label">Why</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-comment"></i></span>
                <input type="text" class="form-control" name="reason" maxlength="255" placeholder="Moving to another provider, bought out, closing down">
            </div>
        </div>
    </div>
    <div class="modal-footer">
        <button type="submit" name="cancel_contract" class="btn btn-danger text-bold"><i class="fas fa-ban me-2"></i>Cancel contract</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Keep it</button>
    </div>
</form>

<?php }
require_once '../../../includes/modal_footer.php';
