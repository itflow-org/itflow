<?php

/*
 * The fields of other income - money in that isn't for an invoice - shared
 * by New Income, Edit and Copy. Set before including:
 *
 *   $form_mode       new | edit | copy
 *   $form_row        incomeRevenueRow() for edit and copy; null for new
 *   $form_client_id  the client a new one starts with (0 for none)
 *
 * Posts date, amount, account, category, client_id, payment_method,
 * reference and description (agent/post/revenue.php). The currency is the
 * account's and follows the account picked; a copy is dated today.
 *
 * Should not be accessed directly.
 */

isset($mysqli) || die("Direct file access is not allowed");

$form_edit = $form_mode === 'edit';
$form_id = 'revenueForm' . ucfirst($form_mode) . ($form_row ? intval($form_row['revenue_id']) : '');
$form_value = function ($column, $default = '') use ($form_row) {
    return $form_row ? (string) ($form_row[$column] ?? $default) : $default;
};

$form_date = $form_edit ? $form_value('revenue_date') : date('Y-m-d');
$form_amount = $form_row ? floatval($form_row['revenue_amount']) : null;
$form_account = $form_row ? intval($form_row['revenue_account_id']) : (intval($form_account_id ?? 0) ?: intval($config_default_payment_account));
$form_category = intval($form_value('revenue_category_id', '0'));
$form_client = $form_row ? intval($form_row['revenue_client_id']) : intval($form_client_id ?? 0);
$form_method = $form_row ? $form_value('revenue_payment_method') : (string) ($config_default_payment_method ?? '');
$form_reference = $form_edit ? $form_value('revenue_reference') : '';
$form_description = $form_value('revenue_description');

