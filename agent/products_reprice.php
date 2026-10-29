<?php

/*
 * Update Prices - lines from the catalog on recurring invoices, draft
 * invoices and draft quotes whose price isn't the product's price now.
 *
 * Grouped by product, with its price and its last change. A line still at
 * one of the product's old prices is picked, ready to move to today's price.
 * A price the product never had looks agreed with the client - it is marked
 * as its own, and left alone unless picked, with the last change applied to
 * it as the suggestion. Each new price can be changed before saving; the
 * footer says what the picked ones add a month on recurring invoices.
 *
 * ?product_id=, ?client_id=, ?kind= and ?doc_id= narrow it - the product's
 * page, a client and one document link here. Posts products_reprice_save.
 */

require_once "includes/inc_all.php";

enforceUserPermission('module_sales');

$filters = [
    'product_id' => intval($_GET['product_id'] ?? 0),
    'client_id' => intval($_GET['client_id'] ?? 0),
    'kind' => isset(productRepriceKinds()[$_GET['kind'] ?? '']) ? $_GET['kind'] : '',
    'doc_id' => intval($_GET['doc_id'] ?? 0),
];
$groups = productRepriceLines($filters);
$kinds = productRepriceKinds();
$can_edit = lookupUserPermission('module_sales') >= 2;
$line_count = array_sum(array_map(function ($group) {
    return count($group['lines']);
}, $groups));
$period_words = ['month' => 'a month', 'quarter' => 'a quarter', 'year' => 'a year'];
$doc_pages = ['recurring' => 'recurring_invoice.php?recurring_invoice_id=', 'invoice' => 'invoice.php?invoice_id=', 'quote' => 'quote.php?quote_id='];

// What it is narrowed to, each with a link that takes it off
$narrowed = [];
$clear_url = function ($keys) use ($filters) {
    $params = array_filter(array_diff_key($filters, array_flip($keys)));
    return 'products_reprice.php' . ($params ? '?' . http_build_query($params) : '');
};
if ($filters['product_id']) {
    $narrowed[] = ['Product', getFieldById('products', $filters['product_id'], 'product_name') ?: 'a product', $clear_url(['product_id'])];
}
if ($filters['client_id']) {
    $narrowed[] = ['Client', getFieldById('clients', $filters['client_id'], 'client_name') ?: 'a client', $clear_url(['client_id'])];
}
if ($filters['kind']) {
    $doc = $filters['doc_id'] ? billingRow($filters['kind'], $filters['doc_id']) : null;
    $narrowed[] = [$kinds[$filters['kind']]['one'], $doc ? billingNumber($filters['kind'], $doc) : $kinds[$filters['kind']]['label'], $clear_url(['kind', 'doc_id'])];
}

$page_title = 'Update Prices';

?>

<ol class="breadcrumb d-print-none">
    <li class="breadcrumb-item"><a href="products.php">Products</a></li>
    <?php if ($filters['product_id'] && count($groups) <= 1) { ?>
        <li class="breadcrumb-item"><a href="product.php?product_id=<?= $filters['product_id'] ?>"><?= escapeHtml((string) getFieldById('products', $filters['product_id'], 'product_name')) ?></a></li>
    <?php } ?>
    <li class="breadcrumb-item active">Update Prices</li>
</ol>

