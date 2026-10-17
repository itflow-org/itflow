<?php

/*
 * The fields of a transfer between our accounts - shared by New Transfer and
 * Edit. Set before including:
 *
 *   $form_row   transferRow() for edit; null for new
 *   $form_from  the account a new one starts from (0 for the default)
 *   $form_to    the account a new one goes to (0 for the default)
 *
 * Posts date, amount, account_from, account_to, transfer_method and notes
 * (agent/post/transfer.php). The amount shows the currency of the account
 * it leaves; Swap turns it round, and a credit card as where it goes says
 * it is paying the card.
 *
 * Should not be accessed directly.
 */

isset($mysqli) || die("Direct file access is not allowed");

$form_id = 'transferForm' . ($form_row ? intval($form_row['transfer_id']) : 'New');
$form_from = $form_row ? intval($form_row['transfer_from_id']) : (intval($form_from ?? 0) ?: intval($config_default_transfer_from_account));
$form_to = $form_row ? intval($form_row['transfer_to_id']) : (intval($form_to ?? 0) ?: intval($config_default_transfer_to_account));
if (!$form_row && $form_from === $form_to) {
    // Pay the Card on the default account, or Transfer from it: keep the account picked, not the default
    if (intval($_GET['to'] ?? 0)) {
        $form_from = 0;
    } else {
        $form_to = 0;
    }
}
$form_accounts = accountsList(false);
foreach ([$form_from, $form_to] as $keep) {
    if ($keep && !isset($form_accounts[$keep]) && ($row = accountRow($keep))) {
        $form_accounts[$keep] = $row;
    }
}
$form_method = $form_row ? (string) $form_row['transfer_method'] : '';
$form_methods = paymentMethods();
if ($form_method !== '' && !in_array($form_method, $form_methods, true)) {
    $form_methods[] = $form_method;
}
$form_types = accountTypes();
// Either side cleared on a reconciled statement - the date, amount and that side's account stay
$form_lock_out = $form_row ? reconcileLock('expense', $form_row['transfer_expense_id']) : null;
$form_lock_in = $form_row ? reconcileLock('revenue', $form_row['transfer_revenue_id']) : null;
$form_lock = $form_lock_out ?: $form_lock_in;
// A new transfer out of a cash account can carry the checks and cash waiting in it to the bank
$form_deposits = [];
if (!$form_row) {
    foreach ($form_accounts as $id => $account) {
        if ($account['type'] !== 4 || $account['account_archived_at']) {
            continue;
        }
        foreach (transferDepositCandidates($id) as $item) {
            $form_deposits[$id][] = ['key' => $item['key'], 'date' => $item['date'], 'what' => $item['what'], 'client' => (string) $item['client_name'],
                'method' => trim((string) $item['method'] . ((string) $item['reference'] !== '' ? ' #' . $item['reference'] : '')),
                'amount' => number_format($item['amount'], 2, '.', ''), 'money' => billingMoney($item['amount'], $account['account_currency_code'])];
        }
    }
}
$form_currency = isset($form_accounts[$form_from]) ? ($form_accounts[$form_from]['account_currency_code'] ?: $session_company_currency) : $session_company_currency;
$form_account_options = function ($selected) use ($form_accounts, $form_types) {
    $html = '';
    foreach ($form_accounts as $id => $account) {
        $html .= '<option value="' . $id . '"' . ($selected === $id ? ' selected' : '') . ' data-icon="fas ' . $form_types[$account['type']]['icon'] . '" data-type="' . $account['type'] . '"'
            . ' data-currency="' . escapeHtml($account['account_currency_code']) . '" data-description="' . escapeHtml(accountBalanceText($account) . ($account['account_archived_at'] ? ' - archived' : '')) . '">'
            . escapeHtml($account['account_name']) . '</option>';
    }
    return $html;
};