$form_accounts = paymentAccounts($form_edit ? $form_account : 0);
// Cleared on a reconciled statement - its amount, date and account stay
$form_lock = $form_edit ? reconcileLock('revenue', $form_row['revenue_id']) : null;
if (!isset($form_accounts[$form_account])) {
    $form_account = 0;
}
$form_categories = incomeCategories($form_edit ? $form_category : 0);
$form_methods = paymentMethods();
if ($form_method !== '' && !in_array($form_method, $form_methods, true)) {
    if ($form_edit) {
        $form_methods[] = $form_method;
    } else {
        $form_method = '';
    }
}
$form_clients = [];
$sql = mysqli_query($mysqli, "SELECT client_id, client_name, client_abbreviation FROM clients
    WHERE (client_archived_at IS NULL OR client_id = $form_client) " . clientScopeSql('clients.client_id') . " ORDER BY client_name ASC");
while ($row = mysqli_fetch_assoc($sql)) {
    $form_clients[] = $row;
}
$form_currency = $form_account ? ($form_accounts[$form_account]['account_currency_code'] ?: $session_company_currency) : $session_company_currency;

?>
<div class="row g-3" id="<?= $form_id ?>">
    <?= reconcileFormNoteHtml($form_lock) ?>

    <div class="col-md-7">
        <label class="form-label">Category <strong class="text-danger">*</strong></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
            <select class="form-select select2" name="category" required>
                <option value="">- What it was -</option>
                <?php foreach ($form_categories as $category_id => $category) { ?>
                    <option value="<?= $category_id ?>" <?= $form_category === $category_id ? 'selected' : '' ?><?= selectOptionColor($category['category_color'] ?? '', $category['category_icon'] ?? '') ?>><?= escapeHtml($category['category_name']) ?><?= $category['category_archived_at'] ? ' (archived)' : '' ?></option>
                <?php } ?>
            </select>
            <?php if ($session_is_admin) { ?>
                <button class="btn btn-outline-secondary ajax-modal" title="New category" type="button" data-modal-url="../admin/modals/category/category_add.php?category=Income"><i class="fas fa-fw fa-plus"></i></button>
            <?php } ?>
        </div>
    </div>

    <div class="col-md-5">
        <label class="form-label">Amount <strong class="text-danger">*</strong></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-dollar-sign"></i></span>
            <input type="text" class="form-control text-end fw-semibold" inputmode="decimal" pattern="-?[0-9,]*\.?[0-9]{0,2}" name="amount" placeholder="0.00" required
                value="<?= $form_amount === null ? '' : number_format($form_amount, 2, '.', '') ?>" <?= $form_mode === 'new' ? 'autofocus' : '' ?> <?= $form_lock ? 'readonly' : '' ?>>
            <span class="input-group-text" data-revenue-currency><?= escapeHtml($form_currency) ?></span>
        </div>
        <?php if ($form_edit && $form_amount < 0) { ?>
            <div class="form-text">Negative - money that went back out.</div>
        <?php } ?>
    </div>

    <div class="col-md-7">
        <label class="form-label">Paid into <strong class="text-danger">*</strong></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-piggy-bank"></i></span>
            <select class="form-select select2" name="account" required data-revenue-account <?= $form_lock ? 'disabled' : '' ?>>
                <option value="">- Which account -</option>
                <?php foreach ($form_accounts as $account_id => $account) { ?>
                    <option value="<?= $account_id ?>" <?= $form_account === $account_id ? 'selected' : '' ?> data-icon="fas fa-piggy-bank" data-currency="<?= escapeHtml($account['account_currency_code'] ?: $session_company_currency) ?>"
                        data-description="<?= escapeHtml(billingMoney($account['balance'], $account['account_currency_code'])) ?><?= $account['account_archived_at'] ? ' - archived' : '' ?>"><?= escapeHtml($account['account_name']) ?></option>
                <?php } ?>
            </select>
            <?php if ($form_lock) { ?>
                <input type="hidden" name="account" value="<?= $form_account ?>">
            <?php } ?>
        </div>
    </div>

    <div class="col-md-5">
        <label class="form-label">Date <strong class="text-danger">*</strong></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
            <input type="date" class="form-control" name="date" max="2999-12-31" value="<?= escapeHtml($form_date) ?>" required <?= $form_lock ? 'readonly' : '' ?>>
        </div>
    </div>

    <div class="col-12">
        <label class="form-label">From client</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
            <select class="form-select select2" name="client_id">
                <option value="0">- Not from a client -</option>
                <?php foreach ($form_clients as $client) { ?>
                    <option value="<?= intval($client['client_id']) ?>" <?= $form_client === intval($client['client_id']) ? 'selected' : '' ?><?= selectOptionClient($client['client_id'], $client['client_name'], $client['client_abbreviation'] ?? '') ?>><?= escapeHtml($client['client_name']) ?></option>
                <?php } ?>
            </select>
        </div>
        <div class="form-text">Money of theirs that isn't for an invoice - it counts for them in Income by Client and Client Profitability.</div>
    </div>

    <div class="col-md-5">
        <label class="form-label">Paid by</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-credit-card"></i></span>
            <select class="form-select select2" name="payment_method">
                <option value="">- How it was paid -</option>
                <?php foreach ($form_methods as $method) { ?>
                    <option value="<?= escapeHtml($method) ?>" <?= $form_method === $method ? 'selected' : '' ?>><?= escapeHtml($method) ?></option>
                <?php } ?>
            </select>
        </div>
    </div>

    <div class="col-md-7">
        <label class="form-label">Reference</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-hashtag"></i></span>
            <input type="text" class="form-control" name="reference" maxlength="200" placeholder="Check number, deposit or transaction number" value="<?= escapeHtml($form_reference) ?>">
        </div>
    </div>

    <div class="col-12">
        <label class="form-label">Description</label>
        <div class="input-group">
            <span class="input-group-text align-items-start pt-2"><i class="fa fa-fw fa-align-left"></i></span>
            <textarea class="form-control" rows="3" name="description" placeholder="What it was - Pax8 partner rebate for Q3, referral fee from Ravenwood, interest"><?= escapeHtml($form_description) ?></textarea>
        </div>
        <div class="form-text">The first line shows in the list.</div>
    </div>

</div>

<script>
(function () {
    // The currency is the account's
    var root = document.getElementById(<?= json_encode($form_id) ?>);
    if (!root || root.dataset.revenueWired) {
        return;
    }
    root.dataset.revenueWired = '1';
    var account = root.querySelector('[data-revenue-account]');
    var currency = root.querySelector('[data-revenue-currency]');
    var update = function () {
        var option = account.options[account.selectedIndex];
        currency.textContent = option && option.dataset.currency ? option.dataset.currency : <?= json_encode((string) $session_company_currency) ?>;
    };
    account.addEventListener('change', update);
    if (window.jQuery) {
        window.jQuery(account).on('change select2:select', update);
    }
})();
</script>
