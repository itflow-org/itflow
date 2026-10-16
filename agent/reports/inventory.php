<?php

/*
 * Inventory - the products that track their stock. What is on the shelf now
 * and what it is worth at today's cost, what came in and went out over the
 * period at the cost each move was made at (a move without one at the
 * product's cost), and what needs reordering. Below that, each product's
 * in and out for the period, then every stock move in the period with the
 * invoice or expense behind it. Stock belongs to the company, so the figures
 * are the whole company's; an invoice of a client the agent can't open shows
 * without its link.
 */

require_once "includes/inc_report.php";
reportEnforce('inventory');

$period = reportPeriod('last_12');
$range_sql = reportRangeSql('stock_created_at', $period);
$value_sql = "stock_qty * COALESCE(stock_unit_cost, product_cost)";

// Each product - tracked ones, and any other with moves in the period
$products = [];
$sql = mysqli_query($mysqli, "SELECT product_id, product_name, product_code, product_location, product_cost, product_currency_code, product_reorder_level, product_reorder_qty,
        product_track_stock, product_archived_at, vendor_name, " . productStockSql() . " AS on_hand,
        COALESCE(m.received, 0) AS received, COALESCE(m.received_value, 0) AS received_value, COALESCE(m.sold, 0) AS sold, COALESCE(m.sold_value, 0) AS sold_value,
        COALESCE(m.written_off, 0) AS written_off, COALESCE(m.written_off_value, 0) AS written_off_value, COALESCE(m.adjusted, 0) AS adjusted, m.last_in
    FROM products
    LEFT JOIN vendors ON vendor_id = product_vendor_id
    LEFT JOIN (
        SELECT stock_product_id,
            SUM(CASE WHEN stock_type = 'received' THEN stock_qty END) AS received,
            SUM(CASE WHEN stock_type = 'received' THEN $value_sql END) AS received_value,
            -SUM(CASE WHEN stock_type IN ('sold', 'returned') THEN stock_qty END) AS sold,
            -SUM(CASE WHEN stock_type IN ('sold', 'returned') THEN $value_sql END) AS sold_value,
            -SUM(CASE WHEN stock_type = 'written_off' THEN stock_qty END) AS written_off,
            -SUM(CASE WHEN stock_type = 'written_off' THEN $value_sql END) AS written_off_value,
            SUM(CASE WHEN stock_type = 'adjusted' THEN stock_qty END) AS adjusted,
            MAX(CASE WHEN stock_type = 'received' THEN stock_created_at END) AS last_in
        FROM product_stock JOIN products ON product_id = stock_product_id
        WHERE $range_sql
        GROUP BY stock_product_id
    ) m ON m.stock_product_id = product_id
    WHERE (product_track_stock = 1 AND product_archived_at IS NULL) OR m.stock_product_id IS NOT NULL
    ORDER BY product_name ASC");
$value_now = $units_now = 0.0;
$reorder = 0;
$in_value = $out_value = $off_value = $in_units = $out_units = 0.0;
while ($row = mysqli_fetch_assoc($sql)) {
    $on_hand = floatval($row['on_hand']);
    $row['value'] = round(max($on_hand, 0) * floatval($row['product_cost']), 2);
    $row['tracked'] = intval($row['product_track_stock']) === 1 && !$row['product_archived_at'];
    $row['needs_reorder'] = $row['tracked'] && $on_hand <= max(floatval($row['product_reorder_level']), 0);
    $row['status'] = !$row['tracked'] ? 'Not tracked' : ($on_hand <= 0 ? 'Out' : ($row['needs_reorder'] ? 'Reorder' : 'In stock'));
    if ($row['tracked']) {
        $value_now += $row['value'];
        $units_now += max($on_hand, 0);
        $reorder += $row['needs_reorder'] ? 1 : 0;
    }
    $in_value += floatval($row['received_value']);
    $in_units += floatval($row['received']);
    $out_value += floatval($row['sold_value']);
    $out_units += floatval($row['sold']);
    $off_value += floatval($row['written_off_value']);
    $products[] = $row;
}

// Every move in the period, newest first
$moves = [];
$sql = mysqli_query($mysqli, "SELECT stock_id, stock_created_at, stock_type, stock_qty, stock_unit_cost, stock_note, stock_invoice_id, stock_expense_id,
        COALESCE(stock_unit_cost, product_cost) AS unit_cost, $value_sql AS value, product_id, product_name, product_currency_code,
        invoice_prefix, invoice_number, invoice_client_id, client_name, expense_date, expense_description, user_name
    FROM product_stock
    JOIN products ON product_id = stock_product_id
    LEFT JOIN invoices ON invoice_id = stock_invoice_id AND stock_invoice_id > 0
    LEFT JOIN clients ON client_id = invoice_client_id
    LEFT JOIN expenses ON expense_id = stock_expense_id
    LEFT JOIN users ON user_id = stock_user_id AND stock_user_id > 0
    WHERE $range_sql
    ORDER BY stock_created_at DESC, stock_id DESC");
while ($row = mysqli_fetch_assoc($sql)) {
    $row['type_label'] = productStockTypes()[$row['stock_type']]['label'] ?? $row['stock_type'];
    $row['invoice_open'] = $row['invoice_number'] !== null && hasClientAccess(intval($row['invoice_client_id']));
    $moves[] = $row;
}

$product_columns = [
    'product_name' => ['label' => 'Product', 'html' => function ($row) {
        return '<a class="fw-semibold" href="/agent/product.php?product_id=' . intval($row['product_id']) . '#stock">' . escapeHtml($row['product_name']) . '</a>'
            . ($row['product_code'] || $row['vendor_name'] ? '<div class="small text-body-secondary">' . escapeHtml(implode(' · ', array_filter([$row['product_code'], $row['vendor_name']]))) . '</div>' : '');
    }],
    'product_code' => ['label' => 'Code', 'screen' => false],
    'vendor_name' => ['label' => 'Vendor', 'screen' => false],
    'product_location' => ['label' => 'Location', 'class' => 'small'],
    'on_hand' => ['label' => 'On shelf', 'type' => 'decimal', 'decimals' => 2, 'html' => function ($row) {
        $tone = $row['status'] === 'Out' ? 'text-danger-emphasis fw-semibold' : ($row['status'] === 'Reorder' ? 'text-warning-emphasis fw-semibold' : '');
        return '<span class="' . $tone . '">' . escapeHtml(billingQuantity($row['on_hand'])) . '</span>';
    }],
    'product_reorder_level' => ['label' => 'Reorder at', 'type' => 'decimal', 'decimals' => 2, 'html' => function ($row) {
        return floatval($row['product_reorder_level']) > 0 ? escapeHtml(billingQuantity($row['product_reorder_level'])) : '<span class="text-body-secondary">-</span>';
    }],
    'value' => ['label' => 'Value', 'type' => 'money', 'title' => 'On the shelf, at its cost today'],
    'received' => ['label' => 'In', 'type' => 'decimal', 'decimals' => 2, 'title' => 'Received in the period', 'html' => function ($row) {
        return floatval($row['received']) ? escapeHtml(billingQuantity($row['received'])) : '<span class="text-body-secondary">-</span>';
    }],
    'sold' => ['label' => 'Sold', 'type' => 'decimal', 'decimals' => 2, 'title' => 'Invoiced in the period, less what came back', 'html' => function ($row) {
        return floatval($row['sold']) ? escapeHtml(billingQuantity($row['sold'])) : '<span class="text-body-secondary">-</span>';
    }],
    'written_off' => ['label' => 'Written off', 'type' => 'decimal', 'decimals' => 2, 'html' => function ($row) {
        return floatval($row['written_off']) ? escapeHtml(billingQuantity($row['written_off'])) : '<span class="text-body-secondary">-</span>';
    }],
    'sold_value' => ['label' => 'Cost of sales', 'type' => 'money', 'title' => 'What what was sold cost you'],
    'status' => ['label' => 'Status', 'html' => function ($row) {
        $tones = ['Out' => 'danger', 'Reorder' => 'warning', 'In stock' => 'success', 'Not tracked' => 'secondary'];
        $tone = $tones[$row['status']] ?? 'secondary';
        return '<span class="badge rounded-pill bg-' . $tone . '-subtle text-' . $tone . '-emphasis fw-normal">' . escapeHtml($row['status'])
            . ($row['needs_reorder'] && floatval($row['product_reorder_qty']) > 0 ? ' ' . escapeHtml(billingQuantity($row['product_reorder_qty'])) : '') . '</span>';
    }],
];
$move_columns = [
    'stock_created_at' => ['label' => 'When', 'type' => 'date', 'class' => 'small text-nowrap', 'html' => function ($row) {
        return '<span title="' . escapeHtml($row['stock_created_at']) . '">' . escapeHtml(date('M j, Y', strtotime($row['stock_created_at']))) . '</span>';
    }],
    'product_name' => ['label' => 'Product', 'html' => function ($row) {
        return '<a href="/agent/product.php?product_id=' . intval($row['product_id']) . '#stock">' . escapeHtml($row['product_name']) . '</a>';
    }],
    'type_label' => ['label' => 'What', 'html' => function ($row) {
        $type = productStockTypes()[$row['stock_type']] ?? productStockTypes()['adjusted'];
        return '<span class="badge rounded-pill bg-' . $type['tone'] . '-subtle text-' . $type['tone'] . '-emphasis fw-normal">' . escapeHtml($type['label']) . '</span>';
    }],
    'stock_qty' => ['label' => 'Qty', 'type' => 'decimal', 'decimals' => 2, 'html' => function ($row) {
        return '<span class="fw-semibold ' . (floatval($row['stock_qty']) < 0 ? 'text-danger-emphasis' : 'text-success-emphasis') . '">' . escapeHtml(productStockQty($row['stock_qty'])) . '</span>';
    }],
    'unit_cost' => ['label' => 'Unit cost', 'type' => 'money'],
    'value' => ['label' => 'Value', 'type' => 'money', 'title' => 'The quantity at the unit cost'],
    'reference' => ['label' => 'For', 'class' => 'small', 'csv' => function ($row) {
        return trim(implode(' - ', array_filter([$row['invoice_open'] ? $row['invoice_prefix'] . $row['invoice_number'] . ' ' . $row['client_name'] : '',
            $row['expense_description'] !== null ? $row['expense_date'] . ' ' . $row['expense_description'] : '', (string) $row['stock_note']])));
    }, 'html' => function ($row) {
        $html = [];
        if ($row['invoice_open']) {
            $html[] = '<a href="/agent/invoice.php?invoice_id=' . intval($row['stock_invoice_id']) . '">' . escapeHtml($row['invoice_prefix'] . $row['invoice_number']) . '</a> ' . escapeHtml($row['client_name']);
        }
        if ($row['expense_description'] !== null) {
            $html[] = '<i class="fas fa-receipt me-1 text-body-secondary"></i>' . escapeHtml(mb_strimwidth($row['expense_date'] . ' - ' . $row['expense_description'], 0, 70, '...'));
        }
        if (trim((string) $row['stock_note']) !== '' && !($row['invoice_open'] && trim($row['stock_note']) === $row['invoice_prefix'] . $row['invoice_number'])) {
            $html[] = '<span class="text-body-secondary">' . escapeHtml($row['stock_note']) . '</span>';
        }
        return implode('<br>', $html) ?: '<span class="text-body-secondary">-</span>';
    }],
    'user_name' => ['label' => 'By', 'class' => 'small text-nowrap'],
];

if (reportExporting('moves')) {
    reportCsv('Stock Moves ' . $period['start'] . ' to ' . $period['end'], $move_columns, $moves);
}
if (reportExporting()) {
    reportCsv('Inventory ' . $period['start'] . ' to ' . $period['end'], $product_columns, $products, ['product_name' => 'Total', 'value' => round($value_now, 2),
        'received' => $in_units, 'sold' => $out_units, 'sold_value' => round($out_value, 2)]);
}

$buckets = reportBuckets($period);
$bucket_sql = function ($types, $sign) use ($buckets, $range_sql, $value_sql) {
    return "SELECT " . reportBucketSql('stock_created_at', $buckets['unit']) . " AS b, $sign SUM($value_sql) AS v
        FROM product_stock JOIN products ON product_id = stock_product_id
        WHERE stock_type IN ($types) AND $range_sql GROUP BY b";
};
$series = [
    ['label' => 'Received', 'tone' => 'success', 'data' => reportSeries($bucket_sql("'received'", ''), $buckets['keys'])],
    ['label' => 'Cost of sales', 'tone' => 'accent', 'data' => reportSeries($bucket_sql("'sold', 'returned'", '-'), $buckets['keys'])],
    ['label' => 'Written off', 'tone' => 'danger', 'data' => reportSeries($bucket_sql("'written_off'", '-'), $buckets['keys'])],
];

require_once "includes/inc_report_layout.php";

?>

<div class="itflow-report">

    <?php reportHeader('inventory', [
        'period' => $period,
        'export' => ['products' => 'Products', 'moves' => 'Stock moves'],
    ]); ?>

    <?= reportStats([
        ['label' => 'On the shelf', 'value' => reportMoney($value_now), 'title' => 'At each product\'s cost today',
            'sub' => escapeHtml(billingQuantity($units_now)) . ' <span class="text-body-tertiary">units</span>'],
        ['label' => 'Received', 'value' => reportMoney($in_value), 'tone' => $in_value > 0 ? 'success' : '',
            'sub' => escapeHtml(billingQuantity($in_units)) . ' <span class="text-body-tertiary">units in the period</span>'],
        ['label' => 'Cost of sales', 'value' => reportMoney($out_value), 'title' => 'What the stock invoiced in the period cost you, less what came back',
            'sub' => escapeHtml(billingQuantity($out_units)) . ' <span class="text-body-tertiary">units sold</span>' . ($off_value > 0 ? ' &middot; <span class="text-danger-emphasis">' . escapeHtml(reportMoney($off_value)) . ' written off</span>' : '')],
        ['label' => 'To reorder', 'value' => reportCount($reorder), 'tone' => $reorder ? 'warning' : '', 'url' => '/agent/products.php?type=reorder',
            'sub' => $reorder ? '<span class="text-body-tertiary">at or under their reorder level</span>' : '<span class="text-body-tertiary">nothing low</span>'],
    ]) ?>

    <?= reportSectionOpen('In and out, at cost', escapeHtml($period['range'])) ?>
        <div class="card-body">
            <?= reportChart(['type' => 'bar', 'labels' => $buckets['labels'], 'series' => $series, 'format' => 'money'], '', 'Stock received, sold and written off over the period, at cost') ?>
        </div>
    </div>

    <?= reportSectionOpen('Products', count($products) . ' tracked or moved') ?>
        <?= reportTable($product_columns, $products, [
            'empty' => reportEmpty('No product tracks its stock. Turn on Track stock on a product to see it here.', 'fa-warehouse', 'secondary'),
            'export_table' => 'products',
            'totals' => ['product_name' => 'Total', 'value' => round($value_now, 2), 'received' => $in_units, 'sold' => $out_units, 'sold_value' => round($out_value, 2)],
            'totals_html' => ['received' => escapeHtml(billingQuantity($in_units)), 'sold' => escapeHtml(billingQuantity($out_units))],
        ]) ?>
        <div class="card-footer small text-body-secondary">
            Value is what is on the shelf at each product's cost today. In, sold and written off are for the period; cost of sales is at the cost each sale was made at.
        </div>
    </div>

    <?= reportSectionOpen('Stock moves', escapeHtml($period['range'])) ?>
        <?= reportTable($move_columns, $moves, [
            'empty' => reportEmpty('Nothing came in or went out in this period.', 'fa-warehouse', 'secondary'),
            'limit' => 300, 'export_table' => 'moves', 'sortable' => true,
        ]) ?>
    </div>

</div>

<?php require_once "../../includes/footer.php"; ?>
<script src="/js/reports.js"></script>
