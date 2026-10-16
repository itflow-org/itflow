<?php

/*
 * The fields of an expense - shared by New Expense, Edit, Copy and Refund.
 * Set before including:
 *
 *   $form_mode       new | edit | copy | refund
 *   $form_row        expenseRow() for edit, copy and refund; null for new
 *   $form_vendor_id  the vendor a new one starts with (0 to pick)
 *   $form_client_id  the client a new one starts with (0 for none)
 *
 * Posts date, amount, vendor, category, account, payment_method, client_id,
 * reference, description, the receipt as `file`, and refund_of (refund) or
 * receipt_remove (edit). Picking a vendor on a new expense fills in the
 * category, account and payment method of the last expense with them,
 * unless those were changed by hand.
 *
 * Should not be accessed directly.
 */

isset($mysqli) || die("Direct file access is not allowed");

$form_edit = $form_mode === 'edit';
$form_refund = $form_mode === 'refund';
$form_id = 'expenseForm' . ucfirst($form_mode) . ($form_row ? intval($form_row['expense_id']) : '');
$form_value = function ($column, $default = '') use ($form_row) {
    return $form_row ? (string) ($form_row[$column] ?? $default) : $default;
};

$form_defaults = expenseVendorDefaults();
$form_vendor = $form_row ? intval($form_row['expense_vendor_id']) : intval($form_vendor_id ?? 0);
$form_category = intval($form_value('expense_category_id', '0'));
$form_account = $form_row ? intval($form_row['expense_account_id']) : intval($config_default_expense_account);
$form_method = $form_row ? $form_value('expense_payment_method') : (string) $config_default_expense_payment_method;
if (!$form_row && $form_vendor && isset($form_defaults[$form_vendor])) {
    $form_category = $form_defaults[$form_vendor]['category'];
    $form_account = $form_defaults[$form_vendor]['account'] ?: $form_account;
    $form_method = $form_defaults[$form_vendor]['method'] !== '' ? $form_defaults[$form_vendor]['method'] : $form_method;
}
$form_client = $form_row ? intval($form_row['expense_client_id']) : intval($form_client_id ?? 0);

// What goes in each box to start
$form_date = $form_edit ? $form_value('expense_date') : date('Y-m-d');
if ($form_refund) {
    $form_amount = max(0, floatval($form_row['expense_amount']) - floatval($form_row['refunded_amount']));
} else {
    $form_amount = $form_row ? floatval($form_row['expense_amount']) : null;
}
$form_reference = $form_edit ? $form_value('expense_reference') : '';
$form_description = $form_value('expense_description');
if ($form_refund) {
    $form_description = 'Refund' . ($form_description !== '' ? ": $form_description" : '');
}
$form_currency = $form_row ? $form_value('expense_currency_code') : (string) $session_company_currency;

