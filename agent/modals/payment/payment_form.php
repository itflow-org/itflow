<?php

/*
 * The fields of a payment - shared by Add Payment, Batch Payment and Edit
 * Payment. Set $pay before including:
 *
 *   amount     the amount to start with
 *   owed       what is owed, for "Pay in full" and what is left after (null to leave out)
 *   currency   the currency
 *   date, account_id, method, reference   to start with (editing)
 *   email      the primary contact's email, for the receipt ('' to leave out)
 *   lock       the statement it cleared on (reconcileLock()) - its amount, date and account stay
 *   full_text  what to say when the amount pays everything owed
 *   id         a unique id for the form's parts
 *
 * Posts date, amount, account, payment_method, reference and email_receipt.
 *
 * Should not be accessed directly.
 */

isset($mysqli) || die("Direct file access is not allowed");

$pay_accounts = paymentAccounts($pay['account_id'] ?? 0);
$pay_methods = paymentMethods();
$pay_account_id = intval($pay['account_id'] ?? $config_default_payment_account);
$pay_method = (string) ($pay['method'] ?? $config_default_payment_method);
$pay_owed = $pay['owed'] ?? null;
$pay_lock = $pay['lock'] ?? null;

?>
<div class="row g-3" id="<?= $pay['id'] ?>" data-pay-form data-owed="<?= $pay_owed === null ? '' : number_format($pay_owed, 2, '.', '') ?>">
    <?= reconcileFormNoteHtml($pay_lock) ?>
    <div class="col-sm-6">
        <label class="form-label">Amount <strong class="text-danger">*</strong></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-dollar-sign"></i></span>
            <input type="text" class="form-control text-end fw-semibold" inputmode="decimal" pattern="[0-9]*\.?[0-9]{0,2}" name="amount"
                value="<?= number_format(floatval($pay['amount']), 2, '.', '') ?>" placeholder="0.00" required data-pay-amount <?= $pay_lock ? 'readonly' : '' ?>>
            <span class="input-group-text"><?= escapeHtml($pay['currency']) ?></span>
        </div>
        <?php if ($pay_owed !== null) { ?>
            <div class="form-text" data-pay-note>
                <?= escapeHtml($pay['full_text'] ?? 'Pays it in full.') ?> <a href="#" class="ms-1 d-none" data-pay-full>Pay in full</a>
            </div>
        <?php } ?>
    </div>
    <div class="col-sm-6">
        <label class="form-label">Date <strong class="text-danger">*</strong></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
            <input type="date" class="form-control" name="date" max="2999-12-31" value="<?= escapeHtml($pay['date'] ?? date('Y-m-d')) ?>" required <?= $pay_lock ? 'readonly' : '' ?>>
        </div>
    </div>
    <div class="col-sm-6">
        <label class="form-label">Method <strong class="text-danger">*</strong></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-money-check-alt"></i></span>
            <select class="form-select select2" name="payment_method" required>
                <option value="">- How it was paid -</option>
                <?php foreach ($pay_methods as $method) { ?>
                    <option <?= $pay_method === $method ? 'selected' : '' ?>><?= escapeHtml($method) ?></option>
                <?php } ?>
            </select>
        </div>
    </div>
    <div class="col-sm-6">
        <label class="form-label">Reference</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-hashtag"></i></span>
            <input type="text" class="form-control" name="reference" placeholder="Check number, transaction ID" maxlength="200" value="<?= escapeHtml($pay['reference'] ?? '') ?>">
        </div>
    </div>
    <div class="col-12">
        <label class="form-label">Into account <strong class="text-danger">*</strong></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-piggy-bank"></i></span>
            <select class="form-select select2" name="account" required <?= $pay_lock ? 'disabled' : '' ?>>
                <option value="">- Select an account -</option>
                <?php foreach ($pay_accounts as $account_id => $account) { ?>
                    <option value="<?= $account_id ?>" <?= $pay_account_id === $account_id ? 'selected' : '' ?> data-icon="fas fa-piggy-bank"
                        data-description="<?= escapeHtml(billingMoney($account['balance'], $account['account_currency_code'])) ?><?= $account['account_archived_at'] ? ' - archived' : '' ?>"><?= escapeHtml($account['account_name']) ?></option>
                <?php } ?>
            </select>
            <?php if ($pay_lock) { ?>
                <input type="hidden" name="account" value="<?= $pay_account_id ?>">
            <?php } ?>
        </div>
    </div>
    <?php if (!empty($pay['email']) && !empty($config_smtp_provider)) { ?>
        <div class="col-12">
            <div class="form-check form-switch">
                <input type="checkbox" class="form-check-input" role="switch" id="<?= $pay['id'] ?>Receipt" name="email_receipt" value="1" checked>
                <label class="form-check-label" for="<?= $pay['id'] ?>Receipt">Email a receipt to <?= escapeHtml($pay['email']) ?></label>
            </div>
        </div>
    <?php } ?>
</div>

<?php if ($pay_owed !== null) { ?>
<script>
(function () {
    var root = document.getElementById(<?= json_encode($pay['id']) ?>);
    if (!root || root.hasAttribute('data-wired')) {
        return;
    }
    root.setAttribute('data-wired', '1');
    var owed = Number(root.getAttribute('data-owed'));
    var amount = root.querySelector('[data-pay-amount]');
    var note = root.querySelector('[data-pay-note]');
    var full = root.querySelector('[data-pay-full]');
    var currency = <?= json_encode((string) $pay['currency']) ?>;
    var locale = <?= json_encode(str_replace('_', '-', (string) $session_company_locale)) ?>;

    function money(value) {
        try {
            return new Intl.NumberFormat(locale, { style: 'currency', currency: currency }).format(value);
        } catch (e) {
            return value.toFixed(2);
        }
    }
    function update() {
        var value = Number(String(amount.value).replace(/[\s,$]/g, ''));
        var left = Math.round((owed - value) * 100) / 100;
        var text;
        if (isNaN(value) || value <= 0) {
            text = 'Enter what was paid.';
        } else if (left > 0) {
            text = money(left) + ' left to pay after this.';
        } else if (left === 0) {
            text = <?= json_encode((string) ($pay['full_text'] ?? 'Pays it in full.')) ?>;
        } else {
            text = 'That is ' + money(-left) + ' more than is owed.';
        }
        note.firstChild.textContent = text + ' ';
        full.classList.toggle('d-none', left === 0);
    }
    amount.addEventListener('input', update);
    full.addEventListener('click', function (e) {
        e.preventDefault();
        amount.value = owed.toFixed(2);
        update();
    });
    update();
})();
</script>
<?php } ?>
