<?php

/*
 * Add stock to a product that tracks it - what came in, the expense it was
 * bought on, a note. ?id=
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
$on_hand = intval($product['product_stock']);
$vendor_id = intval($product['product_vendor_id']);

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

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-dolly me-2"></i>Add Stock <span class="fw-normal text-white-50 ms-1"><?= escapeHtml($product['product_name']) ?></span></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off" id="productStockAdd">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="product_id" value="<?= $product_id ?>">

    <div class="modal-body">
        <div class="d-flex align-items-center gap-3 mb-3 p-3 rounded bg-body-tertiary">
            <?= productTile('product') ?>
            <div class="small">
                <div><strong data-stock-now><?= $on_hand ?></strong> on the shelf<?= $product['product_location'] ? ' at ' . escapeHtml($product['product_location']) : '' ?></div>
                <div class="text-body-secondary" data-stock-after></div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-sm-5">
                <label class="form-label">Came in <strong class="text-danger">*</strong></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-fw fa-plus"></i></span>
                    <input type="number" class="form-control text-end" name="qty" min="1" step="1" inputmode="numeric" placeholder="Units" required autofocus data-stock-qty>
                </div>
            </div>
            <div class="col-12">
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
            <div class="col-12">
                <label class="form-label">Note</label>
                <div class="input-group">
                    <span class="input-group-text align-items-start pt-2"><i class="fas fa-fw fa-sticky-note"></i></span>
                    <textarea class="form-control" name="note" rows="2" placeholder="Order number, serials, who received it"></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="modal-footer">
        <button type="submit" name="add_product_stock" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Add Stock</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<script>
(function () {
    var form = document.getElementById('productStockAdd');
    if (!form || form.dataset.wired) {
        return;
    }
    form.dataset.wired = '1';
    var now = <?= $on_hand ?>;
    var qty = form.querySelector('[data-stock-qty]');
    var after = form.querySelector('[data-stock-after]');
    qty.addEventListener('input', function () {
        var n = parseInt(qty.value, 10);
        after.textContent = n > 0 ? (now + n) + ' once this is added' : '';
    });
})();
</script>

<?php
require_once '../../../includes/modal_footer.php';