?>
<div class="row g-3" id="<?= $form_id ?>">
    <?= reconcileFormNoteHtml($form_lock) ?>

    <div class="col-md-6">
        <label class="form-label">From <strong class="text-danger">*</strong></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-sign-out-alt"></i></span>
            <select class="form-select select2" name="account_from" required data-transfer-from <?= $form_lock_out ? 'disabled' : '' ?>>
                <option value="">- Out of which account -</option>
                <?= $form_account_options($form_from) ?>
            </select>
            <?php if ($form_lock_out) { ?>
                <input type="hidden" name="account_from" value="<?= $form_from ?>">
            <?php } ?>
        </div>
    </div>

    <div class="col-md-6">
        <label class="form-label d-flex align-items-center">To <strong class="text-danger ms-1">*</strong>
            <button type="button" class="btn btn-link btn-sm p-0 ms-auto text-decoration-none" data-transfer-swap title="Turn it round" <?= $form_lock ? 'hidden' : '' ?>><i class="fas fa-exchange-alt me-1"></i>Swap</button>
        </label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-sign-in-alt"></i></span>
            <select class="form-select select2" name="account_to" required data-transfer-to <?= $form_lock_in ? 'disabled' : '' ?>>
                <option value="">- Into which account -</option>
                <?= $form_account_options($form_to) ?>
            </select>
            <?php if ($form_lock_in) { ?>
                <input type="hidden" name="account_to" value="<?= $form_to ?>">
            <?php } ?>
        </div>
        <div class="form-text" data-transfer-card hidden>Paying off the card - what is owed on it goes down.</div>
    </div>

    <?php if ($form_deposits) { ?>
        <div class="col-12" data-transfer-deposit data-deposits="<?= escapeHtml(json_encode($form_deposits)) ?>" hidden>
            <label class="form-label">In this deposit</label>
            <div class="list-group itflow-deposit-list" data-transfer-deposit-list></div>
            <div class="form-text">Tick the checks and cash going to the bank - the amount and notes fill in, and they won't be offered again.</div>
        </div>
    <?php } ?>

    <div class="col-md-6">
        <label class="form-label">Amount <strong class="text-danger">*</strong></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-dollar-sign"></i></span>
            <input type="text" class="form-control text-end fw-semibold" inputmode="decimal" pattern="[0-9,]*\.?[0-9]{0,2}" name="amount" placeholder="0.00" required
                value="<?= $form_row ? number_format(floatval($form_row['transfer_amount']), 2, '.', '') : '' ?>" <?= $form_row ? '' : 'autofocus' ?> <?= $form_lock ? 'readonly' : '' ?>>
            <span class="input-group-text" data-transfer-currency><?= escapeHtml($form_currency) ?></span>
        </div>
    </div>

    <div class="col-md-6">
        <label class="form-label">Date <strong class="text-danger">*</strong></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
            <input type="date" class="form-control" name="date" max="2999-12-31" value="<?= escapeHtml($form_row ? $form_row['transfer_date'] : date('Y-m-d')) ?>" required <?= $form_lock ? 'readonly' : '' ?>>
        </div>
    </div>

    <div class="col-md-6">
        <label class="form-label">Moved by</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-money-check-alt"></i></span>
            <select class="form-select select2" name="transfer_method">
                <option value="">- How it was moved -</option>
                <?php foreach ($form_methods as $method) { ?>
                    <option value="<?= escapeHtml($method) ?>" <?= $form_method === $method ? 'selected' : '' ?>><?= escapeHtml($method) ?></option>
                <?php } ?>
            </select>
        </div>
    </div>

    <div class="col-12">
        <label class="form-label">Notes</label>
        <div class="input-group">
            <span class="input-group-text align-items-start pt-2"><i class="fa fa-fw fa-align-left"></i></span>
            <textarea class="form-control" rows="3" name="notes" placeholder="What it was for - the card's October statement, the quarter's tax reserve, a Stripe payout"><?= escapeHtml($form_row['transfer_notes'] ?? '') ?></textarea>
        </div>
    </div>

</div>

