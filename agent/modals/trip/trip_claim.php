<?php

/*
 * Pay Mileage - pay a driver for the trips in their own vehicle not paid
 * back for yet, ticked, at the mileage rate. Each client's trips become a
 * mileage expense paid personally by the driver, to pay back with anything
 * else they paid for - there and then from an account, or later with
 * Reimburse. ?user_id= for one driver's, or ?trip_ids[]= from the list's
 * bulk menu or a trip's quick look (one driver's at a time). Posts
 * trip_claim.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_financial', 2);

$user_id = intval($_GET['user_id'] ?? 0);
$picked = array_values(array_unique(array_filter(array_map('intval', (array) ($_GET['trip_ids'] ?? [])))));
if (!$user_id && $picked) {
    $drivers = tripsToClaim(0, $picked);
    if (count($drivers) > 1) {
        exit(json_encode(['error' => 'Those were driven by ' . count($drivers) . ' different agents - tick one driver\'s trips at a time.']));
    }
    $user_id = $drivers ? intval(array_key_first($drivers)) : 0;
}
$driver = $user_id ? (tripsToClaim($user_id)[$user_id] ?? null) : null;
if (!$driver) {
    exit(json_encode(['error' => $picked ? 'None of those are waiting to be paid back for - they were in a company vehicle, or paid back for already.' : 'Nothing is waiting to be paid back for them.']));
}

$rules = tripRules();
$accounts = paymentAccounts();
$methods = paymentMethods();
$default_account = intval($config_default_expense_account);
$currency = (string) $session_company_currency;
$category_name = (string) getFieldById('categories', tripMileageCategoryShown(), 'category_name');

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title text-truncate"><i class="fas fa-fw fa-car-side me-2"></i>Pay Mileage <span class="fw-normal text-white-50 ms-1"><?= escapeHtml($driver['user_name']) ?></span></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off" id="tripClaim">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="user_id" value="<?= $user_id ?>">

    <div class="modal-body p-0">
        <div class="px-3 py-2 small text-body-secondary border-bottom">
            <?= escapeHtml($driver['user_name']) ?>'s trips in their own vehicle, not paid back for yet. Each client's become a mileage expense <?= escapeHtml($driver['user_name']) ?> paid personally<?= $category_name !== '' ? ', under ' . escapeHtml($category_name) : '' ?> - so the client's costs include the driving - to pay back now or later with Reimburse.
        </div>
        <div class="list-group list-group-flush" style="max-height: 18rem; overflow-y: auto;">
            <?php foreach ($driver['trips'] as $trip_id => $trip) { ?>
                <label class="list-group-item d-flex align-items-center gap-3 mb-0" for="claim<?= $trip_id ?>">
                    <input class="form-check-input flex-shrink-0 mt-0" type="checkbox" name="trip_ids[]" value="<?= $trip_id ?>" id="claim<?= $trip_id ?>"
                        <?= !$picked || in_array($trip_id, $picked, true) ? 'checked' : '' ?> data-claim data-miles="<?= floatval($trip['trip_miles']) ?>">
                    <span class="flex-grow-1 min-w-0">
                        <span class="d-block text-truncate"><?= escapeHtml($trip['trip_destination']) ?> <span class="text-body-secondary">- <?= escapeHtml(truncate((string) $trip['trip_purpose'], 60)) ?></span></span>
                        <span class="small text-body-secondary"><?= escapeHtml(implode(' · ', array_filter([$trip['trip_date'], intval($trip['trip_client_id']) ? $trip['client_name'] : 'Our own', intval($trip['round_trip']) ? 'round trip' : '']))) ?></span>
                    </span>
                    <span class="text-nowrap fw-semibold"><?= escapeHtml(tripMilesText($trip['trip_miles'])) ?></span>
                </label>
            <?php } ?>
        </div>
        <div class="p-3 border-top">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Rate <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-dollar-sign"></i></span>
                        <input type="text" class="form-control text-end" name="rate" inputmode="decimal" value="<?= $rules['rate'] > 0 ? rtrim(rtrim(number_format($rules['rate'], 4, '.', ''), '0'), '.') : '' ?>" required data-claim-rate>
                        <span class="input-group-text">a <?= tripUnitOne() ?></span>
                    </div>
                    <div class="form-text">The rate for when these were driven<?= $session_is_admin ? ' - <a href="/admin/settings_invoice.php#mileage">the usual rate</a>' : '' ?>.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Date <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                        <input type="date" class="form-control" name="date" max="2999-12-31" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="form-text">Of the mileage expense - when it counts as spent.</div>
                </div>
                <div class="col-12">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" name="pay_now" value="1" id="tripClaimPayNow" data-claim-pay-now>
                        <label class="form-check-label" for="tripClaimPayNow">Pay it back now</label>
                    </div>
                </div>
                <div class="col-md-7" data-claim-pay hidden>
                    <label class="form-label">Paid back from <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-piggy-bank"></i></span>
                        <select class="form-select select2" name="account">
                            <option value="">- Which account -</option>
                            <?php foreach ($accounts as $account_id => $account) { ?>
                                <option value="<?= $account_id ?>" <?= $account_id === $default_account ? 'selected' : '' ?> data-icon="fas fa-piggy-bank"
                                    data-description="<?= escapeHtml(billingMoney($account['balance'], $account['account_currency_code'])) ?>"><?= escapeHtml($account['account_name']) ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-5" data-claim-pay hidden>
                    <label class="form-label">How</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-credit-card"></i></span>
                        <select class="form-select select2" name="payment_method">
                            <option value="">- How they were paid -</option>
                            <?php foreach ($methods as $method) { ?>
                                <option value="<?= escapeHtml($method) ?>"><?= escapeHtml($method) ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>
                <div class="col-12" data-claim-pay hidden>
                    <label class="form-label">Reference</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-hashtag"></i></span>
                        <input type="text" class="form-control" name="reference" maxlength="200" placeholder="Check or transfer number">
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="modal-footer">
        <span class="me-auto small text-body-secondary" data-claim-summary></span>
        <button type="submit" name="trip_claim" class="btn btn-primary text-bold" data-claim-submit><i class="fas fa-check me-2"></i><span data-claim-submit-label>Pay Mileage</span></button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<script>
(function () {
    var form = document.getElementById('tripClaim');
    if (!form || form.dataset.wired) {
        return;
    }
    form.dataset.wired = '1';
    var unit = <?= json_encode(tripUnit(true)) ?>;
    var summary = form.querySelector('[data-claim-summary]');
    var submit = form.querySelector('[data-claim-submit]');
    var submitLabel = form.querySelector('[data-claim-submit-label]');
    var rate = form.querySelector('[data-claim-rate]');
    var payNow = form.querySelector('[data-claim-pay-now]');
    function money(amount) {
        try {
            return new Intl.NumberFormat(<?= json_encode(str_replace('_', '-', (string) $session_company_locale)) ?>, { style: 'currency', currency: <?= json_encode($currency) ?> }).format(amount);
        } catch (e) {
            return amount.toFixed(2);
        }
    }
    function update() {
        var count = 0;
        var miles = 0;
        form.querySelectorAll('[data-claim]').forEach(function (box) {
            if (box.checked) {
                count++;
                miles += Number(box.getAttribute('data-miles'));
            }
        });
        miles = Math.round(miles * 10) / 10;
        var perUnit = Number(String(rate.value).replace(/[\s,$]/g, ''));
        var valid = rate.value.trim() !== '' && !isNaN(perUnit) && perUnit > 0;
        rate.classList.toggle('is-invalid', rate.value.trim() !== '' && !valid);
        summary.textContent = count ? count + ' trip' + (count === 1 ? '' : 's') + ' · ' + miles.toFixed(1) + ' ' + unit + (valid ? ' · ' + money(Math.round(miles * perUnit * 100) / 100) : '') : 'Tick the trips to pay for';
        submit.disabled = count === 0 || !valid;
        form.querySelectorAll('[data-claim-pay]').forEach(function (box) {
            box.hidden = !payNow.checked;
            box.querySelectorAll('select[name="account"]').forEach(function (select) {
                select.required = payNow.checked;
            });
        });
        submitLabel.textContent = payNow.checked ? 'Pay Mileage and Pay Back' : 'Pay Mileage';
    }
    form.addEventListener('change', update);
    form.addEventListener('input', update);
    update();
})();
</script>

<?php
require_once '../../../includes/modal_footer.php';
