<?php

/*
 * Set the category, account, payment method or client of the ticked Income
 * rows - ?field= category, account, method or client, with income_ids[] as
 * payment-12 / revenue-7. A payment's category is its invoice's, so the
 * invoice takes it; its client is the invoice's, so Set Client changes only
 * the other income. Changing a payment takes the invoice's own rights.
 * Posts bulk_edit_income with field, value and income_ids[].
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_financial', 2);

$ids = incomeBulkIds($_GET['income_ids'] ?? []);
$payments = count($ids['payment']);
$others = count($ids['revenue']);
$count = $payments + $others;
$field = (string) ($_GET['field'] ?? '');
$can_payments = incomeCanEditPayments();

$fields = [
    'category' => ['label' => 'Category', 'icon' => 'fa-tag'],
    'account' => ['label' => 'Account', 'icon' => 'fa-piggy-bank'],
    'method' => ['label' => 'Payment Method', 'icon' => 'fa-credit-card'],
    'client' => ['label' => 'Client', 'icon' => 'fa-building'],
];
if (!isset($fields[$field])) {
    echo json_encode(['error' => 'Pick what to set from the Bulk Action menu.']);
    exit;
}
if (!$count) {
    echo json_encode(['error' => 'Tick the income to change first.']);
    exit;
}

// What the ticked rows are, in words - and what of it this leaves alone
$ticked = implode(' and ', array_filter([
    $payments ? $payments . ' invoice payment' . ($payments === 1 ? '' : 's') : '',
    $others ? $others . ' other income' : '',
]));
$notes = [];
if ($payments && $field === 'client') {
    $notes[] = "A payment's client is its invoice's - the " . ($payments === 1 ? 'payment stays' : "$payments payments stay") . ' as ' . ($payments === 1 ? 'it is' : 'they are') . '.';
} elseif ($payments && !$can_payments) {
    $notes[] = 'Changing a payment takes full access to Sales and Financial - the ' . ($payments === 1 ? 'payment stays' : "$payments payments stay") . ' as ' . ($payments === 1 ? 'it is' : 'they are') . '.';
} elseif ($payments && $field === 'category') {
    $notes[] = "A payment's category is its invoice's - the invoice" . ($payments === 1 ? '' : 's') . ' behind the ' . ($payments === 1 ? 'payment takes' : 'payments take') . ' it, and with it every payment on ' . ($payments === 1 ? 'it' : 'them') . '.';
}
if ($field === 'account') {
    $notes[] = 'The currency stays as it was entered.';
}
$changes = $field === 'client' || !$can_payments ? $others : $count;

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw <?= $fields[$field]['icon'] ?> me-2"></i>Set <?= $fields[$field]['label'] ?> <span class="fw-normal text-white-50 ms-1"><?= escapeHtml($ticked) ?></span></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="field" value="<?= $field ?>">
    <?php foreach (['payment', 'revenue'] as $type) {
        foreach ($ids[$type] as $id) { ?>
            <input type="hidden" name="income_ids[]" value="<?= "$type-$id" ?>">
        <?php }
    } ?>
    <div class="modal-body">
        <label class="form-label"><?= $fields[$field]['label'] ?><?= $field !== 'client' ? ' <strong class="text-danger">*</strong>' : '' ?></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw <?= $fields[$field]['icon'] ?>"></i></span>
            <?php if ($field === 'category') { ?>
                <select class="form-select select2" name="value" required>
                    <option value="">- Pick a category -</option>
                    <?= categoryOptionsHtml('Income', null, ['exclude' => ['Account Transfer']]) ?>
                </select>
                <?= categoryAddButtonHtml('Income', 'btn btn-secondary') ?>
            <?php } elseif ($field === 'account') { ?>
                <select class="form-select select2" name="value" required>
                    <option value="">- Pick the account it went into -</option>
                    <?php foreach (paymentAccounts() as $option_id => $account) { ?>
                        <option value="<?= $option_id ?>"><?= escapeHtml($account['account_name']) ?><?= $account['account_currency_code'] ? ' (' . escapeHtml($account['account_currency_code']) . ')' : '' ?></option>
                    <?php } ?>
                </select>
            <?php } elseif ($field === 'method') { ?>
                <select class="form-select select2" name="value" required>
                    <option value="">- Pick how it was paid -</option>
                    <?php foreach (paymentMethods() as $method) { ?>
                        <option><?= escapeHtml($method) ?></option>
                    <?php } ?>
                </select>
            <?php } else { ?>
                <select class="form-select select2" name="value">
                    <option value="0">- Not from a client -</option>
                    <?php
                    $sql = mysqli_query($mysqli, "SELECT client_id, client_name, client_abbreviation FROM clients WHERE client_archived_at IS NULL " . clientScopeSql('clients.client_id') . " ORDER BY client_name ASC");
                    while ($row = mysqli_fetch_assoc($sql)) { ?>
                        <option value="<?= intval($row['client_id']) ?>"<?= selectOptionClient($row['client_id'], $row['client_name'], $row['client_abbreviation'] ?? '') ?>><?= escapeHtml($row['client_name']) ?></option>
                    <?php } ?>
                </select>
            <?php } ?>
        </div>
        <?php foreach ($notes as $note) { ?>
            <div class="form-text"><?= escapeHtml($note) ?></div>
        <?php } ?>
    </div>
    <div class="modal-footer">
        <button type="submit" name="bulk_edit_income" class="btn btn-primary text-bold" <?= $changes ? '' : 'disabled' ?>><i class="fas fa-check me-2"></i>Set on <?= $changes ?></button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
