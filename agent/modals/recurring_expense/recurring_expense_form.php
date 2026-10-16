<?php

/*
 * The fields of a recurring expense - shared by New Recurring Expense and
 * Edit. Set before including:
 *
 *   $form_row        recurringExpenseRow() when editing, null for a new one
 *   $form_vendor_id  the vendor a new one starts with (0 to pick)
 *   $form_client_id  the client a new one starts with (0 for none)
 *
 * Posts vendor, amount, frequency, next_date, end_date, category, account,
 * payment_method, client_id, reference and description. The schedule reads
 * back in words as it is set; picking a vendor on a new one fills in what
 * the last expense with them used, unless that was changed by hand.
 *
 * Should not be accessed directly.
 */

isset($mysqli) || die("Direct file access is not allowed");

$form_edit = !empty($form_row);
$form_id = 'recurringExpenseForm' . ($form_edit ? intval($form_row['recurring_expense_id']) : 'New');
$form_value = function ($column, $default = '') use ($form_row) {
    return $form_row ? (string) ($form_row[$column] ?? $default) : $default;
};

$form_defaults = expenseVendorDefaults();
$form_vendor = $form_edit ? intval($form_row['recurring_expense_vendor_id']) : intval($form_vendor_id ?? 0);
$form_category = intval($form_value('recurring_expense_category_id', '0'));
$form_account = $form_edit ? intval($form_row['recurring_expense_account_id']) : intval($config_default_expense_account);
$form_method = $form_edit ? $form_value('recurring_expense_payment_method') : (string) $config_default_expense_payment_method;
if (!$form_edit && $form_vendor && isset($form_defaults[$form_vendor])) {
    $form_category = $form_defaults[$form_vendor]['category'];
    $form_account = $form_defaults[$form_vendor]['account'] ?: $form_account;
    $form_method = $form_defaults[$form_vendor]['method'] !== '' ? $form_defaults[$form_vendor]['method'] : $form_method;
}
$form_client = $form_edit ? intval($form_row['recurring_expense_client_id']) : intval($form_client_id ?? 0);
$form_frequency = $form_value('recurring_expense_frequency', 'month');
$form_next = $form_edit ? $form_value('recurring_expense_next_date') : date('Y-m-d', strtotime('first day of next month'));
$form_end = $form_value('recurring_expense_end_date');
$form_currency = $form_edit ? $form_value('recurring_expense_currency_code') : (string) $session_company_currency;

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

