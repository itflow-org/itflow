<?php

/*
 * Admin > Payment Providers - one provider's window: Add (no id, or from
 * payment_provider_add.php) or Edit. Its keys - the secret one never put back
 * into the page, so left empty it stays - whether it is on, the account its
 * payments go into, the card limit, whether its fees are recorded as
 * expenses, and whether clients pay a card fee to cover them. Save asks first (check_payment_provider,
 * admin/post/payment_provider.php): what is wrong shows beside each field and
 * the window stays open; once all is right, it saves.
 */

require_once '../../includes/modal_header.php';

$provider_id = intval($_GET['id'] ?? 0);
$row = $provider_id ? paymentProviderRow($provider_id) : null;
if ($provider_id && !$row) {
    echo json_encode(['error' => 'That payment provider no longer exists.']);
    exit;
}

$kinds = paymentProviderKinds();
$taken = [];
$sql = mysqli_query($mysqli, "SELECT payment_provider_name FROM payment_providers");
while ($taken_row = mysqli_fetch_row($sql)) {
    $taken[$taken_row[0]] = true;
}
$free_kinds = array_diff_key($kinds, $taken);

$name = $row ? (string) $row['payment_provider_name'] : (string) (array_key_first($free_kinds) ?? array_key_first($kinds));
$kind = $kinds[$name] ?? reset($kinds);
$mode = $row ? paymentProviderMode($row['payment_provider_private_key']) : '';
$account_id = intval($row['payment_provider_account'] ?? 0);
$vendor_id = intval($row['payment_provider_expense_vendor'] ?? 0);
$category_id = intval($row['payment_provider_expense_category'] ?? 0);
$threshold = round(floatval($row['payment_provider_threshold'] ?? 0), 2);
$records_fees = $row ? ($vendor_id > 0 && $category_id > 0) : true;
$card_fee = intval($row['payment_provider_card_fee'] ?? 0) === 1;
$card_fee_percent = round(floatval($row['payment_provider_card_fee_percent'] ?? 0), 2);
$card_fee_fixed = round(floatval($row['payment_provider_card_fee_fixed'] ?? 0), 2);

