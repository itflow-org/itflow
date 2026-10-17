<?php

/*
 * Tax Collected - the tax on paid invoices, by tax and month or quarter, with
 * the sales it was charged on beside it - what a sales tax return asks for.
 *
 * Cash basis, the same as getMonthlyTax(): tax counts when the payment
 * arrives, in proportion to how much of the invoice that payment covered, so
 * a part-paid invoice has only that part of its tax counted. Each invoice's
 * line items are added up per tax first, so an invoice with several payments
 * never multiplies its own tax.
 */

require_once "includes/inc_report.php";
reportEnforce('tax_summary');

[$year, $years] = reportYear("SELECT YEAR(payment_date) AS y FROM payments");
$view = ($_GET['view'] ?? 'quarter') === 'month' ? 'month' : 'quarter';
$current_year = $year === intval(date('Y'));
$columns = reportYearColumns($view);

/** Tax and taxable sales collected between two dates - [tax id => ['tax' => [1..12], 'sales' => [1..12]]]. */
function taxCollected($from, $to) {
    global $mysqli;
    $sql = mysqli_query($mysqli, "
        SELECT item_tax.item_tax_id AS tax_id, MONTH(payment_date) AS m,
            SUM(item_tax.tax_total * payment_amount / invoice_amount) AS tax,
            SUM(item_tax.sales_total * payment_amount / invoice_amount) AS sales
        FROM payments
        JOIN invoices ON invoice_id = payment_invoice_id
        JOIN (
            SELECT item_invoice_id, item_tax_id, SUM(item_tax) AS tax_total, SUM(item_subtotal) AS sales_total
            FROM invoice_items
            WHERE item_tax_id > 0
            GROUP BY item_invoice_id, item_tax_id
        ) AS item_tax ON item_tax.item_invoice_id = invoice_id
        WHERE payment_date >= '$from' AND payment_date < '$to' + INTERVAL 1 DAY AND invoice_amount > 0
        GROUP BY item_tax.item_tax_id, m");
    $taxes = [];
    while ($row = mysqli_fetch_assoc($sql)) {
        $id = intval($row['tax_id']);
        $taxes[$id] = $taxes[$id] ?? ['tax' => array_fill(1, 12, 0.0), 'sales' => array_fill(1, 12, 0.0)];
        $taxes[$id]['tax'][intval($row['m'])] += floatval($row['tax']);
        $taxes[$id]['sales'][intval($row['m'])] += floatval($row['sales']);
    }
    return $taxes;
}

$to = $current_year ? date('Y-m-d') : "$year-12-31";
$collected = taxCollected("$year-01-01", $to);
$prev_collected = taxCollected(($year - 1) . '-01-01', $current_year ? dashboardShiftMonths(date('Y-m-d'), -12) : ($year - 1) . '-12-31');
$compare = $current_year ? 'the same time last year' : (string) ($year - 1);

// Every tax not archived, and any archived one that collected something
$taxes = [];
$ids = array_keys($collected);
$sql = mysqli_query($mysqli, "SELECT tax_id, tax_name, tax_percent, tax_archived_at FROM taxes
    WHERE tax_archived_at IS NULL" . ($ids ? " OR tax_id IN (" . implode(',', array_map('intval', $ids)) . ")" : '') . "
    ORDER BY tax_name ASC");
while ($row = mysqli_fetch_assoc($sql)) {
    $taxes[intval($row['tax_id'])] = $row;
}

$lines = [];
$tax_total = 0.0;
$sales_total = 0.0;
$prev_tax_total = 0.0;
foreach ($taxes as $id => $tax) {
    $amounts = $collected[$id] ?? ['tax' => array_fill(1, 12, 0.0), 'sales' => array_fill(1, 12, 0.0)];
    $prev = array_sum($prev_collected[$id]['tax'] ?? []);
    $lines[$id] = [
        'name' => $tax['tax_name'], 'percent' => floatval($tax['tax_percent']), 'archived' => !empty($tax['tax_archived_at']),
        'tax' => reportSumColumns($amounts['tax'], $columns), 'sales' => reportSumColumns($amounts['sales'], $columns),
        'tax_total' => array_sum($amounts['tax']), 'sales_total' => array_sum($amounts['sales']), 'prev' => $prev,
        'tax_months' => $amounts['tax'],
    ];
    $tax_total += $lines[$id]['tax_total'];
    $sales_total += $lines[$id]['sales_total'];
    $prev_tax_total += $prev;
}

if (reportExporting()) {
    $csv_columns = ['name' => ['label' => 'Tax'], 'what' => ['label' => 'Figure']];
    foreach ($columns as $i => [$label]) {
        $csv_columns["c$i"] = ['label' => $label, 'type' => 'money'];
    }
    $csv_columns['total'] = ['label' => (string) $year, 'type' => 'money'];
    $rows = [];
    foreach ($lines as $line) {
        foreach (['tax' => 'Tax collected', 'sales' => 'Taxable sales'] as $key => $what) {
            $row = ['name' => $line['name'] . ' (' . taxPercentText($line['percent']) . '%)', 'what' => $what, 'total' => $line[$key . '_total']];
            foreach ($columns as $i => $column) {
                $row["c$i"] = $line[$key][$i];
            }
            $rows[] = $row;
        }
    }
    reportCsv("Tax Collected $year", $csv_columns, $rows);
}

require_once "includes/inc_report_layout.php";

$percent_text = function ($percent) {
    return taxPercentText($percent) . '%';
};
$span = count($columns) + 2;

$stats = [
    ['label' => 'Tax collected', 'value' => reportMoney($tax_total), 'sub' => reportChangeHtml($tax_total, $prev_tax_total, 'up', $compare)],
    ['label' => 'Taxable sales', 'value' => reportMoney($sales_total), 'sub' => '<span class="text-body-tertiary">The paid sales the tax was on</span>'],
];
foreach (array_slice($lines, 0, 3, true) as $line) {
    $stats[] = ['label' => $line['name'], 'value' => reportMoney($line['tax_total']), 'sub' => '<span class="text-body-tertiary">At ' . escapeHtml($percent_text($line['percent'])) . '</span>'];
}

?>

<div class="itflow-report">

    <?php reportHeader('tax_summary', [
        'year' => [$year, $years],
        'controls' => reportSegmentControl('view', $view, ['quarter' => 'Quarterly', 'month' => 'Monthly']),
        'subtitle' => $current_year ? "$year to date" : (string) $year,
    ]); ?>

    <?= reportStats($stats) ?>

    <?php if (!$taxes) { ?>
        <div class="card"><div class="card-body"><?= reportEmpty('No taxes are set up. Add them in Admin > Taxes, and the tax on paid invoices shows here.', 'fa-percent', 'secondary') ?></div></div>
    <?php } else { ?>

        <?= reportSectionOpen('Tax collected by month', escapeHtml((string) $year)) ?>
            <div class="card-body">
                <?php
                $months_shown = $current_year ? intval(date('n')) : 12;
                $series = [];
                $i = 0;
                foreach ($lines as $line) {
                    if ($line['tax_total'] <= 0) {
                        continue;
                    }
                    $series[] = ['label' => $line['name'], 'data' => array_map(function ($m) use ($line, $months_shown) {
                        return $m <= $months_shown ? round($line['tax_months'][$m], 2) : null;
                    }, range(1, 12)), 'tone' => reportPalette($i++), 'stack' => 'tax'];
                }
                echo reportChart([
                    'type' => 'bar',
                    'labels' => array_map(function ($m) { return date('M', mktime(0, 0, 0, $m, 1)); }, range(1, 12)),
                    'series' => $series,
                    'stacked' => true,
                    'format' => 'money',
                ], 'sm', 'Tax collected per month, by tax');
                ?>
            </div>
        </div>

        <?= reportSectionOpen('By tax', $view === 'quarter' ? 'By calendar quarter' : 'By month') ?>
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0 itflow-report-table itflow-report-grid">
                    <thead class="table-light">
                    <tr>
                        <th>Tax</th>
                        <?php foreach ($columns as [$label, $first, $last]) { ?>
                            <th class="text-end"<?php if ($view === 'quarter') { ?> title="<?= date('M', mktime(0, 0, 0, $first, 1)) ?> – <?= date('M', mktime(0, 0, 0, $last, 1)) ?>"<?php } ?>><?= $label ?></th>
                        <?php } ?>
                        <th class="text-end"><?= $year ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr class="itflow-report-group-row"><td colspan="<?= $span ?>">Tax collected</td></tr>
                    <?php foreach ($lines as $line) { ?>
                        <tr>
                            <td class="itflow-report-sub"><?= escapeHtml($line['name']) ?> <span class="text-body-secondary small"><?= escapeHtml($percent_text($line['percent'])) ?></span><?php if ($line['archived']) { ?> <span class="badge bg-secondary-subtle text-secondary-emphasis fw-normal">Archived</span><?php } ?></td>
                            <?php foreach ($columns as $i => $column) { ?>
                                <td class="text-end"><?= abs($line['tax'][$i]) < 0.005 ? '<span class="itflow-report-zero">–</span>' : reportMoney($line['tax'][$i]) ?></td>
                            <?php } ?>
                            <td class="text-end fw-semibold"><?= reportMoney($line['tax_total']) ?></td>
                        </tr>
                    <?php } ?>
                    <tr class="fw-semibold">
                        <td>Total tax</td>
                        <?php foreach ($columns as $i => $column) { ?>
                            <td class="text-end"><?= reportMoney(array_sum(array_map(function ($l) use ($i) { return $l['tax'][$i]; }, $lines))) ?></td>
                        <?php } ?>
                        <td class="text-end"><?= reportMoney($tax_total) ?></td>
                    </tr>
                    <tr class="itflow-report-group-row"><td colspan="<?= $span ?>">Sales the tax was charged on</td></tr>
                    <?php foreach ($lines as $line) { ?>
                        <tr>
                            <td class="itflow-report-sub"><?= escapeHtml($line['name']) ?></td>
                            <?php foreach ($columns as $i => $column) { ?>
                                <td class="text-end text-body-secondary"><?= abs($line['sales'][$i]) < 0.005 ? '<span class="itflow-report-zero">–</span>' : reportMoney($line['sales'][$i]) ?></td>
                            <?php } ?>
                            <td class="text-end"><?= reportMoney($line['sales_total']) ?></td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer small text-body-secondary">
                Counted when invoices are paid: each payment carries its share of the invoice's tax, so a part-paid invoice counts only the part that came in.
                Taxable sales are the line items each tax was charged on, before tax. Check figures with your accountant before filing.
            </div>
        </div>

    <?php } ?>

</div>

<?php require_once "../../includes/footer.php"; ?>
<script src="/js/reports.js"></script>
