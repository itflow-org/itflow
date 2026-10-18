<?php

/*
 * Set one thing on the ticked expenses - ?field= category, account, client,
 * method or billable (bill them on to their clients, with a markup, or
 * not). Posts bulk_edit_expenses with field, value (and markup) and
 * expense_ids[].
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_financial', 2);

$expense_ids = array_values(array_unique(array_filter(array_map('intval', (array) ($_GET['expense_ids'] ?? [])))));
$count = count($expense_ids);
$field = (string) ($_GET['field'] ?? '');

$fields = [
    'category' => ['label' => 'Category', 'icon' => 'fa-list'],
    'account' => ['label' => 'Account', 'icon' => 'fa-piggy-bank'],
    'client' => ['label' => 'Client', 'icon' => 'fa-user'],
    'method' => ['label' => 'Payment Method', 'icon' => 'fa-credit-card'],
    'billable' => ['label' => 'Billable', 'icon' => 'fa-file-invoice-dollar'],
];
if (!isset($fields[$field])) {
    echo json_encode(['error' => 'Pick what to set from the Bulk Action menu.']);
    exit;
}
if (!$count) {
    echo json_encode(['error' => 'Tick the expenses to change first.']);
    exit;
}

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw <?= $fields[$field]['icon'] ?> me-2"></i>Set <?= $fields[$field]['label'] ?> <span class="fw-normal text-white-50 ms-1"><?= $count ?> expense<?= $count === 1 ? '' : 's' ?></span></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="field" value="<?= $field ?>">
    <?php foreach ($expense_ids as $expense_id) { ?>
        <input type="hidden" name="expense_ids[]" value="<?= $expense_id ?>">
    <?php } ?>
    <div class="modal-body">
        <label class="form-label"><?= $fields[$field]['label'] ?><?= in_array($field, ['category', 'account'], true) ? ' <strong class="text-danger">*</strong>' : '' ?></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw <?= $fields[$field]['icon'] ?>"></i></span>
            <?php if ($field === 'category') { ?>
                <select class="form-select select2" name="value" required>
                    <option value="">- Pick a category -</option>
                    <?= categoryOptionsHtml('Expense') ?>
                </select>
                <?= categoryAddButtonHtml('Expense', 'btn btn-secondary') ?>
            <?php } elseif ($field === 'account') { ?>
                <select class="form-select select2" name="value" required>
                    <option value="">- Pick an account -</option>
                    <?php foreach (paymentAccounts() as $account_id => $account) { ?>
                        <option value="<?= $account_id ?>" data-icon="fas fa-piggy-bank" data-description="<?= escapeHtml(billingMoney($account['balance'], $account['account_currency_code'])) ?>"><?= escapeHtml($account['account_name']) ?></option>
                    <?php } ?>
                </select>
            <?php } elseif ($field === 'client') { ?>
                <select class="form-select select2" name="value">
                    <option value="0">- Not for a client -</option>
                    <?php
                    $sql = mysqli_query($mysqli, "SELECT client_id, client_name, client_abbreviation FROM clients WHERE client_archived_at IS NULL " . clientScopeSql('clients.client_id') . " ORDER BY client_name ASC");
                    while ($row = mysqli_fetch_assoc($sql)) { ?>
                        <option value="<?= intval($row['client_id']) ?>"<?= selectOptionClient($row['client_id'], $row['client_name'], $row['client_abbreviation'] ?? '') ?>><?= escapeHtml($row['client_name']) ?></option>
                    <?php } ?>
                </select>
            <?php } elseif ($field === 'billable') { ?>
                <select class="form-select" name="value" data-bulk-billable>
                    <option value="yes">Bill them on to their clients</option>
                    <option value="no">Our cost - not billed on</option>
                </select>
            <?php } else { ?>
                <select class="form-select select2" name="value">
                    <option value="">- None -</option>
                    <?php foreach (paymentMethods() as $method) { ?>
                        <option value="<?= escapeHtml($method) ?>"><?= escapeHtml($method) ?></option>
                    <?php } ?>
                </select>
            <?php } ?>
        </div>
        <?php if ($field === 'billable') { ?>
            <div class="mt-3" data-bulk-markup>
                <label class="form-label">Markup</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-percent"></i></span>
                    <input type="text" class="form-control" name="markup" inputmode="decimal" value="<?= rtrim(rtrim(number_format(expenseMarkupDefault(), 2, '.', ''), '0'), '.') ?>" aria-label="Markup">
                </div>
            </div>
            <div class="form-text">Only those for a client are billed on, and not ones already on an invoice - those stay as they are.</div>
            <script>
                (function () {
                    var value = document.querySelector('.modal.show [data-bulk-billable], [data-bulk-billable]');
                    var markup = value && value.closest('form').querySelector('[data-bulk-markup]');
                    if (!value || !markup || value.dataset.wired) {
                        return;
                    }
                    value.dataset.wired = '1';
                    value.addEventListener('change', function () {
                        markup.hidden = value.value !== 'yes';
                    });
                })();
            </script>
        <?php } ?>
    </div>
    <div class="modal-footer">
        <button type="submit" name="bulk_edit_expenses" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Set on <?= $count ?></button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