// A new one starts on an account and a vendor named for it, and Processing Fee, where there are those
if (!$row) {
    $starts = mysqli_fetch_row(mysqli_query($mysqli, "SELECT
        (SELECT account_id FROM accounts WHERE account_name = '" . escapeSql($name) . "' AND account_archived_at IS NULL LIMIT 1),
        (SELECT vendor_id FROM vendors WHERE vendor_name = '" . escapeSql($name) . "' AND vendor_client_id = 0 AND vendor_archived_at IS NULL LIMIT 1),
        (SELECT category_id FROM categories WHERE category_type = 'Expense' AND category_name = 'Processing Fee' AND category_archived_at IS NULL LIMIT 1)"));
    $account_id = intval($starts[0] ?? 0);
    $vendor_id = intval($starts[1] ?? 0);
    $category_id = intval($starts[2] ?? 0);
}

$accounts = mysqli_query($mysqli, "SELECT account_id, account_name, account_archived_at FROM accounts WHERE account_archived_at IS NULL OR account_id = $account_id ORDER BY account_name ASC");
$vendors = mysqli_query($mysqli, "SELECT vendor_id, vendor_name, vendor_archived_at FROM vendors WHERE vendor_client_id = 0 AND (vendor_archived_at IS NULL OR vendor_id = $vendor_id) ORDER BY vendor_name ASC");
$currency = (string) ($session_company_currency ?? '');

// Where what is wrong with a field shows
$error_for = function ($field) {
    return '<div class="invalid-feedback" data-error-for="' . $field . '"></div>';
};

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title">
        <i class="fa fa-fw fa-credit-card me-2"></i><?= $row ? '<strong>' . escapeHtml($name) . '</strong>' : 'Add Payment Provider' ?>
        <?php if ($mode !== '') { ?>
            <span class="badge <?= $mode === 'live' ? 'text-bg-success' : 'text-bg-warning' ?> ms-2 align-middle" data-provider-mode><?= $mode === 'live' ? 'Live' : 'Test' ?></span>
        <?php } ?>
    </h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off" novalidate data-payment-provider-form>
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="provider_id" value="<?= $provider_id ?>">

    <div class="modal-body">

        <?php if (!$row && !$free_kinds) { ?>
            <div class="alert alert-info py-2 small mb-3"><i class="fas fa-fw fa-info-circle me-1"></i><?= escapeHtml(implode(' and ', array_keys($kinds))) ?> is set up already - it is the only provider ITFlow takes payments through.</div>
        <?php } ?>

        <?php if (!$row) { ?>
            <div class="mb-3">
                <label class="form-label" for="paymentProviderName">Provider <strong class="text-danger">*</strong></label>
                <div class="input-group has-validation">
                    <span class="input-group-text"><i class="fa fa-fw fa-credit-card"></i></span>
                    <select class="form-select" name="provider" id="paymentProviderName">
                        <?php foreach ($kinds as $kind_name => $kind_info) { ?>
                            <option value="<?= escapeHtml($kind_name) ?>" <?= $kind_name === $name ? 'selected' : '' ?> <?= isset($taken[$kind_name]) ? 'disabled' : '' ?>><?= escapeHtml($kind_name) ?><?= isset($taken[$kind_name]) ? ' - set up already' : '' ?></option>
                        <?php } ?>
                    </select>
                </div>
                <?= $error_for('provider') ?>
            </div>
        <?php } else { ?>
            <div class="mb-3">
                <div class="form-check form-switch">
                    <input type="checkbox" class="form-check-input" name="active" value="1" id="paymentProviderActive" <?= intval($row['payment_provider_active']) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="paymentProviderActive">On - clients can pay invoices by card</label>
                </div>
                <div class="form-text ps-5">Off, they can't, and autopay with its saved cards is skipped. Its cards are kept.</div>
            </div>
        <?php } ?>

        <div class="mb-3">
            <label class="form-label" for="paymentProviderDescription">Description</label>
            <div class="input-group has-validation">
                <span class="input-group-text"><i class="fa fa-fw fa-align-left"></i></span>
                <input type="text" class="form-control" name="description" id="paymentProviderDescription" maxlength="250" placeholder="What it is for, if you like" value="<?= escapeHtml($row['payment_provider_description'] ?? '') ?>">
            </div>
            <?= $error_for('description') ?>
        </div>

        <h6 class="fw-semibold mt-4 mb-1">Keys</h6>
        <p class="small text-body-secondary mb-2">From <a href="<?= escapeHtml($kind['keys_url']) ?>" target="_blank" rel="noopener noreferrer"><?= escapeHtml($name) ?>'s Developers &gt; API keys <i class="fas fa-external-link-alt fa-xs"></i></a> - both live, or both test while you try it out.</p>

        <div class="mb-3">
            <label class="form-label" for="paymentProviderPublicKey">Publishable key <strong class="text-danger">*</strong></label>
            <div class="input-group has-validation">
                <span class="input-group-text"><i class="fa fa-fw fa-eye"></i></span>
                <input type="text" class="form-control font-monospace" name="public_key" id="paymentProviderPublicKey" maxlength="250" spellcheck="false" placeholder="<?= escapeHtml($kind['public_example']) ?>" value="<?= escapeHtml($row['payment_provider_public_key'] ?? '') ?>">
            </div>
            <?= $error_for('public_key') ?>
        </div>

        <div class="mb-3">
            <label class="form-label" for="paymentProviderSecretKey">Secret key <?= $row ? '' : '<strong class="text-danger">*</strong>' ?></label>
            <div class="input-group has-validation">
                <span class="input-group-text"><i class="fa fa-fw fa-key"></i></span>
                <input type="password" class="form-control font-monospace" name="private_key" id="paymentProviderSecretKey" maxlength="250" spellcheck="false" autocomplete="new-password"
                    placeholder="<?= $row && trim((string) $row['payment_provider_private_key']) !== '' ? 'Saved - type a new one to replace it' : escapeHtml($kind['secret_example']) ?>">
            </div>
            <?= $error_for('private_key') ?>
            <div class="form-text">A restricted key (rk_...) does too, with write access to payment intents, customers, setup intents and payment methods, and read on the account.</div>
        </div>

        <div class="alert alert-success py-2 small d-none" data-provider-account></div>

        <h6 class="fw-semibold mt-4 mb-2">Payments</h6>

        <div class="mb-3">
            <label class="form-label" for="paymentProviderAccount">Payments go into <strong class="text-danger">*</strong></label>
            <div class="input-group has-validation">
                <span class="input-group-text"><i class="fa fa-fw fa-piggy-bank"></i></span>
                <select class="form-select select2" name="account" id="paymentProviderAccount">
                    <option value="">- Pick an account -</option>
                    <?php while ($account = mysqli_fetch_assoc($accounts)) { ?>
                        <option value="<?= intval($account['account_id']) ?>" <?= intval($account['account_id']) === $account_id ? 'selected' : '' ?>><?= escapeHtml($account['account_name']) ?><?= $account['account_archived_at'] ? ' (archived)' : '' ?></option>
                    <?php } ?>
                </select>
            </div>
            <?= $error_for('account') ?>
            <div class="form-text">An account of its own, named for it, makes its payouts easy to match.</div>
        </div>

        <div class="mb-3">
            <label class="form-label" for="paymentProviderThreshold">Card limit</label>
            <div class="input-group has-validation">
                <span class="input-group-text"><i class="fa fa-fw fa-shopping-cart"></i></span>
                <input type="text" class="form-control" name="threshold" id="paymentProviderThreshold" inputmode="decimal" maxlength="16" placeholder="No limit" value="<?= $threshold > 0 ? escapeHtml(number_format($threshold, 2, '.', '')) : '' ?>">
                <?php if ($currency !== '') { ?><span class="input-group-text"><?= escapeHtml($currency) ?></span><?php } ?>
            </div>
            <?= $error_for('threshold') ?>
            <div class="form-text">An invoice of this much or more can't be paid by card. Empty for no limit.</div>
        </div>

        <h6 class="fw-semibold mt-4 mb-2">Fees</h6>

        <div class="mb-3">
            <div class="form-check form-switch">
                <input type="checkbox" class="form-check-input" name="record_fees" value="1" id="paymentProviderRecordFees" <?= $records_fees ? 'checked' : '' ?> data-provider-fees-switch>
                <label class="form-check-label" for="paymentProviderRecordFees">Record its fees as expenses</label>
            </div>
            <div class="form-text ps-5">Its fee on each card payment is recorded as an expense from the account above, a few minutes after Stripe settles the payment.</div>
        </div>

        <div data-provider-fees>
            <div class="mb-3">
                <label class="form-label" for="paymentProviderVendor">Paid to <strong class="text-danger">*</strong></label>
                <div class="input-group has-validation">
                    <span class="input-group-text"><i class="fa fa-fw fa-building"></i></span>
                    <select class="form-select select2" name="expense_vendor" id="paymentProviderVendor">
                        <option value="">- Pick a vendor -</option>
                        <?php while ($vendor = mysqli_fetch_assoc($vendors)) { ?>
                            <option value="<?= intval($vendor['vendor_id']) ?>" <?= intval($vendor['vendor_id']) === $vendor_id ? 'selected' : '' ?>><?= escapeHtml($vendor['vendor_name']) ?><?= $vendor['vendor_archived_at'] ? ' (archived)' : '' ?></option>
                        <?php } ?>
                    </select>
                </div>
                <?= $error_for('expense_vendor') ?>
                <div class="form-text">One of your own vendors - <?= escapeHtml($name) ?> itself.</div>
            </div>

            <div class="mb-3">
                <label class="form-label" for="paymentProviderCategory">Category <strong class="text-danger">*</strong></label>
                <div class="input-group has-validation">
                    <span class="input-group-text"><i class="fa fa-fw fa-list"></i></span>
                    <select class="form-select select2" name="expense_category" id="paymentProviderCategory">
                        <option value="">- Pick a category -</option>
                        <?= categoryOptionsHtml('Expense', $category_id ?: null) ?>
                    </select>
                    <?= categoryAddButtonHtml('Expense', 'btn btn-secondary') ?>
                </div>
                <?= $error_for('expense_category') ?>
                <div class="form-text">Processing Fee, or the like.</div>
            </div>
        </div>

        <div class="mb-3">
            <div class="form-check form-switch">
                <input type="checkbox" class="form-check-input" name="card_fee" value="1" id="paymentProviderCardFee" <?= $card_fee ? 'checked' : '' ?> data-provider-card-fee-switch>
                <label class="form-check-label" for="paymentProviderCardFee">Clients pay the card fee</label>
            </div>
            <div class="form-text ps-5">Each card payment is made bigger by what <?= escapeHtml($name) ?> takes of it, so you get what the invoice is for. The fee goes on the invoice as a Card Fee line, and clients see it before they pay - in the client portal, through an invoice's link, by autopay, or when you charge a saved card. A client can be let off it on their Billing tab.</div>
        </div>

        <div data-provider-card-fee>
            <div class="row">
                <div class="col-sm-6 mb-3">
                    <label class="form-label" for="paymentProviderCardFeePercent">Percent</label>
                    <div class="input-group has-validation">
                        <span class="input-group-text"><i class="fa fa-fw fa-percent"></i></span>
                        <input type="text" class="form-control" name="card_fee_percent" id="paymentProviderCardFeePercent" inputmode="decimal" maxlength="5" placeholder="2.9" value="<?= $card_fee_percent > 0 ? escapeHtml(rtrim(rtrim(number_format($card_fee_percent, 2, '.', ''), '0'), '.')) : '' ?>">
                        <span class="input-group-text">%</span>
                    </div>
                    <?= $error_for('card_fee_percent') ?>
                </div>
                <div class="col-sm-6 mb-3">
                    <label class="form-label" for="paymentProviderCardFeeFixed">Plus</label>
                    <div class="input-group has-validation">
                        <span class="input-group-text"><i class="fa fa-fw fa-coins"></i></span>
                        <input type="text" class="form-control" name="card_fee_fixed" id="paymentProviderCardFeeFixed" inputmode="decimal" maxlength="6" placeholder="0.30" value="<?= $card_fee_fixed > 0 ? escapeHtml(number_format($card_fee_fixed, 2, '.', '')) : '' ?>">
                        <?php if ($currency !== '') { ?><span class="input-group-text"><?= escapeHtml($currency) ?></span><?php } ?>
                    </div>
                    <?= $error_for('card_fee_fixed') ?>
                </div>
            </div>
            <div class="form-text mt-n2 mb-3">What <?= escapeHtml($name) ?> charges you on a card payment - your account's rate, as 2.9% plus 0.30. Card surcharges have rules where you and your clients are: in the US, for one, the card networks cap them and don't allow them on debit cards, and some states limit them further.</div>
        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="<?= $row ? 'edit_payment_provider' : 'add_payment_provider' ?>" class="btn btn-primary text-bold" data-provider-save <?= !$row && !$free_kinds ? 'disabled' : '' ?>>
            <i class="fa fa-check me-2"></i><?= $row ? 'Save' : 'Add' ?>
        </button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<script>
// Fees - their vendor and category only while they are recorded, the card fee's rates only while clients pay it. Save - asked first, so what is wrong shows here
(function () {
    var form = document.querySelector('[data-payment-provider-form]:not([data-ready])');
    if (!form) {
        return;
    }
    form.setAttribute('data-ready', '1');
    var feesSwitch = form.querySelector('[data-provider-fees-switch]');
    var fees = form.querySelector('[data-provider-fees]');
    var cardFeeSwitch = form.querySelector('[data-provider-card-fee-switch]');
    var cardFee = form.querySelector('[data-provider-card-fee]');
    var save = form.querySelector('[data-provider-save]');
    var accountNote = form.querySelector('[data-provider-account]');
    var checked = false;

    // A disabled select isn't sent, and its picker follows it
    function showFees() {
        var on = feesSwitch.checked;
        fees.classList.toggle('opacity-50', !on);
        fees.querySelectorAll('select, button').forEach(function (el) {
            el.disabled = !on;
        });
    }
    feesSwitch.addEventListener('change', showFees);
    showFees();

    // The card fee's rates only while clients pay it - off, they aren't sent and stay as saved
    function showCardFee() {
        var on = cardFeeSwitch.checked;
        cardFee.classList.toggle('opacity-50', !on);
        cardFee.querySelectorAll('input').forEach(function (el) {
            el.disabled = !on;
        });
    }
    cardFeeSwitch.addEventListener('change', showCardFee);
    showCardFee();

    function clearErrors() {
        form.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });
        form.querySelectorAll('[data-error-for]').forEach(function (el) {
            el.textContent = '';
            el.classList.remove('d-block');
        });
    }

    function showErrors(errors) {
        var first = null;
        Object.keys(errors).forEach(function (field) {
            var input = form.querySelector('[name="' + field + '"]');
            var box = form.querySelector('[data-error-for="' + field + '"]');
            if (input) {
                input.classList.add('is-invalid');
                first = first || input;
            }
            if (box) {
                box.textContent = errors[field];
                box.classList.add('d-block');
            }
        });
        if (first && first.offsetParent !== null) {
            first.focus();
        }
    }

    form.addEventListener('submit', function (e) {
        if (checked) {
            return;
        }
        e.preventDefault();
        var label = save.innerHTML;
        save.disabled = true;
        save.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Checking';
        var data = new FormData(form);
        data.append('check_payment_provider', '1');
        fetch(form.getAttribute('action'), { method: 'POST', body: data, credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (response) { return response.json(); })
            .then(function (answer) {
                clearErrors();
                save.disabled = false;
                if (answer.ok) {
                    if (answer.account && answer.account.name) {
                        accountNote.textContent = 'Connected to ' + answer.account.name + (answer.account.email ? ' (' + answer.account.email + ')' : '') + ' - saving';
                        accountNote.classList.remove('d-none');
                    }
                    checked = true;
                    save.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Saving';
                    form.requestSubmit(save);
                    save.disabled = true;
                    return;
                }
                save.innerHTML = label;
                showErrors(answer.errors || {});
            })
            .catch(function () {
                // Couldn't ask - send it as it is, and the save says what is wrong
                checked = true;
                save.disabled = false;
                save.innerHTML = label;
                form.requestSubmit(save);
            });
    });
})();
</script>

<?php
require_once '../../../includes/modal_footer.php';
