<?php

/*
 * The fields of an account - shared by New Account and Edit. Set before
 * including:
 *
 *   $form_row   accountRow() for edit; null for new
 *   $form_type  the kind a new one starts as
 *
 * Posts name, type, currency_code, opening_balance, description and notes
 * (agent/post/account.php). The currency can't change once anything is on
 * the account.
 *
 * Should not be accessed directly.
 */

isset($mysqli) || die("Direct file access is not allowed");

$form_id = 'accountForm' . ($form_row ? intval($form_row['account_id']) : 'New');
$form_type = $form_row ? accountType($form_row) : intval($form_type ?? 1);
$form_currency = $form_row ? (string) $form_row['account_currency_code'] : (string) $session_company_currency;
$form_locked = $form_row && accountInUse($form_row['account_id']);
$form_types = accountTypes();

?>
<div class="row g-3" id="<?= $form_id ?>">

    <div class="col-md-7">
        <label class="form-label">Name <strong class="text-danger">*</strong></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-piggy-bank"></i></span>
            <input type="text" class="form-control" name="name" maxlength="200" placeholder="Operating Checking, Business Visa, Stripe" value="<?= escapeHtml($form_row['account_name'] ?? '') ?>" required <?= $form_row ? '' : 'autofocus' ?>>
        </div>
    </div>

    <div class="col-md-5">
        <label class="form-label">Kind <strong class="text-danger">*</strong></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-shapes"></i></span>
            <select class="form-select select2" name="type" required data-account-type>
                <?php foreach ($form_types as $type => $info) { ?>
                    <option value="<?= $type ?>" <?= $form_type === $type ? 'selected' : '' ?> data-icon="fas <?= $info['icon'] ?>"><?= escapeHtml($info['label']) ?></option>
                <?php } ?>
            </select>
        </div>
    </div>

    <div class="col-md-7">
        <label class="form-label">Currency <strong class="text-danger">*</strong></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-money-bill"></i></span>
            <select class="form-select select2" name="currency_code" required <?= $form_locked ? 'disabled' : '' ?>>
                <option value="">- Currency -</option>
                <?php foreach ($currencies_array as $currency_code => $currency_name) { ?>
                    <option <?= $form_currency === $currency_code ? 'selected' : '' ?> value="<?= escapeHtml($currency_code) ?>"><?= escapeHtml("$currency_code - $currency_name") ?></option>
                <?php } ?>
            </select>
            <?php if ($form_locked) { ?>
                <input type="hidden" name="currency_code" value="<?= escapeHtml($form_currency) ?>">
            <?php } ?>
        </div>
        <?php if ($form_locked) { ?>
            <div class="form-text">Money is recorded on it in <?= escapeHtml($form_currency) ?>, so that stays.</div>
        <?php } ?>
    </div>

    <div class="col-md-5">
        <label class="form-label">Opening balance <strong class="text-danger">*</strong></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-dollar-sign"></i></span>
            <input type="text" class="form-control text-end" inputmode="decimal" pattern="-?[0-9,]*\.?[0-9]{0,2}" name="opening_balance" placeholder="0.00" required
                value="<?= $form_row ? number_format(floatval($form_row['opening_balance']), 2, '.', '') : '0.00' ?>">
        </div>
        <div class="form-text" data-account-opening-note><?= $form_row && $form_row['line_count'] ? 'What it held before the first line here - changing it moves every balance after.' : 'What it holds before anything is recorded here.' ?><span data-account-card-note <?= $form_type === 3 ? '' : 'hidden' ?>> A card owing money starts below zero.</span></div>
    </div>

    <div class="col-12">
        <label class="form-label">What it is for</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-info-circle"></i></span>
            <input type="text" class="form-control" name="description" maxlength="250" placeholder="Day to day banking - client payments in, bills out" value="<?= escapeHtml($form_row['account_description'] ?? '') ?>">
        </div>
    </div>

    <div class="col-12">
        <label class="form-label">Notes</label>
        <div class="input-group">
            <span class="input-group-text align-items-start pt-2"><i class="fa fa-fw fa-sticky-note"></i></span>
            <textarea class="form-control" rows="3" name="notes" placeholder="The bank, the last four digits, who can sign"><?= escapeHtml($form_row['account_notes'] ?? '') ?></textarea>
        </div>
    </div>

</div>

<script>
(function () {
    // A card owing money starts below zero - say so for a credit card
    var root = document.getElementById(<?= json_encode($form_id) ?>);
    if (!root || root.dataset.wired) {
        return;
    }
    root.dataset.wired = '1';
    var type = root.querySelector('[data-account-type]');
    var note = root.querySelector('[data-account-card-note]');
    type.addEventListener('change', function () {
        note.hidden = type.value !== '3';
    });
})();
</script>
