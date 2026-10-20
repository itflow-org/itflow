<?php

/*
 * Admin > Settings > Defaults - the page ITFlow opens on, the calendar new
 * events go on, what a new client starts with, and - with accounting on - the
 * accounts and payment methods payments, expenses and transfers start with.
 * Each is what a form starts with; it can be changed there. Saved by
 * edit_default_settings (admin/post/settings_default.php).
 */

require_once "includes/inc_all_admin.php";

// The start page - the usual ones, and the one saved should it be another
$start_pages = settingsStartPages();
if ((string) $config_start_page !== '' && !isset($start_pages[$config_start_page])) {
    $start_pages[$config_start_page] = $config_start_page;
}
$kept_start_page = (string) settingsValue('config_start_page', $config_start_page);
if ($kept_start_page !== '' && !isset($start_pages[$kept_start_page])) {
    $start_pages[$kept_start_page] = $kept_start_page;
}

$calendars = [0 => '- None -'];
$sql = mysqli_query($mysqli, "SELECT calendar_id, calendar_name FROM calendars ORDER BY calendar_name ASC");
while ($row = mysqli_fetch_assoc($sql)) {
    $calendars[intval($row['calendar_id'])] = $row['calendar_name'];
}

// Accounts in use, and any saved one since archived - so saving doesn't lose it
$saved_accounts = array_map('intval', [$config_default_payment_account, $config_default_expense_account, $config_default_transfer_from_account, $config_default_transfer_to_account]);
$accounts = [0 => '- None -'];
$sql = mysqli_query($mysqli, "SELECT account_id, account_name, account_archived_at FROM accounts
    WHERE account_archived_at IS NULL OR account_id IN (" . implode(',', $saved_accounts) . ") ORDER BY account_name ASC");
while ($row = mysqli_fetch_assoc($sql)) {
    $accounts[intval($row['account_id'])] = $row['account_name'] . ($row['account_archived_at'] ? ' (archived)' : '');
}

// Admin > Payment Methods - and a saved one, should it no longer be among them
$methods = function ($saved) {
    $choices = ['' => '- None -'];
    foreach (paymentMethods() as $method) {
        $choices[$method] = $method;
    }
    if ((string) $saved !== '' && !isset($choices[(string) $saved])) {
        $choices[(string) $saved] = $saved;
    }
    return $choices;
};

echo settingsPageStart('default');

?>

<form action="post.php" method="post" autocomplete="off" data-settings-form>
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="card itflow-settings-card">
        <div class="card-body">

            <?= settingsSectionStart('opening', 'Opening ITFlow', 'Where everyone lands on signing in or clicking the logo, and the calendar a new event goes on.') ?>
                <?= settingsSelect('config_start_page', $config_start_page, $start_pages, ['icon' => 'fa-home', 'search' => true, 'required' => true, 'data' => ['tags' => 'true'],
                    'help' => 'Or type another page, as it is after /agent/ in its address - tickets.php?status=Open.']) ?>
                <?= settingsSelect('config_default_calendar', intval($config_default_calendar), $calendars, ['icon' => 'fa-calendar']) ?>
            <?= settingsSectionEnd() ?>

            <?= settingsSectionStart('clients', 'New clients', 'What a new client starts with - each can have its own, on its Billing tab.') ?>
                <div class="row g-3">
                    <div class="col-md-6"><?= settingsSelect('config_default_net_terms', intval($config_default_net_terms), settingsNetTerms(), ['icon' => 'fa-calendar-check',
                        'help' => 'When an invoice is due.']) ?></div>
                    <div class="col-md-6"><?= settingsInput('config_default_hourly_rate', number_format(floatval($config_default_hourly_rate), 2, '.', ''), ['icon' => 'fa-clock', 'inputmode' => 'decimal',
                        'placeholder' => '0.00', 'suffix' => escapeHtml($session_company_currency) . ' an hour', 'help' => 'And what time bills at for a client with none of its own.']) ?></div>
                </div>
            <?= settingsSectionEnd() ?>

            <?php if ($config_module_enable_accounting) { ?>
                <?= settingsSectionStart('accounts', 'Accounts', 'The account and payment method a payment, an expense or a transfer starts with.') ?>
                    <div class="row g-3">
                        <div class="col-md-6"><?= settingsSelect('config_default_payment_account', intval($config_default_payment_account), $accounts, ['icon' => 'fa-piggy-bank', 'search' => true]) ?></div>
                        <div class="col-md-6"><?= settingsSelect('config_default_payment_method', (string) $config_default_payment_method, $methods($config_default_payment_method), ['icon' => 'fa-credit-card', 'search' => true]) ?></div>
                        <div class="col-md-6"><?= settingsSelect('config_default_expense_account', intval($config_default_expense_account), $accounts, ['icon' => 'fa-shopping-cart', 'search' => true]) ?></div>
                        <div class="col-md-6"><?= settingsSelect('config_default_expense_payment_method', (string) $config_default_expense_payment_method, $methods($config_default_expense_payment_method), ['icon' => 'fa-credit-card', 'search' => true]) ?></div>
                        <div class="col-md-6"><?= settingsSelect('config_default_transfer_from_account', intval($config_default_transfer_from_account), $accounts, ['icon' => 'fa-sign-out-alt', 'search' => true]) ?></div>
                        <div class="col-md-6"><?= settingsSelect('config_default_transfer_to_account', intval($config_default_transfer_to_account), $accounts, ['icon' => 'fa-sign-in-alt', 'search' => true]) ?></div>
                    </div>
                    <div class="form-text mt-2">Accounts are in <a href="/agent/accounts.php">Accounts</a>, payment methods in <a href="payment_methods.php">Payment methods</a>.</div>
                <?= settingsSectionEnd() ?>
            <?php } ?>

            <?= settingsSaveBar('edit_default_settings') ?>
        </div>
    </div>
</form>

<?php
require_once "../includes/footer.php";
