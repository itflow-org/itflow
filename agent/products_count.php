<?php

/*
 * Count Stock - every product that tracks its stock, with what ITFlow has on
 * the shelf and a box for what was counted. The difference shows as you
 * type, and saving records it for each product counted (blank ones are left
 * alone). ?location= narrows it to one place. Prints as a count sheet with
 * the boxes empty.
 */

require_once "includes/inc_all.php";

enforceUserPermission('module_sales', 2);

$location = (string) ($_GET['location'] ?? '');
$location_sql = $location !== '' ? "AND product_location = '" . escapeSql($location) . "'" : '';

$products = [];
$sql = mysqli_query($mysqli, "SELECT product_id, product_name, product_code, product_location, product_vendor_part, product_cost, product_currency_code,
        product_reorder_level, vendor_name, " . productStockSql() . " AS on_hand
    FROM products
    LEFT JOIN vendors ON vendor_id = product_vendor_id
    WHERE product_track_stock = 1 AND product_archived_at IS NULL $location_sql
    ORDER BY product_location IS NULL, product_location ASC, product_name ASC");
while ($row = mysqli_fetch_assoc($sql)) {
    $products[] = $row;
}
$locations = [];
$sql = mysqli_query($mysqli, "SELECT DISTINCT product_location FROM products WHERE product_track_stock = 1 AND product_archived_at IS NULL AND product_location IS NOT NULL AND product_location <> '' ORDER BY product_location");
while ($row = mysqli_fetch_row($sql)) {
    $locations[] = $row[0];
}

$page_title = 'Count Stock';

?>

<ol class="breadcrumb d-print-none">
    <li class="breadcrumb-item"><a href="products.php">Products</a></li>
    <li class="breadcrumb-item"><a href="products.php?type=stocked">Stocked</a></li>
    <li class="breadcrumb-item active">Count Stock</li>
</ol>

<form action="post.php" method="post" autocomplete="off" id="stockCount">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="card">
        <div class="card-header bg-dark py-2 d-flex align-items-center">
            <h3 class="card-title mt-1 mb-1 flex-grow-1"><i class="fas fa-fw fa-clipboard-check me-2"></i>Count Stock<?= $location !== '' ? ' <span class="fw-normal text-white-50">' . escapeHtml($location) . '</span>' : '' ?></h3>
            <div class="d-flex gap-2 d-print-none">
                <button type="button" class="btn btn-outline-light" onclick="window.print()"><i class="fas fa-print me-2"></i>Count Sheet</button>
            </div>
        </div>

        <div class="card-header py-3 d-print-none">
            <div class="row g-2 align-items-center">
                <div class="col-md-4">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-fw fa-map-marker-alt"></i></span>
                        <select class="form-select" onchange="location.href = 'products_count.php' + (this.value ? '?location=' + encodeURIComponent(this.value) : '')" aria-label="Location">
                            <option value="">Everywhere</option>
                            <?php foreach ($locations as $place) { ?>
                                <option value="<?= escapeHtml($place) ?>" <?= $location === $place ? 'selected' : '' ?>><?= escapeHtml($place) ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-8 small text-body-secondary">
                    Count what is on the shelf and type it in. Leave a product blank to skip it. Each one counted is recorded as a stock move.
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 itflow-stock-count">
                <thead class="text-nowrap">
                <tr>
                    <th>Product</th>
                    <th class="d-none d-md-table-cell">Location</th>
                    <th class="text-end">ITFlow has</th>
                    <th class="text-end" style="width: 9rem;">Counted</th>
                    <th class="text-end d-print-none" style="width: 7rem;">Difference</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($products as $row) {
                    $product_id = intval($row['product_id']);
                    $on_hand = floatval($row['on_hand']); ?>
                    <tr data-count-row data-on-hand="<?= $on_hand ?>">
                        <td>
                            <a class="fw-semibold" href="product.php?product_id=<?= $product_id ?>"><?= escapeHtml($row['product_name']) ?></a>
                            <div class="small text-body-secondary"><?= escapeHtml(implode(' · ', array_filter([$row['product_code'], $row['vendor_name'], $row['product_vendor_part']]))) ?: '&nbsp;' ?></div>
                        </td>
                        <td class="d-none d-md-table-cell"><?= $row['product_location'] ? escapeHtml($row['product_location']) : '<span class="text-body-secondary">-</span>' ?></td>
                        <td class="text-end"><?= escapeHtml(billingQuantity($on_hand)) ?></td>
                        <td class="text-end">
                            <input type="text" class="form-control form-control-sm text-end" name="counted[<?= $product_id ?>]" inputmode="decimal" aria-label="Counted <?= escapeHtml($row['product_name']) ?>" data-count-input>
                        </td>
                        <td class="text-end fw-semibold d-print-none" data-count-diff></td>
                    </tr>
                <?php } ?>
                <?php if (!$products) { ?>
                    <tr><td colspan="5" class="text-center text-body-secondary py-5">No product here tracks its stock. Turn on Track stock on a product to count it.</td></tr>
                <?php } ?>
                </tbody>
            </table>
        </div>

        <?php if ($products) { ?>
            <div class="card-footer d-flex flex-wrap gap-2 align-items-center d-print-none">
                <div class="input-group" style="max-width: 28rem;">
                    <span class="input-group-text"><i class="fas fa-fw fa-sticky-note"></i></span>
                    <input type="text" class="form-control" name="note" maxlength="200" placeholder="Note on every move - year end count, who counted">
                </div>
                <span class="small text-body-secondary ms-auto" data-count-summary>Nothing counted yet</span>
                <button type="submit" name="product_stock_count" class="btn btn-primary text-bold" data-count-save disabled><i class="fas fa-check me-2"></i>Save Count</button>
            </div>
        <?php } ?>
    </div>
</form>

<script>
(function () {
    var form = document.getElementById('stockCount');
    if (!form) {
        return;
    }
    var summary = form.querySelector('[data-count-summary]');
    var save = form.querySelector('[data-count-save]');
    function update() {
        var counted = 0, changed = 0, bad = 0;
        form.querySelectorAll('[data-count-row]').forEach(function (row) {
            var input = row.querySelector('[data-count-input]');
            var cell = row.querySelector('[data-count-diff]');
            var text = input.value.replace(/[\s,]/g, '');
            cell.className = 'text-end fw-semibold d-print-none';
            input.classList.remove('is-invalid');
            if (text === '') {
                cell.textContent = '';
                return;
            }
            var n = Number(text);
            if (isNaN(n) || n < 0) {
                input.classList.add('is-invalid');
                cell.textContent = '';
                bad++;
                return;
            }
            counted++;
            var diff = Math.round((n - Number(row.dataset.onHand)) * 100) / 100;
            if (diff !== 0) {
                changed++;
            }
            cell.textContent = diff === 0 ? 'Same' : (diff > 0 ? '+' : '') + diff;
            cell.classList.add(diff === 0 ? 'text-body-secondary' : (diff > 0 ? 'text-success-emphasis' : 'text-danger-emphasis'));
        });
        if (summary) {
            summary.textContent = counted ? counted + ' counted, ' + changed + ' different' + (bad ? ', ' + bad + ' not a number' : '') : 'Nothing counted yet';
        }
        if (save) {
            save.disabled = !counted || bad > 0;
        }
    }
    form.addEventListener('input', update);
    // Enter moves down to the next box rather than saving
    form.querySelectorAll('[data-count-input]').forEach(function (input, i, all) {
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                if (all[i + 1]) {
                    all[i + 1].focus();
                }
            }
        });
    });
    update();
})();
</script>

<?php
require_once "../includes/footer.php";
