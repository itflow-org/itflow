<?php

/*
 * Add Expenses to an invoice - the client's billable expenses that aren't
 * on an invoice yet, each at its cost with its markup, the price ready to
 * change, the margin following along. Each goes on as a line of its own.
 * ?invoice_id=
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_sales', 2);
enforceUserPermission('module_financial', 2);

$invoice_id = intval($_GET['invoice_id'] ?? 0);
$invoice = billingRow('invoice', $invoice_id);
if (!$invoice) {
    exit(json_encode(['error' => 'That invoice no longer exists.']));
}
$client_id = intval($invoice['client_id']);
enforceClientAccess();
if (!billingCanEditItems('invoice', $invoice)) {
    exit(json_encode(['error' => billingNumber('invoice', $invoice) . " can't take more lines - it is paid, cancelled or non-billable."]));
}

$expenses = expenseUnbilled($client_id);
$currency = (string) $invoice['invoice_currency_code'];
$taxes = billingTaxes();
$markup_text = function ($markup) {
    return rtrim(rtrim(number_format(floatval($markup), 2, '.', ''), '0'), '.') . '%';
};

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-shopping-cart me-2"></i>Add Expenses <span class="fw-normal text-white-50 ms-1"><?= escapeHtml(billingNumber('invoice', $invoice)) ?></span></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off" id="invoiceAddExpenses">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="invoice_id" value="<?= $invoice_id ?>">

    <div class="modal-body p-0">
        <?php if ($expenses) { ?>
            <div class="px-3 py-2 small text-body-secondary border-bottom">
                What we paid for <?= escapeHtml($invoice['client_name']) ?> to bill on to them, at its cost (less any refund) with its markup. Change a price before adding - each goes on as a line of its own.
            </div>
            <div class="list-group list-group-flush" style="max-height: 22rem; overflow-y: auto;">
                <?php foreach ($expenses as $expense_id => $expense) {
                    [$line_name] = expenseBillLine($expense); ?>
                    <div class="list-group-item d-flex align-items-center gap-3" data-add-expense-row data-cost="<?= $expense['cost'] ?>">
                        <input class="form-check-input flex-shrink-0 mt-0" type="checkbox" name="expense_ids[]" value="<?= $expense_id ?>" id="addExpense<?= $expense_id ?>" checked data-add-expense>
                        <label class="flex-grow-1 min-w-0 mb-0" for="addExpense<?= $expense_id ?>">
                            <span class="d-block text-truncate"><?= escapeHtml($line_name) ?></span>
                            <span class="small text-body-secondary d-block text-truncate">
                                <?= escapeHtml($expense['vendor_name']) ?> &middot; <?= escapeHtml($expense['expense_date']) ?>
                                &middot; cost <?= escapeHtml(billingMoney($expense['cost'], $currency)) ?> + <?= $markup_text($expense['expense_markup']) ?>
                            </span>
                        </label>
                        <div class="text-end flex-shrink-0" style="width: 8rem;">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text"><i class="fa fa-fw fa-dollar-sign"></i></span>
                                <input type="text" class="form-control text-end" name="expense_price[<?= $expense_id ?>]" value="<?= number_format($expense['price'], 2, '.', '') ?>" inputmode="decimal" aria-label="Price to bill" data-add-expense-price>
                            </div>
                            <div class="small text-body-secondary mt-1" data-add-expense-margin></div>
                        </div>
                    </div>
                <?php } ?>
            </div>
            <div class="p-3 border-top">
                <div class="row g-3">
                    <div class="col-sm-6">
                        <label class="form-label">Tax</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-percent"></i></span>
                            <select class="form-select" name="tax_id" data-add-expense-tax>
                                <option value="0" data-percent="0">No tax</option>
                                <?php foreach ($taxes as $tax_id => $tax) { ?>
                                    <option value="<?= intval($tax_id) ?>" data-percent="<?= floatval($tax['tax_percent']) ?>"><?= escapeHtml($tax['tax_name'] . ' ' . billingQuantity($tax['tax_percent']) . '%') ?></option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        <?php } else { ?>
            <div class="itflow-asset-empty">
                <i class="fas fa-shopping-cart fa-2x mb-2 opacity-50"></i>
                <div>No expenses to bill - <?= escapeHtml($invoice['client_name']) ?>'s billable expenses are all on an invoice.</div>
                <div class="small mt-1">Mark one billable from its Edit window, or several at once with Bulk Action &rsaquo; Set Billable on <a href="expenses.php?client=<?= $client_id ?>">their expenses</a>.</div>
            </div>
        <?php } ?>
    </div>
    <div class="modal-footer">
        <?php if ($expenses) { ?>
            <span class="me-auto small text-body-secondary" data-add-expense-summary></span>
            <button type="submit" name="invoice_add_expenses" class="btn btn-primary text-bold" data-add-expense-submit><i class="fas fa-check me-2"></i>Add</button>
        <?php } ?>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i><?= $expenses ? 'Cancel' : 'Close' ?></button>
    </div>
</form>

<script>
(function () {
    var form = document.getElementById('invoiceAddExpenses');
    if (!form || form.dataset.wired || !form.querySelector('[data-add-expense]')) {
        return;
    }
    form.dataset.wired = '1';
    var currency = <?= json_encode($currency) ?>;
    var tax = form.querySelector('[data-add-expense-tax]');
    var summary = form.querySelector('[data-add-expense-summary]');
    var submit = form.querySelector('[data-add-expense-submit]');
    function number(value) {
        var n = Number(String(value || '').replace(/[\s,$]/g, ''));
        return isNaN(n) ? 0 : n;
    }
    function money(amount) {
        try {
            return new Intl.NumberFormat(undefined, { style: 'currency', currency: currency || 'USD' }).format(amount);
        } catch (e) {
            return amount.toFixed(2);
        }
    }
    function update() {
        var count = 0;
        var price = 0;
        var cost = 0;
        form.querySelectorAll('[data-add-expense-row]').forEach(function (row) {
            var box = row.querySelector('[data-add-expense]');
            var input = row.querySelector('[data-add-expense-price]');
            var margin = row.querySelector('[data-add-expense-margin]');
            var p = number(input.value);
            var c = Number(row.getAttribute('data-cost'));
            input.disabled = !box.checked;
            margin.textContent = p > 0 ? Math.round((p - c) / p * 100) + '% margin' : 'no charge';
            if (box.checked) {
                count++;
                price += p;
                cost += c;
            }
        });
        var option = tax.options[tax.selectedIndex];
        price = Math.round(price * 100) / 100;
        var total = price + Math.round(price * Number(option ? option.getAttribute('data-percent') || 0 : 0)) / 100;
        summary.textContent = count ? count + ' expense' + (count === 1 ? '' : 's') + ' · ' + money(total) + (price > 0 ? ' · ' + Math.round((price - cost) / price * 100) + '% margin' : '') : 'Tick the expenses to add';
        submit.disabled = count === 0;
    }
    form.addEventListener('change', update);
    form.addEventListener('input', update);
    update();
})();
</script>

<?php
require_once '../../../includes/modal_footer.php';
