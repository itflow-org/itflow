<?php

/*
 * Get started - Billing: the hourly rate and net terms a new client starts
 * with, the tax new lines get (a rate is made when there is none), how
 * invoices and quotes are numbered, and - if you like - Stripe, for clients
 * to pay invoices by card (getting_started_billing). The same settings as
 * Admin > Defaults, Taxes, Invoices, Quotes and Payment Providers.
 */

defined('FROM_GETTING_STARTED') || die("Direct file access is not allowed");

$gs_billing = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT config_default_hourly_rate, config_default_net_terms, config_invoice_prefix, config_invoice_next_number,
    config_quote_prefix, config_quote_next_number FROM settings WHERE company_id = 1")) ?: [];
$gs_rate = floatval($gs_billing['config_default_hourly_rate'] ?? 0);
$gs_tax = taxRow(taxSettings()['default_id']);
$gs_stripe = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT payment_provider_id, payment_provider_active, payment_provider_private_key FROM payment_providers WHERE payment_provider_name = 'Stripe' LIMIT 1"));
$gs_accounts = [];
$gs_sql = mysqli_query($mysqli, "SELECT account_id, account_name FROM accounts WHERE account_archived_at IS NULL ORDER BY account_name ASC");
while ($gs_row = mysqli_fetch_assoc($gs_sql)) {
    $gs_accounts[intval($gs_row['account_id'])] = $gs_row['account_name'];
}
$gs_stripe_on = settingsChecked('stripe_on', false);

?>
<form action="post.php" method="post" autocomplete="off" data-gs-form>
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <h2 class="h6 fw-semibold">What you charge</h2>
    <div class="row g-3 mb-4">
        <div class="col-sm-6">
            <?= settingsInput('config_default_hourly_rate', $gs_rate > 0 ? number_format($gs_rate, 2, '.', '') : '', ['icon' => 'fa-coins', 'type' => 'number', 'label' => 'Hourly rate', 'required' => true,
                'min' => '0.01', 'step' => '0.01', 'inputmode' => 'decimal', 'placeholder' => '125.00', 'suffix' => escapeHtml($session_company_currency) . ' an hour',
                'help' => 'For time on a ticket - a client can have their own.']) ?>
        </div>
        <div class="col-sm-6">
            <?= settingsSelect('config_default_net_terms', (string) intval($gs_billing['config_default_net_terms'] ?? 30), settingsNetTerms(), ['icon' => 'fa-calendar-check', 'label' => 'Invoices are due',
                'help' => 'A new client starts with these.']) ?>
        </div>
    </div>

    <h2 class="h6 fw-semibold">Tax</h2>
    <?php if ($gs_tax) { ?>
        <p class="mb-4" data-gs-tax="<?= intval($gs_tax['tax_id']) ?>"><i class="fas fa-check-circle text-success me-2"></i>New lines get <strong><?= escapeHtml(taxLabel($gs_tax)) ?></strong>. Rates with more than one part, and what is taxed, are in Admin &gt; Taxes.</p>
    <?php } else { ?>
        <p class="small text-body-secondary">The rate new invoice and quote lines get - leave it empty if you don't charge tax. A rate in parts, as GST and PST, is made in Admin &gt; Taxes.</p>
        <div class="row g-3 mb-4" data-gs-tax-new>
            <div class="col-sm-7">
                <?= settingsInput('tax_name', '', ['icon' => 'fa-receipt', 'label' => 'Name', 'maxlength' => 200, 'placeholder' => 'Sales Tax']) ?>
            </div>
            <div class="col-sm-5">
                <?= settingsInput('tax_percent', '', ['icon' => 'fa-calculator', 'type' => 'number', 'label' => 'Rate', 'min' => 0, 'max' => 100, 'step' => '0.0001', 'inputmode' => 'decimal', 'placeholder' => '6', 'suffix' => '%']) ?>
            </div>
        </div>
    <?php } ?>

    <h2 class="h6 fw-semibold">Invoice numbers</h2>
    <div class="mb-4"><?= settingsNumberingFields('invoice', (string) ($gs_billing['config_invoice_prefix'] ?? ''), intval($gs_billing['config_invoice_next_number'] ?? 1)) ?></div>

    <h2 class="h6 fw-semibold">Quote numbers</h2>
    <div class="mb-4"><?= settingsNumberingFields('quote', (string) ($gs_billing['config_quote_prefix'] ?? ''), intval($gs_billing['config_quote_next_number'] ?? 1)) ?></div>

    <h2 class="h6 fw-semibold">Card payments</h2>
    <?php if ($gs_stripe) { ?>
        <p class="mb-4" data-gs-stripe="<?= intval($gs_stripe['payment_provider_id']) ?>"><i class="fas <?= intval($gs_stripe['payment_provider_active']) ? 'fa-check-circle text-success' : 'fa-pause-circle text-body-secondary' ?> me-2"></i>Stripe is set up<?= intval($gs_stripe['payment_provider_active']) ? '' : ', and turned off' ?><?= paymentProviderMode($gs_stripe['payment_provider_private_key']) === 'test' ? ' with test keys' : '' ?> - in Admin &gt; Payment Providers.</p>
    <?php } else { ?>
        <div class="itflow-gs-optional mb-4" data-gs-stripe-new>
            <?= settingsSwitch('stripe_on', $gs_stripe_on, ['id' => 'gsStripeOn', 'label' => 'Let clients pay invoices by card, with Stripe', 'data' => ['gs-reveal' => 'gsStripe'],
                'help' => 'A Pay button on each invoice, and cards clients save for autopay.']) ?>
            <div id="gsStripe" class="row g-3 mt-0" data-gs-reveal-target<?= $gs_stripe_on ? '' : ' hidden' ?>>
                <div class="col-12 small text-body-secondary">The keys are in Stripe's <a href="<?= escapeHtml(paymentProviderKinds()['Stripe']['keys_url']) ?>" target="_blank" rel="noopener noreferrer">Developers &gt; API keys</a>. Test keys work too, until you are ready to take real cards.</div>
                <div class="col-sm-6">
                    <?= settingsInput('stripe_public_key', '', ['icon' => 'fa-key', 'label' => 'Publishable key', 'maxlength' => 250, 'placeholder' => 'pk_live_...', 'autocomplete' => 'off', 'class' => 'font-monospace']) ?>
                </div>
                <div class="col-sm-6">
                    <?= settingsInput('stripe_private_key', '', ['icon' => 'fa-lock', 'type' => 'password', 'label' => 'Secret key', 'maxlength' => 250, 'placeholder' => 'sk_live_...', 'autocomplete' => 'new-password', 'class' => 'font-monospace']) ?>
                </div>
                <div class="col-12">
                    <?= settingsSelect('stripe_account', (string) (array_key_first($gs_accounts) ?? ''), $gs_accounts ?: ['' => 'No accounts yet - add one in Finance > Accounts'], ['icon' => 'fa-piggy-bank', 'label' => 'Payments go into',
                        'help' => 'Fees, and a card limit, are set in Admin &gt; Payment Providers.']) ?>
                </div>
            </div>
        </div>
    <?php } ?>

    <button type="submit" name="getting_started_billing" value="1" class="btn btn-primary" data-gs-save>Save and continue<i class="fas fa-arrow-right ms-2"></i></button>
</form>
