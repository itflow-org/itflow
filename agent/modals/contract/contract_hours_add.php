<?php

/*
 * Add prepaid hours to a contract - how many, what they cost, the day they
 * were bought and the day they run out (if they do), a note, and whether to
 * make a draft invoice for them. Time done between those days is drawn from
 * them (functions/contract_hours.php).
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_sales', 2);

$contract = contractRow(intval($_GET['id'] ?? 0));
if ($contract) {
    $client_id = intval($contract['contract_client_id']);
    enforceClientAccess();
}

ob_start();

if (!$contract || !contractHoursReady() || in_array($contract['contract_status'], ['Ended', 'Cancelled'], true)) { ?>
    <div class="modal-body text-center text-secondary py-5">
        <?= !$contract ? 'That contract is gone - it may have been deleted.' : (!contractHoursReady() ? 'Prepaid hours need the database update - run it from Maintenance > Update first.' : "Hours are added to a draft or active contract - renew it first.") ?>
    </div>
<?php } else {
    $can_invoice = intval($config_module_enable_accounting) === 1;
    $currency = (string) $session_company_currency; ?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-hourglass-half me-2"></i>Add hours to <strong><?= escapeHtml($contract['contract_name']) ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="contract_id" value="<?= intval($contract['contract_id']) ?>">
    <div class="modal-body">
        <div class="row g-3 mb-3">
            <div class="col-sm-6">
                <label class="form-label">Hours <strong class="text-danger">*</strong></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-hourglass-half"></i></span>
                    <input type="text" inputmode="decimal" class="form-control" name="hours" placeholder="20" required autofocus>
                    <span class="input-group-text">hours</span>
                </div>
            </div>
            <div class="col-sm-6">
                <label class="form-label">Price</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-dollar-sign"></i></span>
                    <input type="text" inputmode="decimal" class="form-control" name="price" placeholder="None" data-contract-hours-price>
                </div>
            </div>
        </div>
        <div class="row g-3 mb-1">
            <div class="col-sm-6">
                <label class="form-label">Bought <strong class="text-danger">*</strong></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-calendar-plus"></i></span>
                    <input type="date" class="form-control" name="bought" max="2999-12-31" value="<?= date('Y-m-d') ?>" required>
                </div>
            </div>
            <div class="col-sm-6">
                <label class="form-label">Run out</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-calendar-times"></i></span>
                    <input type="date" class="form-control" name="expires" max="2999-12-31" value="<?= escapeHtml((string) ($contract['contract_end_date'] ?? '')) ?>">
                </div>
            </div>
        </div>
        <small class="text-secondary d-block mb-3">Tickets done from the day they were bought to the day they run out draw from them. Leave the last day empty if they don't run out.</small>
        <div class="mb-3">
            <label class="form-label">Note</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-sticky-note"></i></span>
                <input type="text" class="form-control" name="note" maxlength="255" placeholder="Block of 20 hours, PO 4512">
            </div>
        </div>
        <?php if ($can_invoice) { ?>
            <div class="form-check form-switch mb-2">
                <input class="form-check-input" type="checkbox" role="switch" name="invoice" value="1" id="contractHoursInvoice">
                <label class="form-check-label" for="contractHoursInvoice">Make a draft invoice for them, at the price</label>
            </div>
            <div class="input-group mb-1">
                <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                <select class="form-select" name="category" aria-label="Invoice category">
                    <option value="0">- Invoice category -</option>
                    <?= categoryOptionsHtml('Income') ?>
                </select>
            </div>
            <small class="text-secondary">One line, at the client's usual tax rate for time<?= $currency !== '' ? ', in ' . escapeHtml($currency) : '' ?>. Send it from the invoice when it is ready.</small>
        <?php } ?>
    </div>
    <div class="modal-footer">
        <button type="submit" name="add_contract_hours" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Add hours</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php }
require_once '../../../includes/modal_footer.php';