<form action="post.php" method="post" autocomplete="off" id="productsReprice">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="card">
        <div class="card-header bg-dark py-2">
            <h3 class="card-title mt-1 mb-1"><i class="fas fa-fw fa-tags me-2"></i>Update Prices</h3>
        </div>

        <div class="card-header py-3">
            <p class="small text-body-secondary mb-0">
                Catalog lines on recurring invoices, draft invoices and draft quotes whose price isn't the product's price now.
                Lines still at one of its old prices are picked. A price it never had looks agreed with the client, so it is left alone unless you pick it.
                Invoices already sent, and quotes out with clients, keep their prices.
            </p>
            <?php if ($narrowed) { ?>
                <div class="small text-body-secondary mt-2 d-flex flex-wrap column-gap-3 row-gap-1">
                    <?php foreach ($narrowed as [$what, $name, $url]) { ?>
                        <span><i class="fas fa-filter me-1"></i><?= escapeHtml($what) ?>: <strong><?= escapeHtml($name) ?></strong> <a class="ms-1" href="<?= escapeHtml($url) ?>">Clear</a></span>
                    <?php } ?>
                </div>
            <?php } ?>
        </div>

        <?php foreach ($groups as $group) {
            $product_id = intval($group['product_id']);
            $currency = (string) $group['product_currency_code'];
            $last = $group['last'];
            $ratio = $last && floatval($last['price_old']) > 0 ? floatval($last['price_new']) / floatval($last['price_old']) : 0;
            $percent = $ratio ? round(($ratio - 1) * 100, 1) : 0; ?>
            <section class="itflow-reprice-group" data-reprice-group>
                <div class="d-flex flex-wrap align-items-center gap-2 px-3 py-2 bg-body-tertiary border-top border-bottom">
                    <?= productTile($group['product_type'], 'sm') ?>
                    <div class="min-w-0 flex-grow-1">
                        <a class="fw-semibold" href="product.php?product_id=<?= $product_id ?>"><?= escapeHtml($group['product_name']) ?></a>
                        <div class="small text-body-secondary">
                            Now <strong class="text-body"><?= escapeHtml(billingMoney($group['product_price'], $currency)) ?></strong>
                            <?php if ($last) { ?>
                                &middot; was <?= escapeHtml(billingMoney($last['price_old'], $currency)) ?> until <?= escapeHtml(date('M j, Y', strtotime($last['price_created_at']))) ?>
                                (<?= $percent > 0 ? '+' : '' ?><?= escapeHtml((string) $percent) ?>%)
                            <?php } ?>
                        </div>
                    </div>
                    <?php if ($can_edit) { ?>
                        <div class="btn-group btn-group-sm" role="group" aria-label="Set the new prices">
                            <button type="button" class="btn btn-outline-secondary" data-reprice-fill="catalog" title="Every line here at <?= escapeHtml(billingMoney($group['product_price'], $currency)) ?>"><i class="fas fa-tag me-1"></i>Catalog price</button>
                            <?php if ($ratio && $percent != 0) { ?>
                                <button type="button" class="btn btn-outline-secondary" data-reprice-fill="ratio" data-ratio="<?= $ratio ?>" title="Each line's price changed by the same <?= escapeHtml((string) $percent) ?>%"><i class="fas fa-percent me-1"></i><?= $percent > 0 ? '+' : '' ?><?= escapeHtml((string) $percent) ?>% each</button>
                            <?php } ?>
                        </div>
                    <?php } ?>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 itflow-reprice-table">
                        <thead class="text-nowrap small">
                        <tr>
                            <?php if ($can_edit) { ?>
                                <th class="checkbox-column"><input class="form-check-input" type="checkbox" title="Pick them all" aria-label="Pick them all" data-reprice-all></th>
                            <?php } ?>
                            <th>On</th>
                            <th>Client</th>
                            <th class="d-none d-lg-table-cell">Line</th>
                            <th class="text-end">Qty</th>
                            <th class="text-end">Now</th>
                            <th class="text-end" style="width: 9.5rem;">New price</th>
                            <th class="text-end d-none d-sm-table-cell">Change</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($group['lines'] as $line) {
                            $kind = $line['kind'];
                            $key = $kind . ':' . intval($line['item_id']);
                            $line_currency = (string) $line['currency'];
                            $frequency = (string) $line['frequency'];
                            $monthly_factor = $kind === 'recurring' ? ['year' => 1 / 12, 'quarter' => 1 / 3][$frequency] ?? 1 : 0; ?>
                            <tr data-reprice-row data-kind="<?= $kind ?>" data-price="<?= floatval($line['item_price']) ?>" data-qty="<?= floatval($line['item_quantity']) ?>"
                                data-catalog="<?= round(floatval($line['product_price']), 2) ?>" data-monthly="<?= $monthly_factor ?>" data-currency="<?= escapeHtml($line_currency) ?>"
                                data-period="<?= escapeHtml($period_words[$frequency] ?? '') ?>">
                                <?php if ($can_edit) { ?>
                                    <td class="checkbox-column">
                                        <input class="form-check-input" type="checkbox" name="pick[]" value="<?= $key ?>" aria-label="Update this line" <?= $line['old'] ? 'checked' : '' ?> data-reprice-pick>
                                    </td>
                                <?php } ?>
                                <td class="text-nowrap">
                                    <i class="fas fa-fw <?= $kinds[$kind]['icon'] ?> text-secondary me-1" title="<?= $kinds[$kind]['one'] ?>"></i><a class="fw-semibold" href="<?= $doc_pages[$kind] . intval($line['doc_id']) ?>"><?= escapeHtml($line['number']) ?></a>
                                    <div class="small text-body-secondary"><?= escapeHtml($kind === 'recurring' ? $line['status'] . ($frequency ? ', ' . recurringInvoicePeriodWord($frequency) . 'ly' : '') : $line['status']) ?></div>
                                </td>
                                <td><a class="text-reset" href="client_overview.php?client_id=<?= intval($line['client_id']) ?>"><?= escapeHtml($line['client_name']) ?></a></td>
                                <td class="d-none d-lg-table-cell small"><?= escapeHtml($line['item_name']) ?></td>
                                <td class="text-end"><?= escapeHtml(billingQuantity($line['item_quantity'])) ?></td>
                                <td class="text-end text-nowrap">
                                    <span class="itflow-money"><?= escapeHtml(billingMoney($line['item_price'], $line_currency)) ?></span>
                                    <?php if (!$line['old']) { ?>
                                        <div><span class="badge rounded-pill bg-info-subtle text-info-emphasis fw-normal" title="A price this product never had - agreed with the client?">Own price</span></div>
                                    <?php } ?>
                                </td>
                                <td class="text-end">
                                    <?php if ($can_edit) { ?>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text"><i class="fas fa-fw fa-dollar-sign"></i></span>
                                            <input type="text" class="form-control text-end" name="price[<?= $key ?>]" inputmode="decimal" value="<?= number_format($line['suggested'], 2, '.', '') ?>" aria-label="New price" data-reprice-price>
                                        </div>
                                    <?php } else { ?>
                                        <span class="itflow-money"><?= escapeHtml(billingMoney($line['product_price'], $line_currency)) ?></span>
                                    <?php } ?>
                                </td>
                                <td class="text-end text-nowrap small d-none d-sm-table-cell" data-reprice-change></td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php } ?>

        <?php if (!$groups) { ?>
            <div class="itflow-asset-empty">
                <i class="fas fa-tags fa-2x mb-2 opacity-50"></i>
                <div><?= $narrowed ? 'Every catalog line here is at its product\'s price.' : 'Every catalog line on recurring invoices, drafts and quotes is at its product\'s price.' ?></div>
            </div>
        <?php } elseif ($line_count >= 1000) { ?>
            <div class="small text-body-secondary px-3 py-2 border-top">The first 1000 lines - update these, or narrow it to a product, and the rest show.</div>
        <?php } ?>

        <?php if ($groups && $can_edit) { ?>
            <div class="card-footer d-flex flex-wrap gap-2 align-items-center itflow-reprice-footer">
                <span class="small text-body-secondary me-auto" data-reprice-summary>Nothing picked</span>
                <button type="submit" name="products_reprice_save" class="btn btn-primary text-bold" data-reprice-save disabled
                    ><i class="fas fa-check me-2"></i><span data-reprice-save-label>Update Prices</span></button>
            </div>
        <?php } ?>
    </div>
</form>

<script>
(function () {
    var form = document.getElementById('productsReprice');
    if (!form) {
        return;
    }
    var locale = <?= json_encode(str_replace('_', '-', (string) $session_company_locale)) ?>;
    var home = <?= json_encode((string) $session_company_currency) ?>;
    var summary = form.querySelector('[data-reprice-summary]');
    var save = form.querySelector('[data-reprice-save]');
    var label = form.querySelector('[data-reprice-save-label]');

    function money(amount, currency) {
        try {
            return new Intl.NumberFormat(locale || undefined, { style: 'currency', currency: currency || home || 'USD' }).format(amount);
        } catch (e) {
            return amount.toFixed(2);
        }
    }
    function signed(amount, currency) {
        return (amount > 0 ? '+' : (amount < 0 ? '-' : '')) + money(Math.abs(amount), currency);
    }
    function number(input) {
        var text = String(input.value).replace(/[\s,$€£]/g, '');
        return text === '' ? NaN : Number(text);
    }
    function update() {
        var picked = 0, bad = 0, monthly = 0;
        form.querySelectorAll('[data-reprice-row]').forEach(function (row) {
            var pick = row.querySelector('[data-reprice-pick]');
            var input = row.querySelector('[data-reprice-price]');
            var cell = row.querySelector('[data-reprice-change]');
            var on = pick && pick.checked;
            row.classList.toggle('itflow-reprice-off', !on);
            input.disabled = !on;
            var price = number(input);
            var valid = !isNaN(price) && price >= 0;
            input.classList.toggle('is-invalid', on && !valid);
            if (!valid) {
                cell.textContent = '';
                bad += on ? 1 : 0;
                return;
            }
            var change = Math.round((price - Number(row.dataset.price)) * Number(row.dataset.qty) * 100) / 100;
            cell.textContent = change === 0 ? 'No change' : signed(change, row.dataset.currency) + (row.dataset.period ? ' ' + row.dataset.period : '');
            cell.className = 'text-end text-nowrap small d-none d-sm-table-cell ' + (change > 0 ? 'text-success-emphasis' : (change < 0 ? 'text-danger-emphasis' : 'text-body-secondary'));
            if (on) {
                picked++;
                monthly += change * Number(row.dataset.monthly);
            }
        });
        form.querySelectorAll('[data-reprice-group]').forEach(function (group) {
            var all = group.querySelector('[data-reprice-all]');
            var picks = group.querySelectorAll('[data-reprice-pick]');
            var on = group.querySelectorAll('[data-reprice-pick]:checked').length;
            if (all) {
                all.checked = on === picks.length;
                all.indeterminate = on > 0 && on < picks.length;
            }
        });
        if (summary) {
            monthly = Math.round(monthly * 100) / 100;
            summary.textContent = picked
                ? picked + ' line' + (picked === 1 ? '' : 's') + ' picked' + (monthly !== 0 ? ' - recurring invoices bring in ' + signed(monthly) + ' a month' : '') + (bad ? ', ' + bad + ' without a price' : '')
                : 'Nothing picked';
            label.textContent = picked ? 'Update ' + picked + ' Price' + (picked === 1 ? '' : 's') : 'Update Prices';
            save.disabled = !picked || bad > 0;
        }
    }
    // Prices as they are typed - picks on change, so Pick all sees its own click first
    form.addEventListener('input', function (e) {
        if (e.target.matches('[data-reprice-price]')) {
            update();
        }
    });
    form.addEventListener('change', function (e) {
        if (e.target.matches('[data-reprice-all]')) {
            e.target.closest('[data-reprice-group]').querySelectorAll('[data-reprice-pick]').forEach(function (pick) {
                pick.checked = e.target.checked;
            });
        }
        update();
    });
    form.addEventListener('click', function (e) {
        var fill = e.target.closest('[data-reprice-fill]');
        if (!fill) {
            return;
        }
        fill.closest('[data-reprice-group]').querySelectorAll('[data-reprice-row]').forEach(function (row) {
            var input = row.querySelector('[data-reprice-price]');
            var price = fill.dataset.repriceFill === 'catalog' ? Number(row.dataset.catalog) : Number(row.dataset.price) * Number(fill.dataset.ratio);
            input.value = (Math.round(price * 100) / 100).toFixed(2);
        });
        update();
    });
    update();
})();
</script>

<?php
require_once "../includes/footer.php";
