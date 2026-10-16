<?php

/*
 * Product - one product or service in the catalog. ?product_id=
 *
 * What it sells for and costs us, so its margin; how it is billed by default;
 * who we buy it from and their part number; for a product, its stock - on the
 * shelf, where, what that is worth and when to reorder - or that it is
 * ordered as needed, and for hardware the client asset it becomes. Internal
 * notes save as you click away.
 *
 * Then in tabs with counts: its stock moves with what was on the shelf after
 * each, the recurring invoices it is on (what that brings in a month), open
 * quotes, the invoices it was sold on, the client assets made from it, its
 * price changes and its history. Figures are for the clients the agent can
 * see. When lines on recurring invoices, drafts or quotes are still at one of
 * its old prices, it says so, with Update Prices.
 */

require_once "includes/inc_all.php";

enforceUserPermission('module_sales');

$product = productRow(intval($_GET['product_id'] ?? 0));

if (!$product) {

    echo '<div class="itflow-asset-empty mt-5"><i class="fas fa-box-open fa-3x mb-3 opacity-50"></i><h1 class="h4">That product isn\'t here</h1>'
        . '<p>It may have been deleted.</p><a class="btn btn-outline-secondary" href="products.php"><i class="fas fa-arrow-left me-2"></i>Back to Products</a></div>';

} else {

    $product_id = intval($product['product_id']);
    $type = $product['product_type'];
    $type_info = productTypes()[$type] ?? productTypes()['service'];
    $currency = (string) $product['product_currency_code'];
    $money = function ($amount) use ($currency) {
        return escapeHtml(billingMoney($amount, $currency));
    };
    $archived_at = $product['product_archived_at'];
    $tracked = intval($product['product_track_stock']) === 1;
    $on_hand = floatval($product['product_stock']);
    $reorder_level = floatval($product['product_reorder_level']);
    $reorder_qty = floatval($product['product_reorder_qty']);
    $needs_reorder = $tracked && !$archived_at && $on_hand <= max($reorder_level, 0);
    $margin = productMargin($product['product_price'], $product['product_cost']);
    $billed = productBilledText($product['product_frequency'], $product['product_seat_source']);
    $period = $product['product_frequency'] ? ' a ' . recurringInvoicePeriodWord($product['product_frequency']) : '';
    $can_edit = lookupUserPermission('module_sales') >= 2;
    $can_delete = lookupUserPermission('module_sales') >= 3 && !empty($config_destructive_deletes_enable);
    $csrf = $_SESSION['csrf_token'];

    // Hardware - the client asset it becomes when sold, and the ones it became
    $asset_type = $type === 'product' ? (string) $product['product_asset_type'] : '';
    $warranty_months = intval($product['product_warranty_months']);
    $can_see_assets = lookupUserPermission('module_support') >= 1;
    $assets = $can_see_assets ? productAssets($product_id, 200) : [];
    $asset_clients = count(array_unique(array_column($assets, 'client_id')));

    // Its price over time, and the lines still at an old price
    $prices = productPrices($product_id, 100);
    $last_change = productPriceLastChange($product_id);
    $behind = productRepriceCount(['product_id' => $product_id]);

    $usage = productUsage($product_id);
    $moves = $type === 'product' ? productStockMoves($product_id, 200) : [];
    $history = productHistory($product_id, 100);
    $sold = $usage['sold'];

    // Recurring first for what is billed each month, quarter or year, invoices first for the rest
    $tabs = [
        'recurring' => ['label' => 'Recurring', 'icon' => 'fa-redo-alt', 'count' => count($usage['recurring'])],
        'invoices' => ['label' => 'Invoices', 'icon' => 'fa-file-invoice-dollar', 'count' => count($usage['invoices'])],
        'quotes' => ['label' => 'Open quotes', 'icon' => 'fa-comment-dollar', 'count' => count($usage['quotes'])],
        'prices' => ['label' => 'Prices', 'icon' => 'fa-tags', 'count' => count($prices)],
        'history' => ['label' => 'History', 'icon' => 'fa-history', 'count' => count($history)],
    ];
    if (!$product['product_frequency']) {
        $tabs = ['invoices' => $tabs['invoices'], 'quotes' => $tabs['quotes'], 'recurring' => $tabs['recurring'], 'prices' => $tabs['prices'], 'history' => $tabs['history']];
    }
    // The client assets made from it, after the invoices it was sold on
    if ($can_see_assets && ($asset_type !== '' || $assets)) {
        $with_assets = [];
        foreach ($tabs as $tab_key => $tab) {
            $with_assets[$tab_key] = $tab;
            if ($tab_key === 'invoices') {
                $with_assets['assets'] = ['label' => 'Assets', 'icon' => 'fa-laptop', 'count' => count($assets)];
            }
        }
        $tabs = $with_assets;
    }
    // Its stock moves - first for a product that tracks stock
    if ($tracked || $moves) {
        $stock_tab = ['stock' => ['label' => 'Stock', 'icon' => 'fa-warehouse', 'count' => count($moves)]];
        $tabs = $tracked ? $stock_tab + $tabs : $tabs + $stock_tab;
    }
    $stock_url = function ($mode) use ($product_id) {
        return "modals/product/product_stock.php?id=$product_id&mode=$mode";
    };

    // Override Tab Title // No Sanitizing needed as this var will only be used in the tab title
    $page_title = $product['product_name'];

    ?>

    <ol class="breadcrumb d-print-none">
        <li class="breadcrumb-item"><a href="products.php">Products</a></li>
        <li class="breadcrumb-item"><a href="products.php?type=<?= $type ?>"><?= $type_info['plural'] ?></a></li>
        <li class="breadcrumb-item active"><?= escapeHtml($product['product_name']) ?></li>
    </ol>

    <!-- What it is -->
    <div class="card mb-3">
        <div class="card-body d-flex flex-wrap align-items-center gap-3">
            <?= productTile($type, 'lg') ?>

            <div class="flex-grow-1 min-w-0">
                <h1 class="h4 fw-semibold mb-1 d-flex flex-wrap align-items-center gap-2">
                    <span><?= escapeHtml($product['product_name']) ?></span>
                    <span class="badge rounded-pill text-bg-light border fw-normal fs-6"><?= $type_info['label'] ?></span>
                    <?php if ($archived_at) { ?><span class="badge rounded-pill text-bg-secondary fw-normal fs-6" title="<?= escapeHtml($archived_at) ?>">Archived</span><?php } ?>
                </h1>
                <div class="text-body-secondary small d-flex flex-wrap column-gap-3 row-gap-1">
                    <?php if ($product['product_code']) { ?><span title="Your code"><i class="fas fa-fw fa-barcode me-1"></i><span class="font-monospace"><?= escapeHtml($product['product_code']) ?></span></span><?php } ?>
                    <?php if ($product['category_name']) { ?><span title="Income category"><i class="fas fa-fw fa-list me-1"></i><?= escapeHtml($product['category_name']) ?></span><?php } ?>
                    <span><i class="fas fa-fw fa-redo-alt me-1"></i><?= escapeHtml($billed) ?></span>
                    <?php if ($product['vendor_name']) { ?><span><i class="fas fa-fw fa-building me-1"></i><a class="text-reset" href="vendor.php?vendor_id=<?= intval($product['product_vendor_id']) ?>"><?= escapeHtml($product['vendor_name']) ?></a></span><?php } ?>
                </div>
            </div>

            <?php if ($can_edit) { ?>
                <div class="d-flex flex-wrap gap-2 d-print-none">
                    <?php if ($tracked && !$archived_at) { ?>
                        <div class="btn-group">
                            <button type="button" class="btn btn-primary ajax-modal" data-modal-url="<?= $stock_url('receive') ?>">
                                <i class="fas fa-dolly me-2"></i>Receive
                            </button>
                            <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-label="More stock actions"></button>
                            <div class="dropdown-menu dropdown-menu-end">
                                <a class="dropdown-item ajax-modal" href="#" data-modal-url="<?= $stock_url('count') ?>"><i class="fas fa-fw fa-clipboard-check text-secondary me-2"></i>Count</a>
                                <a class="dropdown-item ajax-modal" href="#" data-modal-url="<?= $stock_url('write_off') ?>"><i class="fas fa-fw fa-minus-circle text-secondary me-2"></i>Write off</a>
                            </div>
                        </div>
                    <?php } ?>
                    <button type="button" class="btn btn-outline-secondary ajax-modal" data-modal-size="lg" data-modal-url="modals/product/product_edit.php?id=<?= $product_id ?>">
                        <i class="fas fa-edit me-2"></i>Edit
                    </button>
                    <div class="dropdown dropstart">
                        <button class="btn btn-outline-secondary" title="More actions" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-fw fa-ellipsis-v"></i>
                        </button>
                        <div class="dropdown-menu">
                            <a class="dropdown-item" href="post.php?copy_product=<?= $product_id ?>&csrf_token=<?= $csrf ?>">
                                <i class="fas fa-fw fa-copy text-secondary me-2"></i>Copy
                            </a>
                            <div class="dropdown-divider"></div>
                            <?php if ($archived_at) { ?>
                                <a class="dropdown-item" href="post.php?restore_product=<?= $product_id ?>&csrf_token=<?= $csrf ?>">
                                    <i class="fas fa-fw fa-redo text-secondary me-2"></i>Restore
                                </a>
                                <?php if ($can_delete) { ?>
                                    <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_product=<?= $product_id ?>&csrf_token=<?= $csrf ?>"
                                        data-confirm-text="<?= escapeHtml($product['product_name']) ?> goes for good, with its stock. Lines it is on keep their text and amounts.">
                                        <i class="fas fa-fw fa-trash me-2"></i>Delete
                                    </a>
                                <?php } ?>
                            <?php } else { ?>
                                <a class="dropdown-item text-danger confirm-link" href="post.php?archive_product=<?= $product_id ?>&csrf_token=<?= $csrf ?>"
                                    data-confirm-text="<?= escapeHtml($product['product_name']) ?> stops being offered on new lines. Lines it is already on stay.">
                                    <i class="fas fa-fw fa-archive me-2"></i>Archive
                                </a>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            <?php } ?>
        </div>
    </div>

    <!-- What it does for us -->
    <div class="row g-3 mb-3">
        <div class="col-sm-6 col-xl-3">
            <div class="itflow-billing-stat">
                <div class="itflow-billing-stat-label"><i class="fas fa-tag me-1"></i>Price</div>
                <div class="itflow-billing-stat-value"><?= $money($product['product_price']) ?><span class="fs-6 fw-normal text-body-secondary ms-1"><?= escapeHtml(trim($period)) ?></span></div>
                <div class="itflow-billing-stat-note">
                    <?php if ($margin) { ?>
                        <?= productMarginPill($margin) ?> <?= $money($margin['amount']) ?> margin
                    <?php } else { ?>
                        No cost set
                    <?php } ?>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <a class="itflow-billing-stat" href="#product-pane-invoices" data-product-tab-link="invoices" title="On invoices dated in the last 12 months - not drafts, cancelled or non-billable - before tax">
                <div class="itflow-billing-stat-label"><i class="fas fa-chart-line me-1"></i>Sold, 12 months</div>
                <div class="itflow-billing-stat-value"><?= $money($sold['amount']) ?></div>
                <div class="itflow-billing-stat-note"><?= escapeHtml(billingQuantity($sold['qty'])) ?> on <?= intval($sold['invoices']) ?> invoice<?= intval($sold['invoices']) === 1 ? '' : 's' ?> to <?= intval($sold['clients']) ?> client<?= intval($sold['clients']) === 1 ? '' : 's' ?></div>
            </a>
        </div>
        <div class="col-sm-6 col-xl-3">
            <a class="itflow-billing-stat" href="#product-pane-recurring" data-product-tab-link="recurring" title="On active recurring invoices - a quarterly one counts a third, a yearly one a twelfth">
                <div class="itflow-billing-stat-label"><i class="fas fa-redo-alt me-1"></i>On recurring</div>
                <div class="itflow-billing-stat-value <?= $usage['monthly'] > 0 ? 'text-success-emphasis' : '' ?>"><?= $money($usage['monthly']) ?><span class="fs-6 fw-normal text-body-secondary ms-1">/mo</span></div>
                <div class="itflow-billing-stat-note">For <?= intval($usage['monthly_clients']) ?> client<?= intval($usage['monthly_clients']) === 1 ? '' : 's' ?></div>
            </a>
        </div>
        <div class="col-sm-6 col-xl-3">
            <?php if ($type === 'product' && $tracked) { ?>
                <a class="itflow-billing-stat" href="#product-pane-stock" data-product-tab-link="stock">
                    <div class="itflow-billing-stat-label"><i class="fas fa-warehouse me-1"></i>On the shelf</div>
                    <div class="itflow-billing-stat-value <?= $on_hand <= 0 ? 'text-danger-emphasis' : ($needs_reorder ? 'text-warning-emphasis' : '') ?>">
                        <?= escapeHtml(billingQuantity($on_hand)) ?>
                        <?php if ($needs_reorder) { ?><span class="badge rounded-pill bg-warning-subtle text-warning-emphasis fw-normal fs-6 align-middle">Reorder<?= $reorder_qty > 0 ? ' ' . escapeHtml(billingQuantity($reorder_qty)) : '' ?></span><?php } ?>
                    </div>
                    <div class="itflow-billing-stat-note"><?= floatval($product['product_cost']) > 0 && $on_hand > 0 ? $money($on_hand * floatval($product['product_cost'])) . ' at cost' : ($product['product_location'] ? escapeHtml($product['product_location']) : 'Stock tracked') ?><?= $reorder_level > 0 ? ' &middot; reorder at ' . escapeHtml(billingQuantity($reorder_level)) : '' ?></div>
                </a>
            <?php } else { ?>
                <a class="itflow-billing-stat" href="#product-pane-quotes" data-product-tab-link="quotes">
                    <div class="itflow-billing-stat-label"><i class="fas fa-comment-dollar me-1"></i>Open quotes</div>
                    <div class="itflow-billing-stat-value"><?= count($usage['quotes']) ?></div>
                    <div class="itflow-billing-stat-note"><?= $type === 'product' ? 'Ordered as needed - stock not tracked' : 'Draft, out with clients or accepted' ?></div>
                </a>
            <?php } ?>
        </div>
    </div>

    <?php if ($behind['lines'] && $can_edit) { ?>
        <!-- Lines still at an old price -->
        <div class="alert alert-warning d-flex flex-wrap align-items-center gap-2 mb-3">
            <i class="fas fa-tags"></i>
            <div class="flex-grow-1">
                <strong><?= intval($behind['lines']) ?> line<?= $behind['lines'] == 1 ? ' is' : 's are' ?> at an old price</strong> -
                <?= escapeHtml(implode(', ', array_filter([
                    $behind['recurring'] ? intval($behind['recurring']) . ' on recurring invoices' : '',
                    $behind['invoice'] ? intval($behind['invoice']) . ' on draft invoices' : '',
                    $behind['quote'] ? intval($behind['quote']) . ' on draft quotes' : '',
                ]))) ?><?= $behind['monthly'] != 0 ? ', worth ' . escapeHtml(($behind['monthly'] > 0 ? '+' : '-') . billingMoney(abs($behind['monthly']), $currency)) . ' a month' : '' ?>.
            </div>
            <a class="btn btn-sm btn-warning" href="products_reprice.php?product_id=<?= $product_id ?>"><i class="fas fa-tags me-1"></i>Update Prices</a>
        </div>
    <?php } ?>

    <div class="row g-3">

        <!-- The details -->
        <div class="col-xl-4 col-lg-5">

            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title mb-0"><i class="fas fa-fw fa-dollar-sign text-secondary me-2"></i>Pricing</h3>
                </div>
                <div class="card-body">
                    <dl class="itflow-asset-dl mb-0">
                        <dt>Price</dt><dd class="fw-semibold"><?= $money($product['product_price']) ?><?= escapeHtml($period) ?></dd>
                        <dt>Cost</dt><dd><?= floatval($product['product_cost']) > 0 ? $money($product['product_cost']) : '<span class="text-body-secondary">Not set</span>' ?></dd>
                        <dt>Margin</dt><dd><?= $margin ? $money($margin['amount']) . ' ' . productMarginPill($margin) : '<span class="text-body-secondary">-</span>' ?></dd>
                        <?php if ($last_change) { ?>
                            <dt>Changed</dt>
                            <dd><a href="#product-pane-prices" data-product-tab-link="prices"><?= escapeHtml(date('M j, Y', strtotime($last_change['price_created_at']))) ?></a> <span class="small text-body-secondary">from <?= $money($last_change['price_old']) ?></span></dd>
                        <?php } ?>
                        <dt>Tax</dt><dd><?= $product['tax_name'] ? escapeHtml($product['tax_name'] . ' ' . floatval($product['tax_percent']) . '%') : '<span class="text-body-secondary">None</span>' ?></dd>
                        <dt>Billed</dt>
                        <dd>
                            <?= escapeHtml($billed) ?>
                            <?php if ($product['product_seat_source']) { ?><div class="small text-body-secondary">On a recurring invoice the quantity is the client's count</div><?php } ?>
                        </dd>
                    </dl>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title mb-0"><i class="fas fa-fw fa-truck text-secondary me-2"></i>Ordering</h3>
                </div>
                <div class="card-body">
                    <dl class="itflow-asset-dl mb-0">
                        <dt>Vendor</dt>
                        <dd><?= $product['vendor_name'] ? '<a href="vendor.php?vendor_id=' . intval($product['product_vendor_id']) . '">' . escapeHtml($product['vendor_name']) . '</a>' : '<span class="text-body-secondary">Not set</span>' ?></dd>
                        <dt>Part #</dt>
                        <dd>
                            <?php if ($product['product_vendor_part']) { ?>
                                <span class="font-monospace"><?= escapeHtml($product['product_vendor_part']) ?></span><button type="button" class="itflow-copy clipboardjs" title="Copy part number" data-clipboard-text="<?= escapeHtml($product['product_vendor_part']) ?>"><i class="far fa-copy"></i></button>
                            <?php } else { ?>
                                <span class="text-body-secondary">Not set</span>
                            <?php } ?>
                        </dd>
                        <dt>Our code</dt>
                        <dd>
                            <?php if ($product['product_code']) { ?>
                                <span class="font-monospace"><?= escapeHtml($product['product_code']) ?></span><button type="button" class="itflow-copy clipboardjs" title="Copy code" data-clipboard-text="<?= escapeHtml($product['product_code']) ?>"><i class="far fa-copy"></i></button>
                            <?php } else { ?>
                                <span class="text-body-secondary">Not set</span>
                            <?php } ?>
                        </dd>
                        <dt>Added</dt><dd title="<?= escapeHtml($product['product_created_at']) ?>"><?= timeAgo($product['product_created_at']) ?></dd>
                    </dl>
                </div>
            </div>

            <?php if ($type === 'product') { ?>
                <div class="card mb-3">
                    <div class="card-header d-flex align-items-center">
                        <h3 class="card-title mb-0 flex-grow-1"><i class="fas fa-fw fa-warehouse text-secondary me-2"></i>Stock</h3>
                        <?php if ($needs_reorder) { ?><span class="badge rounded-pill bg-warning-subtle text-warning-emphasis fw-normal">To reorder</span><?php } ?>
                    </div>
                    <?php if ($tracked) { ?>
                        <div class="card-body">
                            <dl class="itflow-asset-dl mb-0">
                                <dt>On shelf</dt><dd class="fw-semibold <?= $on_hand <= 0 ? 'text-danger-emphasis' : '' ?>"><?= escapeHtml(billingQuantity($on_hand)) ?></dd>
                                <dt>Reorder</dt>
                                <dd>
                                    <?php if ($reorder_level > 0) { ?>
                                        At <?= escapeHtml(billingQuantity($reorder_level)) ?><?= $reorder_qty > 0 ? ', order ' . escapeHtml(billingQuantity($reorder_qty)) : '' ?>
                                    <?php } else { ?>
                                        <span class="text-body-secondary">When it is out</span>
                                    <?php } ?>
                                </dd>
                                <dt>Location</dt><dd><?= $product['product_location'] ? escapeHtml($product['product_location']) : '<span class="text-body-secondary">Not set</span>' ?></dd>
                                <?php if (floatval($product['product_cost']) > 0) { ?>
                                    <dt>Worth</dt><dd><?= $money(max(0, $on_hand) * floatval($product['product_cost'])) ?> <span class="text-body-secondary small">at cost</span></dd>
                                <?php } ?>
                            </dl>
                        </div>
                        <?php if ($can_edit && !$archived_at) { ?>
                            <div class="card-footer d-flex flex-wrap gap-2 d-print-none">
                                <button type="button" class="btn btn-sm btn-outline-primary ajax-modal" data-modal-url="<?= $stock_url('receive') ?>"><i class="fas fa-dolly me-1"></i>Receive</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary ajax-modal" data-modal-url="<?= $stock_url('count') ?>"><i class="fas fa-clipboard-check me-1"></i>Count</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary ajax-modal" data-modal-url="<?= $stock_url('write_off') ?>"><i class="fas fa-minus-circle me-1"></i>Write off</button>
                            </div>
                        <?php } ?>
                    <?php } else { ?>
                        <div class="card-body small text-body-secondary">
                            Ordered as needed - invoicing it doesn't touch stock.<?= $on_hand ? ' ' . escapeHtml(billingQuantity($on_hand)) . ' on record from when it was tracked.' : '' ?>
                            <?php if ($can_edit) { ?>Turn on Track stock in Edit to count it.<?php } ?>
                        </div>
                    <?php } ?>
                </div>
            <?php } ?>

            <?php if ($type === 'product') { ?>
                <div class="card mb-3">
                    <div class="card-header">
                        <h3 class="card-title mb-0"><i class="fas fa-fw fa-laptop text-secondary me-2"></i>Client asset</h3>
                    </div>
                    <?php if ($asset_type !== '') { ?>
                        <div class="card-body">
                            <dl class="itflow-asset-dl mb-0">
                                <dt>Becomes</dt><dd><i class="fas fa-fw <?= escapeHtml(productAssetTypes()[$asset_type] ?? 'fa-tag') ?> text-secondary me-1"></i><?= escapeHtml($asset_type) ?></dd>
                                <dt>Make</dt><dd><?= $product['product_make'] ? escapeHtml($product['product_make']) : '<span class="text-body-secondary">Not set</span>' ?></dd>
                                <dt>Model</dt><dd><?= $product['product_model'] ? escapeHtml($product['product_model']) : '<span class="text-body-secondary">Not set</span>' ?></dd>
                                <dt>Warranty</dt><dd><?= $warranty_months ? $warranty_months . ' month' . ($warranty_months === 1 ? '' : 's') . ' <span class="small text-body-secondary">from the invoice date</span>' : '<span class="text-body-secondary">Not set</span>' ?></dd>
                                <?php if ($can_see_assets) { ?>
                                    <dt>In use</dt>
                                    <dd><a href="#product-pane-assets" data-product-tab-link="assets"><?= count($assets) ?> asset<?= count($assets) === 1 ? '' : 's' ?></a><?= $asset_clients ? " <span class=\"text-body-secondary\">at $asset_clients client" . ($asset_clients === 1 ? '' : 's') . '</span>' : '' ?></dd>
                                <?php } ?>
                            </dl>
                        </div>
                    <?php } else { ?>
                        <div class="card-body small text-body-secondary">
                            Sold as goods - it doesn't go on the client's assets.
                            <?php if ($can_edit) { ?>Pick an asset type in Edit for hardware the client keeps, and selling it offers to add it to their assets.<?php } ?>
                        </div>
                    <?php } ?>
                </div>
            <?php } ?>

            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title mb-0"><i class="fas fa-fw fa-align-left text-secondary me-2"></i>On invoices and quotes</h3>
                </div>
                <div class="card-body">
                    <div class="fw-semibold"><?= escapeHtml($product['product_name']) ?></div>
                    <?php if (trim((string) $product['product_description']) !== '') { ?>
                        <div class="small text-body-secondary" style="white-space: pre-line;"><?= escapeHtml($product['product_description']) ?></div>
                    <?php } else { ?>
                        <div class="small text-body-secondary fst-italic">No description - the line shows its name only.</div>
                    <?php } ?>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title mb-0"><i class="fas fa-fw fa-sticky-note text-secondary me-2"></i>Notes <span class="small fw-normal text-body-secondary" data-note-status-for="productNotes">for agents only</span></h3>
                </div>
                <textarea class="form-control border-0 rounded-0 rounded-bottom <?= $can_edit ? 'itflow-inline-note' : '' ?>" id="productNotes" rows="5"
                    placeholder="Lead time, where to order it, what to check before quoting - saves when you click away"
                    data-endpoint="product_set_notes" data-id-field="product_id" data-id="<?= $product_id ?>" data-csrf="<?= escapeHtml($csrf) ?>" <?= $can_edit ? '' : 'readonly' ?>><?= escapeHtml((string) $product['product_notes']) ?></textarea>
            </div>

        </div>

        <!-- Where it is used -->
        <div class="col-xl-8 col-lg-7">
            <div class="card mb-3 itflow-asset-related">
                <div class="card-header py-2">
                    <ul class="nav nav-pills itflow-asset-pills" role="tablist">
                        <?php foreach ($tabs as $tab_key => $tab) { ?>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link text-nowrap <?= $tab['count'] ? '' : 'is-empty' ?>" id="product-tab-<?= $tab_key ?>" data-bs-toggle="pill" data-bs-target="#product-pane-<?= $tab_key ?>" data-product-tab="<?= $tab_key ?>" type="button" role="tab">
                                    <i class="fas fa-fw <?= $tab['icon'] ?> me-1 d-none d-xxl-inline"></i><?= $tab['label'] ?>
                                    <span class="badge rounded-pill ms-1"><?= $tab['count'] ?></span>
                                </button>
                            </li>
                        <?php } ?>
                    </ul>
                </div>
                <div class="tab-content">

                    <!-- Recurring invoices it is on -->
                    <div class="tab-pane fade" id="product-pane-recurring" role="tabpanel">
                        <?php if ($usage['recurring']) { ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="text-nowrap small">
                                    <tr><th>Recurring invoice</th><th>Client</th><th class="text-end">Qty</th><th class="text-end">Each time</th><th class="text-end">A month</th><th class="d-none d-md-table-cell">Next</th></tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($usage['recurring'] as $row) {
                                        $active = intval($row['recurring_invoice_status']) === 1; ?>
                                        <tr class="<?= $active ? '' : 'opacity-50' ?>">
                                            <td class="text-nowrap">
                                                <a class="fw-semibold" href="recurring_invoice.php?recurring_invoice_id=<?= intval($row['recurring_invoice_id']) ?>"><?= escapeHtml($row['recurring_invoice_prefix'] . $row['recurring_invoice_number']) ?></a>
                                                <div class="small text-body-secondary"><?= escapeHtml(recurringInvoiceFrequencies()[$row['recurring_invoice_frequency']] ?? '') ?><?= $active ? '' : ' &middot; Paused' ?></div>
                                            </td>
                                            <td><a class="text-reset" href="recurring_invoices.php?client_id=<?= intval($row['client_id']) ?>"><?= escapeHtml($row['client_name']) ?></a></td>
                                            <td class="text-end"><?= escapeHtml(billingQuantity($row['qty'])) ?></td>
                                            <td class="text-end itflow-money"><?= escapeHtml(billingMoney($row['amount'], $row['recurring_invoice_currency_code'])) ?></td>
                                            <td class="text-end itflow-money fw-semibold"><?= escapeHtml(billingMoney($row['monthly'], $row['recurring_invoice_currency_code'])) ?></td>
                                            <td class="d-none d-md-table-cell text-nowrap small"><?= escapeHtml($row['recurring_invoice_next_date']) ?></td>
                                        </tr>
                                    <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php } else { ?>
                            <div class="itflow-asset-empty"><i class="fas fa-redo-alt fa-2x mb-2 opacity-50"></i><div>Not on any recurring invoice<?= $product['product_frequency'] ? ' yet' : '' ?>.</div></div>
                        <?php } ?>
                    </div>

                    <!-- Open quotes it is on -->
                    <div class="tab-pane fade" id="product-pane-quotes" role="tabpanel">
                        <?php if ($usage['quotes']) { ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="text-nowrap small">
                                    <tr><th>Quote</th><th>Client</th><th class="d-none d-md-table-cell">Date</th><th class="text-end">Qty</th><th class="text-end">Amount</th><th>Status</th></tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($usage['quotes'] as $row) { ?>
                                        <tr>
                                            <td class="text-nowrap"><a class="fw-semibold" href="quote.php?quote_id=<?= intval($row['quote_id']) ?>"><?= escapeHtml($row['quote_prefix'] . $row['quote_number']) ?></a></td>
                                            <td><a class="text-reset" href="quotes.php?client_id=<?= intval($row['client_id']) ?>"><?= escapeHtml($row['client_name']) ?></a></td>
                                            <td class="d-none d-md-table-cell text-nowrap small"><?= escapeHtml($row['quote_date']) ?></td>
                                            <td class="text-end"><?= escapeHtml(billingQuantity($row['qty'])) ?></td>
                                            <td class="text-end itflow-money"><?= escapeHtml(billingMoney($row['amount'], $row['quote_currency_code'])) ?><?= $row['frequency'] ? '<div class="small text-body-secondary">a ' . escapeHtml(recurringInvoicePeriodWord($row['frequency'])) . '</div>' : '' ?></td>
                                            <td><?= quoteStatusBadge($row) ?></td>
                                        </tr>
                                    <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php } else { ?>
                            <div class="itflow-asset-empty"><i class="fas fa-comment-dollar fa-2x mb-2 opacity-50"></i><div>Not on any open quote.</div></div>
                        <?php } ?>
                    </div>

                    <!-- Invoices it was sold on -->
                    <div class="tab-pane fade" id="product-pane-invoices" role="tabpanel">
                        <?php if ($usage['invoices']) { ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="text-nowrap small">
                                    <tr><th>Invoice</th><th>Client</th><th class="d-none d-md-table-cell">Date</th><th class="text-end">Qty</th><th class="text-end d-none d-sm-table-cell">Price</th><th class="text-end">Amount</th><th>Status</th></tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($usage['invoices'] as $row) { ?>
                                        <tr>
                                            <td class="text-nowrap"><a class="fw-semibold" href="invoice.php?invoice_id=<?= intval($row['invoice_id']) ?>"><?= escapeHtml($row['invoice_prefix'] . $row['invoice_number']) ?></a></td>
                                            <td><a class="text-reset" href="invoices.php?client_id=<?= intval($row['client_id']) ?>"><?= escapeHtml($row['client_name']) ?></a></td>
                                            <td class="d-none d-md-table-cell text-nowrap small"><?= escapeHtml($row['invoice_date']) ?></td>
                                            <td class="text-end"><?= escapeHtml(billingQuantity($row['item_quantity'])) ?></td>
                                            <td class="text-end itflow-money d-none d-sm-table-cell"><?= escapeHtml(billingMoney($row['item_price'], $row['invoice_currency_code'])) ?></td>
                                            <td class="text-end itflow-money fw-semibold"><?= escapeHtml(billingMoney($row['item_subtotal'], $row['invoice_currency_code'])) ?></td>
                                            <td><?= invoiceStatusBadge($row['invoice_status']) ?></td>
                                        </tr>
                                    <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php if (count($usage['invoices']) >= 50) { ?>
                                <div class="small text-body-secondary px-3 py-2 border-top">The last 50 lines.</div>
                            <?php } ?>
                        <?php } else { ?>
                            <div class="itflow-asset-empty"><i class="fas fa-file-invoice-dollar fa-2x mb-2 opacity-50"></i><div>Not sold on an invoice yet.</div></div>
                        <?php } ?>
                    </div>

                    <?php if (isset($tabs['assets'])) { ?>
                    <!-- The client assets made from it - where it is installed -->
                    <div class="tab-pane fade" id="product-pane-assets" role="tabpanel">
                        <?php if ($assets) { ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="text-nowrap small">
                                    <tr><th>Asset</th><th>Client</th><th class="d-none d-md-table-cell">Serial</th><th>Warranty</th><th class="d-none d-sm-table-cell">Sold on</th></tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($assets as $row) {
                                        $row_warranty = assetWarranty($row['asset_warranty_expire']); ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <?= productAssetIcon($row['asset_type'], 'sm') ?>
                                                    <div class="min-w-0">
                                                        <a class="fw-semibold" href="asset.php?client_id=<?= intval($row['client_id']) ?>&asset_id=<?= intval($row['asset_id']) ?>"><?= escapeHtml($row['asset_name']) ?></a>
                                                        <?php if ($row['asset_archived_at']) { ?><span class="badge rounded-pill text-bg-secondary fw-normal ms-1">Archived</span><?php } ?>
                                                        <div><?= assetStatusPill($row['asset_status']) ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><a class="text-reset" href="assets.php?client_id=<?= intval($row['client_id']) ?>"><?= escapeHtml($row['client_name']) ?></a></td>
                                            <td class="d-none d-md-table-cell font-monospace small"><?= $row['asset_serial'] ? escapeHtml($row['asset_serial']) : '<span class="text-body-secondary">-</span>' ?></td>
                                            <td class="text-nowrap">
                                                <?php if ($row_warranty) { ?>
                                                    <span class="badge rounded-pill bg-<?= $row_warranty['tone'] ?>-subtle text-<?= $row_warranty['tone'] ?>-emphasis border border-<?= $row_warranty['tone'] ?>-subtle fw-normal" title="<?= escapeHtml($row_warranty['text']) ?>"><?= escapeHtml($row_warranty['short']) ?></span>
                                                <?php } else { ?>
                                                    <span class="text-body-secondary small">Not recorded</span>
                                                <?php } ?>
                                            </td>
                                            <td class="d-none d-sm-table-cell text-nowrap small">
                                                <?php if ($row['invoice_id'] !== null) { ?>
                                                    <a href="invoice.php?invoice_id=<?= intval($row['invoice_id']) ?>"><?= escapeHtml($row['invoice_prefix'] . $row['invoice_number']) ?></a>
                                                    <div class="text-body-secondary"><?= escapeHtml($row['invoice_date']) ?></div>
                                                <?php } else { ?>
                                                    <span class="text-body-secondary"><?= $row['asset_purchase_date'] ? escapeHtml($row['asset_purchase_date']) : '-' ?></span>
                                                <?php } ?>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php if (count($assets) >= 200) { ?>
                                <div class="small text-body-secondary px-3 py-2 border-top">The latest 200.</div>
                            <?php } ?>
                        <?php } else { ?>
                            <div class="itflow-asset-empty"><i class="fas fa-laptop fa-2x mb-2 opacity-50"></i><div>Not on any client's assets yet. When it is sold, the invoice offers to add it.</div></div>
                        <?php } ?>
                    </div>
                    <?php } ?>

                    <?php if (isset($tabs['stock'])) { ?>
                    <!-- Every stock move, newest first, with what was on the shelf after it -->
                    <div class="tab-pane fade" id="product-pane-stock" role="tabpanel">
                        <?php if ($moves) {
                            $stock_types = productStockTypes(); ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0 itflow-stock-ledger">
                                    <thead class="text-nowrap small">
                                    <tr><th>When</th><th>What</th><th class="text-end">Qty</th><th class="text-end" title="On the shelf after it">After</th><th class="text-end d-none d-md-table-cell">Unit cost</th><th>For</th><th class="d-none d-lg-table-cell">By</th></tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($moves as $move) {
                                        $move_type = $stock_types[$move['stock_type']] ?? $stock_types['adjusted'];
                                        $qty = floatval($move['stock_qty']); ?>
                                        <tr>
                                            <td class="text-nowrap small" title="<?= escapeHtml($move['stock_created_at']) ?>"><?= escapeHtml(date('M j, Y', strtotime($move['stock_created_at']))) ?></td>
                                            <td class="text-nowrap"><span class="badge rounded-pill bg-<?= $move_type['tone'] ?>-subtle text-<?= $move_type['tone'] ?>-emphasis fw-normal"><i class="fas <?= $move_type['icon'] ?> me-1"></i><?= $move_type['label'] ?></span></td>
                                            <td class="text-end fw-semibold text-nowrap <?= $qty < 0 ? 'text-danger-emphasis' : 'text-success-emphasis' ?>"><?= escapeHtml(productStockQty($qty)) ?></td>
                                            <td class="text-end text-nowrap"><?= escapeHtml(billingQuantity($move['balance'])) ?></td>
                                            <td class="text-end itflow-money d-none d-md-table-cell"><?= $move['stock_unit_cost'] !== null ? $money($move['stock_unit_cost']) : '<span class="text-body-secondary">-</span>' ?></td>
                                            <td class="small" style="min-width: 12rem;">
                                                <?php if ($move['invoice_open']) { ?>
                                                    <a href="invoice.php?invoice_id=<?= intval($move['stock_invoice_id']) ?>"><?= escapeHtml($move['invoice_prefix'] . $move['invoice_number']) ?></a>
                                                <?php } ?>
                                                <?php if ($move['expense_description'] !== null) { ?>
                                                    <div class="text-body-secondary"><i class="fas fa-receipt me-1"></i><?= escapeHtml($move['expense_date'] . ' - ' . mb_strimwidth((string) $move['expense_description'], 0, 60, '...')) ?></div>
                                                <?php } ?>
                                                <?php if (trim((string) $move['stock_note']) !== '' && !($move['invoice_open'] && trim((string) $move['stock_note']) === $move['invoice_prefix'] . $move['invoice_number'])) { ?>
                                                    <div class="text-body-secondary"><?= escapeHtml($move['stock_note']) ?></div>
                                                <?php } ?>
                                            </td>
                                            <td class="small text-nowrap d-none d-lg-table-cell"><?= $move['user_name'] !== null ? escapeHtml($move['user_name']) : '<span class="text-body-secondary">-</span>' ?></td>
                                        </tr>
                                    <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php if (count($moves) >= 200) { ?>
                                <div class="small text-body-secondary px-3 py-2 border-top">The last 200 moves - the Inventory report has them all.</div>
                            <?php } ?>
                        <?php } else { ?>
                            <div class="itflow-asset-empty"><i class="fas fa-warehouse fa-2x mb-2 opacity-50"></i><div>Nothing in or out yet. Receive stock as it comes in.</div></div>
                        <?php } ?>
                    </div>
                    <?php } ?>

                    <!-- Its price and cost over time -->
                    <div class="tab-pane fade" id="product-pane-prices" role="tabpanel">
                        <?php if ($prices) {
                            $price_sources = productPriceSources(); ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="text-nowrap small">
                                    <tr><th>When</th><th class="text-end">Price</th><th class="text-end">Change</th><th class="text-end d-none d-md-table-cell">Cost</th><th class="d-none d-sm-table-cell">How</th><th class="d-none d-lg-table-cell">By</th></tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($prices as $price_row) {
                                        $price_old = floatval($price_row['price_old']);
                                        $price_new = floatval($price_row['price_new']);
                                        $price_moved = round($price_new - $price_old, 2);
                                        $cost_moved = round(floatval($price_row['price_cost_new']) - floatval($price_row['price_cost_old']), 2); ?>
                                        <tr>
                                            <td class="text-nowrap small" title="<?= escapeHtml($price_row['price_created_at']) ?>"><?= escapeHtml(date('M j, Y', strtotime($price_row['price_created_at']))) ?></td>
                                            <td class="text-end text-nowrap">
                                                <?php if ($price_moved != 0) { ?>
                                                    <span class="text-body-secondary small"><?= $money($price_old) ?> &rarr;</span> <span class="fw-semibold"><?= $money($price_new) ?></span>
                                                <?php } else { ?>
                                                    <span class="text-body-secondary"><?= $money($price_new) ?></span>
                                                <?php } ?>
                                            </td>
                                            <td class="text-end text-nowrap small <?= $price_moved > 0 ? 'text-success-emphasis' : ($price_moved < 0 ? 'text-danger-emphasis' : 'text-body-secondary') ?>">
                                                <?= $price_moved == 0 ? '-' : ($price_moved > 0 ? '+' : '') . ($price_old > 0 ? round($price_moved / $price_old * 100, 1) . '%' : $money($price_moved)) ?>
                                            </td>
                                            <td class="text-end text-nowrap small d-none d-md-table-cell">
                                                <?php if ($cost_moved != 0) { ?>
                                                    <span class="text-body-secondary"><?= $money($price_row['price_cost_old']) ?> &rarr;</span> <?= $money($price_row['price_cost_new']) ?>
                                                <?php } else { ?>
                                                    <span class="text-body-secondary"><?= floatval($price_row['price_cost_new']) > 0 ? $money($price_row['price_cost_new']) : '-' ?></span>
                                                <?php } ?>
                                            </td>
                                            <td class="small d-none d-sm-table-cell"><?= escapeHtml($price_sources[$price_row['price_source']] ?? $price_row['price_source']) ?></td>
                                            <td class="small text-nowrap d-none d-lg-table-cell"><?= $price_row['user_name'] !== null ? escapeHtml($price_row['user_name']) : '<span class="text-body-secondary">-</span>' ?></td>
                                        </tr>
                                    <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php } else { ?>
                            <div class="itflow-asset-empty"><i class="fas fa-tags fa-2x mb-2 opacity-50"></i><div>Its price and cost haven't changed since this was kept.</div></div>
                        <?php } ?>
                    </div>

                    <!-- History -->
                    <div class="tab-pane fade" id="product-pane-history" role="tabpanel">
                        <?php if ($history) { ?>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($history as $entry) { ?>
                                    <li class="list-group-item d-flex gap-3 align-items-start">
                                        <span class="badge rounded-pill text-bg-light border fw-normal"><?= escapeHtml($entry['log_action']) ?></span>
                                        <div class="flex-grow-1 small"><?= escapeHtml($entry['log_description']) ?></div>
                                        <span class="small text-body-secondary text-nowrap" title="<?= escapeHtml($entry['log_created_at']) ?>"><?= timeAgo($entry['log_created_at']) ?></span>
                                    </li>
                                <?php } ?>
                            </ul>
                        <?php } else { ?>
                            <div class="itflow-asset-empty"><i class="fas fa-history fa-2x mb-2 opacity-50"></i><div>Nothing recorded yet.</div></div>
                        <?php } ?>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        // The open tab follows the URL hash (#invoices), then the first with something in it
        var tabs = document.querySelectorAll('[data-product-tab]');
        function show(key) {
            var button = document.querySelector('[data-product-tab="' + key + '"]');
            if (button && window.bootstrap) {
                bootstrap.Tab.getOrCreateInstance(button).show();
            }
        }
        tabs.forEach(function (button) {
            button.addEventListener('shown.bs.tab', function () {
                history.replaceState(null, '', '#' + button.dataset.productTab);
            });
        });
        document.querySelectorAll('[data-product-tab-link]').forEach(function (link) {
            link.addEventListener('click', function (e) {
                e.preventDefault();
                show(link.dataset.productTabLink);
                document.querySelector('.itflow-asset-related').scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        });
        var hash = location.hash.replace('#', '');
        var first = Array.prototype.find.call(tabs, function (button) { return !button.classList.contains('is-empty'); }) || tabs[0];
        show(document.querySelector('[data-product-tab="' + hash + '"]') ? hash : first.dataset.productTab);
    });
    </script>
    <script src="/js/inline_notes.js"></script>

<?php
}

require_once "../includes/footer.php";