?>
<div class="row g-3" id="<?= $form_id ?>" data-recurring-expense-form>

    <div class="col-md-7">
        <label class="form-label">Vendor <strong class="text-danger">*</strong></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-building"></i></span>
            <select class="form-select select2" name="vendor" required data-expense-vendor>
                <option value="">- Who it is paid to -</option>
                <?php foreach ($form_vendors as $vendor_id => $vendor) { ?>
                    <option value="<?= $vendor_id ?>" <?= $form_vendor === $vendor_id ? 'selected' : '' ?><?= selectOptionVendor($vendor['vendor_name'], $vendor['vendor_archived_at'] ? 'Archived' : (string) $vendor['vendor_description']) ?>><?= escapeHtml($vendor['vendor_name']) ?></option>
                <?php } ?>
            </select>
            <a class="btn btn-outline-secondary" title="Add a vendor - opens Vendors in a new tab" href="vendors.php" target="_blank"><i class="fas fa-fw fa-plus"></i></a>
        </div>
        <div class="form-text" data-expense-filled hidden>Filled in from the last expense with them.</div>
    </div>

    <div class="col-md-5">
        <label class="form-label">Amount each time <strong class="text-danger">*</strong></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-dollar-sign"></i></span>
            <input type="text" class="form-control text-end fw-semibold" inputmode="decimal" pattern="[0-9,]*\.?[0-9]{0,2}" name="amount" placeholder="0.00" required
                value="<?= $form_edit ? number_format(floatval($form_row['recurring_expense_amount']), 2, '.', '') : '' ?>" data-recurring-amount>
            <span class="input-group-text"><?= escapeHtml($form_currency) ?></span>
        </div>
    </div>

    <div class="col-md-4">
        <label class="form-label">How often <strong class="text-danger">*</strong></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-sync-alt"></i></span>
            <select class="form-select" name="frequency" required data-recurring-frequency>
                <?php foreach (recurringInvoiceFrequencies() as $key => $label) { ?>
                    <option value="<?= $key ?>" <?= $form_frequency === $key ? 'selected' : '' ?>><?= $label ?></option>
                <?php } ?>
            </select>
        </div>
    </div>

    <div class="col-md-4">
        <label class="form-label"><?= $form_edit ? 'Next one on' : 'First one on' ?> <strong class="text-danger">*</strong></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-calendar-day"></i></span>
            <input type="date" class="form-control" name="next_date" max="2999-12-31" value="<?= escapeHtml($form_next) ?>" required data-recurring-next>
        </div>
    </div>

    <div class="col-md-4">
        <label class="form-label">Ends</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-flag-checkered"></i></span>
            <input type="date" class="form-control" name="end_date" max="2999-12-31" value="<?= escapeHtml($form_end) ?>" data-recurring-end>
        </div>
    </div>

    <div class="col-12">
        <div class="small text-body-secondary"><i class="fas fa-fw fa-redo-alt me-1"></i><span data-recurring-summary><?= $form_edit ? escapeHtml(recurringExpenseScheduleText($form_row)) : '' ?></span>. Leave Ends empty unless it stops - a lease or financing that is paid off on a date.</div>
    </div>

    <div class="col-md-7">
        <label class="form-label">Category <strong class="text-danger">*</strong></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-list"></i></span>
            <select class="form-select select2" name="category" required data-expense-category>
                <option value="">- What it is for -</option>
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
                <option value="">- How it is paid -</option>
                <?php foreach ($form_methods as $method) { ?>
                    <option value="<?= escapeHtml($method) ?>" <?= $form_method === $method ? 'selected' : '' ?>><?= escapeHtml($method) ?></option>
                <?php } ?>
            </select>
        </div>
    </div>

    <div class="col-12">
        <label class="form-label">Reference</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-hashtag"></i></span>
            <input type="text" class="form-control" name="reference" maxlength="255" placeholder="Account or contract number - it goes on each expense" value="<?= escapeHtml($form_value('recurring_expense_reference')) ?>">
        </div>
    </div>

    <div class="col-12">
        <label class="form-label">Description</label>
        <div class="input-group">
            <span class="input-group-text align-items-start pt-2"><i class="fa fa-fw fa-align-left"></i></span>
            <textarea class="form-control" rows="2" name="description" placeholder="What it is - office rent, colocation, M365 partner licensing"><?= escapeHtml($form_value('recurring_expense_description')) ?></textarea>
        </div>
    </div>

</div>

<script>
(function () {
    var root = document.getElementById(<?= json_encode($form_id) ?>);
    if (!root || root.dataset.wired) {
        return;
    }
    root.dataset.wired = '1';

    // The schedule in words - the same as recurringExpenseScheduleText()
    var frequency = root.querySelector('[data-recurring-frequency]');
    var next = root.querySelector('[data-recurring-next]');
    var end = root.querySelector('[data-recurring-end]');
    var summary = root.querySelector('[data-recurring-summary]');
    var months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    function parse(value) {
        var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value || '');
        return m ? { y: +m[1], m: +m[2], d: +m[3] } : null;
    }
    function ordinal(n) {
        var s = (n % 100 >= 11 && n % 100 <= 13) ? 'th' : (['th', 'st', 'nd', 'rd'][n % 10] || 'th');
        return n + s;
    }
    function update() {
        var d = parse(next.value);
        if (!d) {
            summary.textContent = 'Set the date of the ' + <?= json_encode($form_edit ? 'next' : 'first') ?> + ' one';
            return;
        }
        var text = frequency.value === 'year' ? 'Every year on ' + months[d.m - 1] + ' ' + d.d
            : 'Every ' + (frequency.value === 'quarter' ? 'quarter' : 'month') + ' on the ' + ordinal(d.d);
        text += ', ' + <?= json_encode($form_edit ? 'next on ' : 'starting ') ?> + months[d.m - 1] + ' ' + d.d + ', ' + d.y;
        var e = parse(end.value);
        if (e) {
            text += (end.value < next.value ? ' - but that is after it ends on ' : ', until ') + months[e.m - 1] + ' ' + e.d + ', ' + e.y;
        }
        summary.textContent = text;
    }
    [frequency, next, end].forEach(function (el) {
        el.addEventListener('change', update);
        el.addEventListener('input', update);
    });
    update();

    <?php if (!$form_edit) { ?>
    // Picking a vendor fills in what the last expense with them used, unless changed by hand
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
    <?php } ?>
})();
</script>