$form_vendors = expenseVendors($form_edit ? $form_vendor : 0);
$form_categories = expenseCategories($form_edit ? $form_category : 0);
$form_accounts = paymentAccounts($form_edit ? $form_account : 0);
$form_methods = paymentMethods();
if ($form_method !== '' && !in_array($form_method, $form_methods, true)) {
    $form_methods[] = $form_method;
}
$form_clients = [];
$sql = mysqli_query($mysqli, "SELECT client_id, client_name, client_abbreviation FROM clients
    WHERE (client_archived_at IS NULL OR client_id = $form_client) " . clientScopeSql('clients.client_id') . " ORDER BY client_name ASC");
while ($row = mysqli_fetch_assoc($sql)) {
    $form_clients[] = $row;
}
$form_receipt = $form_edit ? $form_value('expense_receipt') : '';

?>
<div class="row g-3" id="<?= $form_id ?>" data-expense-form="<?= $form_mode ?>">

    <?php if ($form_refund) { ?>
        <input type="hidden" name="refund_of" value="<?= intval($form_row['expense_id']) ?>">
        <div class="col-12">
            <div class="d-flex align-items-center gap-3 p-3 rounded bg-body-tertiary">
                <?= vendorTile($form_row['vendor_name'] ?? '') ?>
                <div class="flex-grow-1 min-w-0">
                    <div class="fw-semibold text-truncate"><?= escapeHtml($form_row['vendor_name'] ?? '') ?></div>
                    <div class="small text-body-secondary text-truncate"><?= escapeHtml(implode(' · ', array_filter([$form_row['expense_date'], $form_row['category_name'], truncate((string) $form_row['expense_description'], 60)]))) ?></div>
                </div>
                <div class="text-end">
                    <div class="fw-semibold itflow-money"><?= escapeHtml(billingMoney($form_row['expense_amount'], $form_currency)) ?></div>
                    <?php if (floatval($form_row['refunded_amount']) > 0) { ?>
                        <div class="small text-success-emphasis"><?= escapeHtml(billingMoney($form_row['refunded_amount'], $form_currency)) ?> back already</div>
                    <?php } ?>
                </div>
            </div>
        </div>
    <?php } ?>

    <div class="col-md-7">
        <label class="form-label">Vendor <strong class="text-danger">*</strong></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-building"></i></span>
            <select class="form-select select2" name="vendor" required data-expense-vendor <?= $form_refund ? 'disabled' : '' ?>>
                <option value="">- Who it was paid to -</option>
                <?php foreach ($form_vendors as $vendor_id => $vendor) { ?>
                    <option value="<?= $vendor_id ?>" <?= $form_vendor === $vendor_id ? 'selected' : '' ?><?= selectOptionVendor($vendor['vendor_name'], $vendor['vendor_archived_at'] ? 'Archived' : (string) $vendor['vendor_description']) ?>><?= escapeHtml($vendor['vendor_name']) ?></option>
                <?php } ?>
            </select>
            <?php if ($form_refund) { ?>
                <input type="hidden" name="vendor" value="<?= $form_vendor ?>">
            <?php } else { ?>
                <a class="btn btn-outline-secondary" title="Add a vendor - opens Vendors in a new tab" href="vendors.php" target="_blank"><i class="fas fa-fw fa-plus"></i></a>
            <?php } ?>
        </div>
        <div class="form-text" data-expense-filled hidden>Filled in from the last expense with them.</div>
    </div>

    <div class="col-md-5">
        <label class="form-label"><?= $form_refund ? 'Amount back' : 'Amount' ?> <strong class="text-danger">*</strong></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-dollar-sign"></i></span>
            <input type="text" class="form-control text-end fw-semibold" inputmode="decimal" pattern="-?[0-9,]*\.?[0-9]{0,2}" name="amount" placeholder="0.00" required
                value="<?= $form_amount === null ? '' : number_format($form_amount, 2, '.', '') ?>" <?= $form_mode === 'new' ? 'autofocus' : '' ?>>
            <span class="input-group-text"><?= escapeHtml($form_currency) ?></span>
        </div>
        <?php if ($form_refund) { ?>
            <div class="form-text">Saved as money back - a negative expense.</div>
        <?php } elseif ($form_edit && floatval($form_row['expense_amount']) < 0) { ?>
            <div class="form-text">Negative - money back from the vendor.</div>
        <?php } ?>
    </div>

    <div class="col-md-7">
        <label class="form-label">Category <strong class="text-danger">*</strong></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-list"></i></span>
            <select class="form-select select2" name="category" required data-expense-category>
                <option value="">- What it was for -</option>
                <?php foreach ($form_categories as $category_id => $category) { ?>
                    <option value="<?= $category_id ?>" <?= $form_category === $category_id ? 'selected' : '' ?><?= selectOptionColor($category['category_color'] ?? '', $category['category_icon'] ?? '') ?>><?= escapeHtml($category['category_name']) ?><?= $category['category_archived_at'] ? ' (archived)' : '' ?></option>
                <?php } ?>
            </select>
            <?php if ($session_is_admin) { ?>
                <button class="btn btn-outline-secondary ajax-modal" title="New category" type="button" data-modal-url="../admin/modals/category/category_add.php?category=Expense"><i class="fas fa-fw fa-plus"></i></button>
            <?php } ?>
        </div>
    </div>

    <div class="col-md-5">
        <label class="form-label">Date <strong class="text-danger">*</strong></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
            <input type="date" class="form-control" name="date" max="2999-12-31" value="<?= escapeHtml($form_date) ?>" required>
        </div>
    </div>

    <div class="col-md-7">
        <label class="form-label">Paid from <strong class="text-danger">*</strong></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-piggy-bank"></i></span>
            <select class="form-select select2" name="account" required data-expense-account>
                <option value="">- Which account -</option>
                <?php foreach ($form_accounts as $account_id => $account) { ?>
                    <option value="<?= $account_id ?>" <?= $form_account === $account_id ? 'selected' : '' ?> data-icon="fas fa-piggy-bank"
                        data-description="<?= escapeHtml(billingMoney($account['balance'], $account['account_currency_code'])) ?><?= $account['account_archived_at'] ? ' - archived' : '' ?>"><?= escapeHtml($account['account_name']) ?></option>
                <?php } ?>
            </select>
        </div>
    </div>

    <div class="col-md-5">
        <label class="form-label">Paid by</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-credit-card"></i></span>
            <select class="form-select select2" name="payment_method" data-expense-method>
                <option value="">- How it was paid -</option>
                <?php foreach ($form_methods as $method) { ?>
                    <option value="<?= escapeHtml($method) ?>" <?= $form_method === $method ? 'selected' : '' ?>><?= escapeHtml($method) ?></option>
                <?php } ?>
            </select>
        </div>
    </div>

    <div class="col-md-7">
        <label class="form-label">Client</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
            <select class="form-select select2" name="client_id">
                <option value="0">- Not for a client -</option>
                <?php foreach ($form_clients as $client) { ?>
                    <option value="<?= intval($client['client_id']) ?>" <?= $form_client === intval($client['client_id']) ? 'selected' : '' ?><?= selectOptionClient($client['client_id'], $client['client_name'], $client['client_abbreviation'] ?? '') ?>><?= escapeHtml($client['client_name']) ?></option>
                <?php } ?>
            </select>
        </div>
        <div class="form-text">A direct cost of theirs - it counts against them in Client Profitability.</div>
    </div>

    <div class="col-md-5">
        <label class="form-label">Reference</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-hashtag"></i></span>
            <input type="text" class="form-control" name="reference" maxlength="200" placeholder="Their invoice or order number" value="<?= escapeHtml($form_reference) ?>">
        </div>
    </div>

    <div class="col-12">
        <label class="form-label">Description</label>
        <div class="input-group">
            <span class="input-group-text align-items-start pt-2"><i class="fa fa-fw fa-align-left"></i></span>
            <textarea class="form-control" rows="3" name="description" placeholder="What it was - 24 port switch for Ravenwood, October M365 licensing"><?= escapeHtml($form_description) ?></textarea>
        </div>
    </div>

    <div class="col-12">
        <label class="form-label">Receipt</label>
        <?php if ($form_receipt) { ?>
            <div class="d-flex align-items-center gap-2 mb-2 p-2 rounded border" data-expense-receipt-current>
                <i class="fas fa-fw fa-<?= expenseReceiptKind($form_receipt) === 'pdf' ? 'file-pdf text-danger' : 'file-image text-primary' ?>"></i>
                <a class="text-truncate flex-grow-1" href="expense_receipt.php?expense_id=<?= intval($form_row['expense_id']) ?>&amp;action=view" target="_blank"><?= escapeHtml(expenseReceiptName($form_row)) ?></a>
                <div class="form-check form-switch mb-0 text-nowrap">
                    <input class="form-check-input" type="checkbox" role="switch" name="receipt_remove" value="1" id="<?= $form_id ?>Remove" data-expense-receipt-remove>
                    <label class="form-check-label small" for="<?= $form_id ?>Remove">Remove</label>
                </div>
            </div>
        <?php } ?>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-paperclip"></i></span>
            <input type="file" class="form-control" name="file" accept="image/*,application/pdf" data-expense-receipt-file>
        </div>
        <div class="form-text"><?= $form_receipt ? 'Choose a file to replace it. ' : '' ?>A photo or a PDF - on a phone, take the picture right here.</div>
    </div>

</div>

<?php if (in_array($form_mode, ['new', 'copy'], true)) { ?>
<script>
(function () {
    // Picking a vendor fills in what the last expense with them used, unless changed by hand
    var root = document.getElementById(<?= json_encode($form_id) ?>);
    if (!root || root.dataset.wired) {
        return;
    }
    root.dataset.wired = '1';
    var defaults = <?= json_encode((object) $form_defaults) ?>;
    var vendor = root.querySelector('[data-expense-vendor]');
    var note = root.querySelector('[data-expense-filled]');
    var fields = {
        category: root.querySelector('[data-expense-category]'),
        account: root.querySelector('[data-expense-account]'),
        method: root.querySelector('[data-expense-method]')
    };
    var touched = {};
    var filling = false;
    Object.keys(fields).forEach(function (key) {
        fields[key].addEventListener('change', function () {
            if (!filling) {
                touched[key] = true;
            }
        });
    });
    vendor.addEventListener('change', function () {
        var d = defaults[vendor.value];
        var filled = false;
        if (!d) {
            note.hidden = true;
            return;
        }
        filling = true;
        Object.keys(fields).forEach(function (key) {
            var value = String(d[key] || '');
            if (touched[key] || value === '' || value === '0' || !fields[key].querySelector('option[value="' + CSS.escape(value) + '"]')) {
                return;
            }
            if (fields[key].value !== value) {
                fields[key].value = value;
                fields[key].dispatchEvent(new Event('change', { bubbles: true }));
                filled = true;
            }
        });
        filling = false;
        note.hidden = !filled;
    });
})();
</script>
<?php } ?>
