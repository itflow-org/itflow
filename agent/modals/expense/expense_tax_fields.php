<?php

/*
 * The tax included in what an expense cost - its rate, and the amount,
 * worked out from the amount as it is typed until it is changed by hand to
 * what the receipt says. For expense_form.php and recurring_expense_form.php,
 * which set before including:
 *
 *   $tax_form_id   the form's root element id (the amount is its input[name=amount])
 *   $tax_selected  the rate picked (0 for none)
 *   $tax_amount    the tax saved, or null to work it out
 *   $tax_currency  the currency shown beside it
 *   $tax_lock      true when the amount can't change (cleared on a reconciled statement)
 *
 * Posts tax_id and tax_amount (expenseTaxFromInput()).
 */

isset($mysqli) || die("Direct file access is not allowed");

$tax_choices = billingTaxes();
?>
    <div class="col-md-7">
        <label class="form-label">Tax included</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-percent"></i></span>
            <select class="form-select" name="tax_id" data-expense-tax-rate>
                <?= taxOptionsHtml($tax_choices, intval($tax_selected)) ?>
            </select>
        </div>
    </div>

    <div class="col-md-5">
        <label class="form-label">Tax</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-balance-scale"></i></span>
            <input type="text" class="form-control text-end" inputmode="decimal" pattern="-?[0-9,]*\.?[0-9]{0,2}" name="tax_amount" placeholder="0.00"
                value="<?= $tax_amount === null || floatval($tax_amount) == 0 ? '' : number_format(abs(floatval($tax_amount)), 2, '.', '') ?>" data-expense-tax-amount <?= !empty($tax_lock) ? 'readonly' : '' ?>>
            <span class="input-group-text"><?= escapeHtml($tax_currency) ?></span>
        </div>
        <div class="form-text">Part of the amount - change it to what the receipt says.</div>
    </div>

<script>
(function () {
    // The tax follows the amount and rate until it is typed over
    var root = document.getElementById(<?= json_encode($tax_form_id) ?>);
    if (!root || root.dataset.taxWired) {
        return;
    }
    root.dataset.taxWired = '1';
    var amount = root.querySelector('input[name="amount"]');
    var rate = root.querySelector('[data-expense-tax-rate]');
    var tax = root.querySelector('[data-expense-tax-amount]');
    function number(value) {
        var n = Number(String(value || '').replace(/[\s,$]/g, ''));
        return isNaN(n) ? 0 : n;
    }
    // What the rate makes of the amount - as taxIncluded() does
    function worked() {
        var option = rate.options[rate.selectedIndex];
        var percent = option ? number(option.getAttribute('data-percent')) : 0;
        var gross = Math.abs(number(amount.value));
        if (!percent || !gross) {
            return '';
        }
        var net = Math.round(gross / (1 + percent / 100) * 100) / 100;
        return (Math.round((gross - net) * 100) / 100).toFixed(2);
    }
    // A saved tax that isn't what the rate makes of it was typed from a receipt
    var byHand = tax.value !== '' && tax.value !== worked();
    function update() {
        if (byHand || tax.readOnly) {
            return;
        }
        tax.value = worked();
    }
    tax.addEventListener('input', function () {
        byHand = tax.value.trim() !== '';
    });
    rate.addEventListener('change', function () {
        // A new rate works it out again
        byHand = false;
        update();
    });
    amount.addEventListener('input', update);
    if (tax.value === '') {
        update();
    }
})();
</script>
