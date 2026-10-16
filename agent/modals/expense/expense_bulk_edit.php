<?php

/*
 * Set one thing on the ticked expenses - ?field= category, account, client
 * or method. Posts bulk_edit_expenses with field, value and expense_ids[].
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
                    <?php foreach (expenseCategories() as $category_id => $category) { ?>
                        <option value="<?= $category_id ?>"<?= selectOptionColor($category['category_color'] ?? '', $category['category_icon'] ?? '') ?>><?= escapeHtml($category['category_name']) ?></option>
                    <?php } ?>
                </select>
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
            <?php } else { ?>
                <select class="form-select select2" name="value">
                    <option value="">- None -</option>
                    <?php foreach (paymentMethods() as $method) { ?>
                        <option value="<?= escapeHtml($method) ?>"><?= escapeHtml($method) ?></option>
                    <?php } ?>
                </select>
            <?php } ?>
        </div>
    </div>
    <div class="modal-footer">
        <button type="submit" name="bulk_edit_expenses" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Set on <?= $count ?></button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
