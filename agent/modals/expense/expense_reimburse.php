<?php

/*
 * Reimburse - pay an agent back for what they paid personally. Their
 * expenses not paid back yet, ticked, with the account the money comes
 * from, the date, how and a reference; the total follows the ticks.
 * ?user_id= for one agent's, or ?expense_ids[]= from the list's bulk menu
 * (one agent's at a time). Posts expense_reimburse.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_financial', 2);

$user_id = intval($_GET['user_id'] ?? 0);
$picked = array_values(array_unique(array_filter(array_map('intval', (array) ($_GET['expense_ids'] ?? [])))));
if (!$user_id && $picked) {
    $agents = expenseToReimburse(0, $picked);
    if (count($agents) > 1) {
        exit(json_encode(['error' => 'Those were paid by ' . count($agents) . ' different agents - tick one agent\'s expenses at a time.']));
    }
    $user_id = $agents ? intval(array_key_first($agents)) : 0;
}
$agent = $user_id ? (expenseToReimburse($user_id)[$user_id] ?? null) : null;
if (!$agent) {
    exit(json_encode(['error' => $picked ? 'None of those are waiting to be paid back - they were paid from an account, or paid back already.' : 'Nothing is waiting to be paid back to them.']));
}

$accounts = paymentAccounts();
$methods = paymentMethods();
$default_account = intval($config_default_expense_account);
$currency = (string) $session_company_currency;

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title text-truncate"><i class="fas fa-fw fa-hand-holding-usd me-2"></i>Reimburse <span class="fw-normal text-white-50 ms-1"><?= escapeHtml($agent['user_name']) ?></span></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off" id="expenseReimburse">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="user_id" value="<?= $user_id ?>">

    <div class="modal-body p-0">
        <div class="px-3 py-2 small text-body-secondary border-bottom">
            What <?= escapeHtml($agent['user_name']) ?> paid for personally and hasn't been paid back for. They count as spent on their own dates; the money comes out of the account on the date it is paid back.
        </div>
        <div class="list-group list-group-flush" style="max-height: 20rem; overflow-y: auto;">
            <?php foreach ($agent['expenses'] as $expense_id => $expense) { ?>
                <label class="list-group-item d-flex align-items-center gap-3 mb-0" for="reimburse<?= $expense_id ?>">
                    <input class="form-check-input flex-shrink-0 mt-0" type="checkbox" name="expense_ids[]" value="<?= $expense_id ?>" id="reimburse<?= $expense_id ?>"
                        <?= !$picked || in_array($expense_id, $picked, true) ? 'checked' : '' ?> data-reimburse data-amount="<?= floatval($expense['expense_amount']) ?>">
                    <span class="flex-grow-1 min-w-0">
                        <span class="d-block text-truncate"><?= escapeHtml($expense['vendor_name']) ?><?= trim((string) $expense['expense_description']) !== '' ? ' <span class="text-body-secondary">- ' . escapeHtml(truncate(strtok((string) $expense['expense_description'], "\n"), 60)) . '</span>' : '' ?></span>
                        <span class="small text-body-secondary"><?= escapeHtml(implode(' · ', array_filter([$expense['expense_date'], $expense['category_name'], $expense['client_name']]))) ?></span>
                    </span>
                    <span class="itflow-money text-nowrap fw-semibold"><?= escapeHtml(billingMoney($expense['expense_amount'], $expense['expense_currency_code'])) ?></span>
                </label>
            <?php } ?>
        </div>
        <div class="p-3 border-top">
            <div class="row g-3">
                <div class="col-md-7">
                    <label class="form-label">Paid back from <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-piggy-bank"></i></span>
                        <select class="form-select select2" name="account" required>
                            <option value="">- Which account -</option>
                            <?php foreach ($accounts as $account_id => $account) { ?>
                                <option value="<?= $account_id ?>" <?= $account_id === $default_account ? 'selected' : '' ?> data-icon="fas fa-piggy-bank"
                                    data-description="<?= escapeHtml(billingMoney($account['balance'], $account['account_currency_code'])) ?>"><?= escapeHtml($account['account_name']) ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-5">
                    <label class="form-label">Date <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                        <input type="date" class="form-control" name="date" max="2999-12-31" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>
                <div class="col-md-7">
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
                <div class="col-md-5">
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
        <span class="me-auto small text-body-secondary" data-reimburse-summary></span>
        <button type="submit" name="expense_reimburse" class="btn btn-primary text-bold" data-reimburse-submit><i class="fas fa-check me-2"></i>Pay Back</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<script>
(function () {
    var form = document.getElementById('expenseReimburse');
    if (!form || form.dataset.wired) {
        return;
    }
    form.dataset.wired = '1';
    var summary = form.querySelector('[data-reimburse-summary]');
    var submit = form.querySelector('[data-reimburse-submit]');
    function money(amount) {
        try {
            return new Intl.NumberFormat(<?= json_encode(str_replace('_', '-', (string) $session_company_locale)) ?>, { style: 'currency', currency: <?= json_encode($currency) ?> }).format(amount);
        } catch (e) {
            return amount.toFixed(2);
        }
    }
    function update() {
        var count = 0;
        var total = 0;
        form.querySelectorAll('[data-reimburse]').forEach(function (box) {
            if (box.checked) {
                count++;
                total += Number(box.getAttribute('data-amount'));
            }
        });
        summary.textContent = count ? count + ' expense' + (count === 1 ? '' : 's') + ' · ' + money(Math.round(total * 100) / 100) : 'Tick what to pay back';
        submit.disabled = count === 0;
    }
    form.addEventListener('change', update);
    update();
})();
</script>

<?php
require_once '../../../includes/modal_footer.php';
