<?php

/*
 * Stock for a product that tracks it. ?id= and ?mode= picks where it opens:
 *   receive    it came in - how many, at what unit cost, on which expense
 *   count      what is on the shelf now - the difference is recorded
 *   write_off  damaged, lost, used by us or sent back
 * Posts product_stock_save.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_sales', 2);

$product = productRow(intval($_GET['id'] ?? 0));
if (!$product) {
    exit(json_encode(['error' => 'That product no longer exists.']));
}
if (!intval($product['product_track_stock'])) {
    exit(json_encode(['error' => escapeHtml($product['product_name']) . " doesn't track stock. Turn on Track stock in Edit to count it."]));
}
$product_id = intval($product['product_id']);
$on_hand = floatval($product['product_stock']);
$vendor_id = intval($product['product_vendor_id']);
$mode = in_array($_GET['mode'] ?? '', ['receive', 'count', 'write_off'], true) ? $_GET['mode'] : 'receive';
$currency = (string) $product['product_currency_code'];
$cost = floatval($product['product_cost']) > 0 ? number_format(floatval($product['product_cost']), 2, '.', '') : '';
$level = floatval($product['product_reorder_level']);

// Recent expenses to link - the product's vendor's first
$expenses = [];
$sql = mysqli_query($mysqli, "SELECT expense_id, expense_description, expense_date, expense_amount, expense_currency_code, expense_vendor_id, vendor_name
    FROM expenses
    LEFT JOIN vendors ON vendor_id = expense_vendor_id
    WHERE expense_archived_at IS NULL AND expense_date >= CURDATE() - INTERVAL 1 YEAR
    ORDER BY (expense_vendor_id = $vendor_id AND $vendor_id > 0) DESC, expense_date DESC, expense_id DESC
    LIMIT 150");
while ($row = mysqli_fetch_assoc($sql)) {
    $expenses[] = $row;
}

$modes = [
    'receive' => ['label' => 'Receive', 'icon' => 'fa-dolly', 'note' => 'It came in'],
    'count' => ['label' => 'Count', 'icon' => 'fa-clipboard-check', 'note' => 'What is on the shelf'],
    'write_off' => ['label' => 'Write off', 'icon' => 'fa-minus-circle', 'note' => 'Damaged, lost, used'],
];

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-warehouse me-2"></i>Stock <span class="fw-normal text-white-50 ms-1"><?= escapeHtml($product['product_name']) ?></span></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off" id="productStock<?= $product_id ?>" data-on-hand="<?= $on_hand ?>" data-level="<?= $level ?>">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="product_id" value="<?= $product_id ?>">

    <div class="modal-body">
        <div class="d-flex align-items-center gap-3 mb-3 p-3 rounded bg-body-tertiary">
            <?= productTile('product') ?>
            <div class="small flex-grow-1">
                <div><strong><?= escapeHtml(billingQuantity($on_hand)) ?></strong> on the shelf<?= $product['product_location'] ? ' at ' . escapeHtml($product['product_location']) : '' ?><?= $level > 0 ? ' &middot; reorder at ' . escapeHtml(billingQuantity($level)) : '' ?></div>
                <div class="text-body-secondary" data-stock-after>&nbsp;</div>
            </div>
        </div>

        <div class="btn-group w-100 itflow-product-type mb-3" role="group" aria-label="What happened">
            <?php foreach ($modes as $mode_key => $m) { ?>
                <input type="radio" class="btn-check" name="mode" value="<?= $mode_key ?>" id="productStock<?= $product_id . $mode_key ?>" autocomplete="off" <?= $mode === $mode_key ? 'checked' : '' ?> data-stock-mode>
                <label class="btn btn-outline-secondary text-start d-flex align-items-center gap-2" for="productStock<?= $product_id . $mode_key ?>">
                    <i class="fas fa-fw <?= $m['icon'] ?> fa-lg"></i>
                    <span>
                        <span class="fw-semibold d-block"><?= $m['label'] ?></span>
                        <span class="small opacity-75 d-none d-sm-block"><?= $m['note'] ?></span>
                    </span>
                </label>
            <?php } ?>
        </div>

        <div class="row g-3">
            <!-- Receive -->
            <div class="col-sm-5" data-stock-for="receive">
                <label class="form-label">Came in <strong class="text-danger">*</strong></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-fw fa-plus"></i></span>
                    <input type="text" class="form-control text-end" name="qty" inputmode="decimal" placeholder="How many" required data-stock-qty>
                </div>
            </div>
            <div class="col-sm-7" data-stock-for="receive">
                <label class="form-label">Unit cost</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-fw fa-shopping-cart"></i></span>
                    <input type="text" class="form-control text-end" name="unit_cost" inputmode="decimal" value="<?= $cost ?>" placeholder="0.00" data-stock-cost>
                </div>
            </div>
            <div class="col-12" data-stock-for="receive">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" name="update_cost" value="1" id="productStockCost<?= $product_id ?>" data-stock-update-cost>
                    <label class="form-check-label" for="productStockCost<?= $product_id ?>">Make this the product's cost</label>
                    <div class="form-text">Its cost is <?= $cost !== '' ? escapeHtml(billingMoney($cost, $currency)) : 'not set' ?> - margins and the stock's value use it.</div>
                </div>
            </div>
            <div class="col-12" data-stock-for="receive">
                <label class="form-label">Bought on</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-fw fa-receipt"></i></span>
                    <select class="form-select select2" name="expense">
                        <option value="0">- An expense from the last year -</option>
                        <?php foreach ($expenses as $expense) { ?>
                            <option value="<?= intval($expense['expense_id']) ?>"><?= escapeHtml($expense['expense_date'] . ' - ' . ($expense['vendor_name'] ? $expense['vendor_name'] . ' - ' : '') . mb_strimwidth((string) $expense['expense_description'], 0, 60, '...') . ' - ' . billingMoney($expense['expense_amount'], $expense['expense_currency_code'])) ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>

            <!-- Count -->
            <div class="col-sm-6" data-stock-for="count">
                <label class="form-label">On the shelf <strong class="text-danger">*</strong></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-fw fa-clipboard-check"></i></span>
                    <input type="text" class="form-control text-end" name="counted" inputmode="decimal" placeholder="What you counted" required data-stock-counted>
                </div>
            </div>

            <!-- Write off -->
            <div class="col-sm-5" data-stock-for="write_off">
                <label class="form-label">How many <strong class="text-danger">*</strong></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-fw fa-minus"></i></span>
                    <input type="text" class="form-control text-end" name="qty" inputmode="decimal" placeholder="Off the shelf" required data-stock-qty>
                </div>
            </div>
            <div class="col-sm-7" data-stock-for="write_off">
                <label class="form-label">Why</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-fw fa-question-circle"></i></span>
                    <select class="form-select" name="reason">
                        <?php foreach (productWriteOffReasons() as $reason) { ?>
                            <option><?= escapeHtml($reason) ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>

            <div class="col-12">
                <label class="form-label">Note</label>
                <div class="input-group">
                    <span class="input-group-text align-items-start pt-2"><i class="fas fa-fw fa-sticky-note"></i></span>
                    <textarea class="form-control" name="note" rows="2" placeholder="Order number, serials, who - kept with the move"></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="modal-footer">
        <button type="submit" name="product_stock_save" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i><span data-stock-submit>Save</span></button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<script>
(function () {
    var form = document.getElementById('productStock<?= $product_id ?>');
    if (!form || form.dataset.wired) {
        return;
    }
    form.dataset.wired = '1';
    var onHand = Number(form.dataset.onHand);
    var level = Number(form.dataset.level);
    var after = form.querySelector('[data-stock-after]');
    var submit = form.querySelector('[data-stock-submit]');
    var labels = { receive: 'Receive', count: 'Save Count', write_off: 'Write Off' };

    function number(input) {
        var text = String(input ? input.value : '').replace(/[\s,]/g, '');
        return text === '' ? NaN : Number(text);
    }
    function fmt(n) {
        return String(Math.round(n * 100) / 100);
    }
    function update() {
        var mode = (form.querySelector('[data-stock-mode]:checked') || {}).value;
        form.querySelectorAll('[data-stock-for]').forEach(function (section) {
            var on = section.dataset.stockFor === mode;
            section.hidden = !on;
            section.querySelectorAll('input, select, textarea').forEach(function (input) {
                input.disabled = !on;
            });
        });
        submit.textContent = labels[mode] || 'Save';
        var text = '';
        var section = form.querySelector('[data-stock-for="' + mode + '"]');
        if (mode === 'receive' || mode === 'write_off') {
            var q = number(section.querySelector('[data-stock-qty]'));
            if (q > 0) {
                var next = mode === 'receive' ? onHand + q : onHand - q;
                text = next < 0 ? 'Only ' + fmt(onHand) + ' on the shelf' : fmt(next) + ' on the shelf after this';
            }
        } else if (mode === 'count') {
            var c = number(form.querySelector('[data-stock-counted]'));
            if (!isNaN(c) && c >= 0) {
                var diff = Math.round((c - onHand) * 100) / 100;
                text = diff === 0 ? 'The same as ITFlow has' : (diff > 0 ? '+' : '') + fmt(diff) + ' from what ITFlow has';
            }
        }
        if (text && level > 0 && mode !== 'receive') {
            var left = mode === 'count' ? number(form.querySelector('[data-stock-counted]')) : onHand - number(section.querySelector('[data-stock-qty]'));
            if (left <= level) {
                text += ' - at or under the reorder level';
            }
        }
        after.innerHTML = text ? text.replace(/&/g, '&amp;').replace(/</g, '&lt;') : '&nbsp;';
    }
    form.addEventListener('input', update);
    form.addEventListener('change', update);
    update();
})();
</script>

<?php
require_once '../../../includes/modal_footer.php';