<script>
(function () {
    // The currency is the account it leaves; Swap turns it round; into a card is paying it
    var root = document.getElementById(<?= json_encode($form_id) ?>);
    if (!root || root.dataset.wired) {
        return;
    }
    root.dataset.wired = '1';
    var from = root.querySelector('[data-transfer-from]');
    var to = root.querySelector('[data-transfer-to]');
    var currency = root.querySelector('[data-transfer-currency]');
    var card = root.querySelector('[data-transfer-card]');
    function picked(select) {
        return select.options[select.selectedIndex] || null;
    }
    function update() {
        var option = picked(from);
        currency.textContent = option && option.dataset.currency ? option.dataset.currency : <?= json_encode((string) $session_company_currency) ?>;
        var into = picked(to);
        card.hidden = !(into && into.dataset.type === '3');
        to.setCustomValidity(from.value !== '' && from.value === to.value ? 'Pick two different accounts' : '');
    }
    root.querySelector('[data-transfer-swap]').addEventListener('click', function () {
        var was = from.value;
        from.value = to.value;
        to.value = was;
        from.dispatchEvent(new Event('change', { bubbles: true }));
        to.dispatchEvent(new Event('change', { bubbles: true }));
    });
    from.addEventListener('change', update);
    to.addEventListener('change', update);
    update();

    // A deposit out of a cash account: tick what goes to the bank, and the amount and notes follow
    var deposit = root.querySelector('[data-transfer-deposit]');
    if (!deposit) {
        return;
    }
    var waiting = JSON.parse(deposit.dataset.deposits || '{}');
    var list = deposit.querySelector('[data-transfer-deposit-list]');
    var amount = root.querySelector('input[name="amount"]');
    var notes = root.querySelector('textarea[name="notes"]');
    var filledAmount = '';
    var filledNotes = '';
    function line(item) {
        return [item.what, item.client, item.method, item.money].filter(function (part) { return part; }).join(' - ');
    }
    function fill() {
        var ticked = [];
        list.querySelectorAll('input:checked').forEach(function (box) {
            ticked.push(box.item);
        });
        var cents = ticked.reduce(function (sum, item) { return sum + Math.round(parseFloat(item.amount) * 100); }, 0);
        var total = ticked.length ? (cents / 100).toFixed(2) : '';
        if (amount.value === '' || amount.value === filledAmount) {
            amount.value = total;
            filledAmount = total;
        }
        var text = ticked.length ? 'Deposit of ' + ticked.length + (ticked.length === 1 ? ' item' : ' items') + ':\n' + ticked.map(line).join('\n') : '';
        if (notes.value === '' || notes.value === filledNotes) {
            notes.value = text;
            filledNotes = text;
        }
    }
    function show() {
        var items = waiting[from.value] || [];
        list.textContent = '';
        items.forEach(function (item) {
            var row = document.createElement('label');
            row.className = 'list-group-item d-flex align-items-center gap-2';
            var box = document.createElement('input');
            box.type = 'checkbox';
            box.className = 'form-check-input mt-0';
            box.name = 'deposit[]';
            box.value = item.key;
            box.item = item;
            var date = document.createElement('span');
            date.className = 'text-body-secondary small text-nowrap';
            date.textContent = item.date;
            var what = document.createElement('span');
            what.className = 'text-truncate';
            what.textContent = [item.what, item.client].filter(function (part) { return part; }).join(' - ');
            if (item.method) {
                var method = document.createElement('span');
                method.className = 'text-body-secondary small ms-1';
                method.textContent = item.method;
                what.appendChild(method);
            }
            var money = document.createElement('span');
            money.className = 'ms-auto fw-semibold itflow-money text-nowrap';
            money.textContent = item.money;
            row.append(box, date, what, money);
            list.appendChild(row);
        });
        deposit.hidden = items.length === 0;
        fill();
    }
    list.addEventListener('change', fill);
    from.addEventListener('change', show);
    show();
})();
</script>
