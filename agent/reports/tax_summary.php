<?php

/*
 * Tax Collected - the tax on sales by part of each rate (a state's and a
 * county's apart), with the sales it was charged on and the taxable, exempt
 * and non-taxable sales beside it, by month or quarter - what a sales tax,
 * GST or VAT return asks for. Then the tax paid on purchases and what is
 * left to pay, and the exempt sales by client with their certificates.
 *
 * Invoiced counts each invoice sent in the month of its date; Paid counts
 * each payment, carrying its share of its invoice (functions/tax.php:
 * taxReportSales()). Sales are net of discounts, before tax.
 */

require_once "includes/inc_report.php";
reportEnforce('tax_summary');

$basis = isset(TAX_REPORT_BASES[$_GET['basis'] ?? '']) ? $_GET['basis'] : 'paid';
[$year, $years] = reportYear($basis === 'invoiced'
    ? "SELECT YEAR(invoice_date) AS y FROM invoices WHERE invoice_status IN ('Sent', 'Viewed', 'Partial', 'Paid') UNION SELECT YEAR(expense_date) FROM expenses WHERE expense_tax_amount != 0"
    : "SELECT YEAR(payment_date) AS y FROM payments UNION SELECT YEAR(expense_date) FROM expenses WHERE expense_tax_amount != 0");
$view = ($_GET['view'] ?? 'quarter') === 'month' ? 'month' : 'quarter';
$current_year = $year === intval(date('Y'));
$columns = reportYearColumns($view);
$to = $current_year ? date('Y-m-d') : "$year-12-31";

$sales = taxReportSales("$year-01-01", $to, $basis);
$purchases = taxReportPurchases("$year-01-01", $to);
$prev = taxReportSales(($year - 1) . '-01-01', $current_year ? dashboardShiftMonths(date('Y-m-d'), -12) : ($year - 1) . '-12-31', $basis);
$compare = $current_year ? 'the same time last year' : (string) ($year - 1);

$sum = function (array $months) {
    return array_sum($months);
};
$tax_months = taxReportMonths();
foreach ($sales['parts'] as $part) {
    foreach ($part['months'] as $m => $amount) {
        $tax_months[$m] += $amount;
    }
}
$paid_months = taxReportMonths();
foreach ($purchases['parts'] as $part) {
    foreach ($part['months'] as $m => $amount) {
        $paid_months[$m] += $amount;
    }
}
$net_months = array_map(function ($collected, $paid) {
    return $collected - $paid;
}, $tax_months, $paid_months);
$net_months = array_combine(range(1, 12), $net_months);
$tax_total = $sum($tax_months);
$paid_total = $sum($paid_months);
$prev_tax_total = array_sum(array_column($prev['parts'], 'total'));
$has_purchases = (bool) $purchases['parts'];

// A table row for each figure: [group, name, sub, months, emphasis]
$grid = [];
foreach ($sales['parts'] as $label => $part) {
    $grid[] = ['Tax collected', $label, '', $part['months'], false];
}
$grid[] = ['Tax collected', 'Total tax collected', '', $tax_months, true];
$grid[] = ['Sales, before tax and net of discounts', 'Total sales', '', $sales['sales'], true];
$grid[] = ['Sales, before tax and net of discounts', 'Taxable', 'Lines with tax on them', $sales['taxable'], false];
$grid[] = ['Sales, before tax and net of discounts', 'Exempt', 'No tax, for clients who are tax exempt', $sales['exempt'], false];
$grid[] = ['Sales, before tax and net of discounts', 'Non-taxable', 'No tax, for clients who are not exempt', $sales['nontaxable'], false];
if ($has_purchases) {
    foreach ($purchases['parts'] as $label => $part) {
        $grid[] = ['Tax paid on purchases', $label, '', $part['months'], false];
    }
    $grid[] = ['Tax paid on purchases', 'Total tax paid', '', $paid_months, true];
    $grid[] = ['Tax paid on purchases', 'Purchases it was paid on', 'Before tax', $purchases['purchases'], false];
    $grid[] = ['Collected less paid', 'Net tax', 'For GST, HST and VAT returns that take off tax paid', $net_months, true];
}

if (reportExporting() && ($_GET['table'] ?? 'tax') === 'tax') {
    $csv_columns = ['group' => ['label' => 'Section'], 'name' => ['label' => 'Figure'], 'registration' => ['label' => 'Registration']];
    foreach ($columns as $i => [$label]) {
        $csv_columns["c$i"] = ['label' => $label, 'type' => 'money'];
    }
    $csv_columns['total'] = ['label' => (string) $year, 'type' => 'money'];
    $rows = [];
    foreach ($grid as [$group, $name, $sub, $months]) {
        $row = ['group' => $group, 'name' => $name, 'registration' => $group === 'Tax collected' ? ($sales['numbers'][$name] ?? '') : '', 'total' => round(array_sum($months), 2)];
        foreach (reportSumColumns($months, $columns) as $i => $value) {
            $row["c$i"] = round($value, 2);
        }
        $rows[] = $row;
    }
    reportCsv('Tax Collected ' . $year . ' ' . TAX_REPORT_BASES[$basis], $csv_columns, $rows);
}
if (reportExporting('exempt')) {
    $rows = [];
    foreach ($sales['exempt_clients'] as $client) {
        $rows[] = ['client' => $client['name'], 'number' => $client['number'], 'expires' => $client['expires'], 'amount' => round($client['amount'], 2)];
    }
    reportCsv("Exempt Sales $year", [
        'client' => ['label' => 'Client'], 'number' => ['label' => 'Certificate'], 'expires' => ['label' => 'Expires'], 'amount' => ['label' => 'Exempt sales', 'type' => 'money'],
    ], $rows);
}

require_once "includes/inc_report_layout.php";

$span = count($columns) + 2;
$basis_note = $basis === 'invoiced'
    ? 'Counted on each invoice\'s date once it is sent, paid or not. Drafts, cancelled and non-billable invoices are left out.'
    : 'Counted as payments come in: each payment carries its share of its invoice\'s tax and sales, so a part-paid invoice counts only the part paid.';

$stats = [
    ['label' => 'Tax collected', 'value' => reportMoney($tax_total), 'sub' => reportChangeHtml($tax_total, $prev_tax_total, 'up', $compare)],
    ['label' => 'Taxable sales', 'value' => reportMoney($sum($sales['taxable'])), 'sub' => '<span class="text-body-tertiary">Of ' . escapeHtml(reportMoney($sum($sales['sales']))) . ' in sales</span>'],
    ['label' => 'Exempt sales', 'value' => reportMoney($sum($sales['exempt'])), 'sub' => '<span class="text-body-tertiary">' . count($sales['exempt_clients']) . ' exempt client' . (count($sales['exempt_clients']) === 1 ? '' : 's') . '</span>'],
    ['label' => 'Non-taxable sales', 'value' => reportMoney($sum($sales['nontaxable'])), 'sub' => '<span class="text-body-tertiary">No tax on the line</span>'],
];
if ($has_purchases) {
    $stats[] = ['label' => 'Tax paid on purchases', 'value' => reportMoney($paid_total), 'sub' => '<span class="text-body-tertiary">On ' . $purchases['expenses'] . ' expense' . ($purchases['expenses'] === 1 ? '' : 's') . '</span>'];
    $stats[] = ['label' => 'Net tax', 'value' => reportMoney($tax_total - $paid_total), 'sub' => '<span class="text-body-tertiary">Collected less paid</span>'];
}

?>

<div class="itflow-report">

    <?php reportHeader('tax_summary', [
        'year' => [$year, $years],
        'controls' => reportSegmentControl('basis', $basis, TAX_REPORT_BASES) . reportSegmentControl('view', $view, ['quarter' => 'Quarterly', 'month' => 'Monthly']),
        'subtitle' => ($current_year ? "$year to date" : (string) $year) . ' · ' . TAX_REPORT_BASES[$basis],
        'export' => $sales['exempt_clients'] ? ['tax' => 'Tax by ' . $view, 'exempt' => 'Exempt sales by client'] : ['tax' => 'Tax by ' . $view],
        'note' => escapeHtml($basis_note) . ' Sales are before tax and net of discounts.',
        'print_meta' => TAX_REPORT_BASES[$basis] . ' basis',
    ]); ?>

    <?= reportStats($stats) ?>

    <?php if (!$sales['invoices'] && !$has_purchases) { ?>
        <div class="card"><div class="card-body"><?= reportEmpty($basis === 'invoiced' ? "No invoices were sent in $year." : "No payments came in during $year.", 'fa-percent', 'secondary') ?></div></div>
    <?php } else { ?>

        <?php if ($tax_total != 0) { ?>
            <?= reportSectionOpen('Tax collected by month', escapeHtml((string) $year)) ?>
                <div class="card-body">
                    <?php
                    $months_shown = $current_year ? intval(date('n')) : 12;
                    $series = [];
                    $i = 0;
                    foreach ($sales['parts'] as $label => $part) {
                        if (abs($part['total']) < 0.005) {
                            continue;
                        }
                        $series[] = ['label' => $label, 'data' => array_map(function ($m) use ($part, $months_shown) {
                            return $m <= $months_shown ? round($part['months'][$m], 2) : null;
                        }, range(1, 12)), 'tone' => reportPalette($i++), 'stack' => 'tax'];
                    }
                    echo reportChart([
                        'type' => 'bar',
                        'labels' => array_map(function ($m) { return date('M', mktime(0, 0, 0, $m, 1)); }, range(1, 12)),
                        'series' => $series,
                        'stacked' => true,
                        'format' => 'money',
                    ], 'sm', 'Tax collected per month, by part');
                    ?>
                </div>
            </div>
        <?php } ?>

        <?= reportSectionOpen('By ' . ($view === 'quarter' ? 'quarter' : 'month'), $view === 'quarter' ? 'Calendar quarters' : '') ?>
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0 itflow-report-table itflow-report-grid">
                    <thead class="table-light">
                    <tr>
                        <th><?= TAX_REPORT_BASES[$basis] ?> basis</th>
                        <?php foreach ($columns as [$label, $first, $last]) { ?>
                            <th class="text-end"<?php if ($view === 'quarter') { ?> title="<?= date('M', mktime(0, 0, 0, $first, 1)) ?> – <?= date('M', mktime(0, 0, 0, $last, 1)) ?>"<?php } ?>><?= $label ?></th>
                        <?php } ?>
                        <th class="text-end"><?= $year ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php
                    $group_shown = '';
                    foreach ($grid as [$group, $name, $sub, $months, $strong]) {
                        if ($group !== $group_shown) {
                            $group_shown = $group; ?>
                            <tr class="itflow-report-group-row"><td colspan="<?= $span ?>"><?= escapeHtml($group) ?></td></tr>
                        <?php }
                        $cells = reportSumColumns($months, $columns);
                        $registration = $group === 'Tax collected' ? ($sales['numbers'][$name] ?? '') : ''; ?>
                        <tr<?= $strong ? ' class="fw-semibold"' : '' ?>>
                            <td class="<?= $strong ? '' : 'itflow-report-sub' ?>">
                                <?= escapeHtml($name) ?>
                                <?php if ($sub !== '') { ?><div class="small text-body-secondary fw-normal"><?= escapeHtml($sub) ?></div><?php } ?>
                                <?php if ($registration !== '') { ?><div class="small text-body-secondary fw-normal">No. <?= escapeHtml($registration) ?></div><?php } ?>
                            </td>
                            <?php foreach ($cells as $value) { ?>
                                <td class="text-end<?= $strong ? '' : ' text-body-secondary' ?>"><?= abs($value) < 0.005 ? '<span class="itflow-report-zero">–</span>' : reportMoney($value) ?></td>
                            <?php } ?>
                            <td class="text-end fw-semibold"><?= reportMoney(array_sum($months)) ?></td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer small text-body-secondary">
                Tax shows by part, so a rate made of a state's and a county's part counts each where it is due. A rate that changed shows its old and new percents apart.
                Exempt sales are untaxed lines for clients marked tax exempt now. Check the figures with your accountant before filing.
            </div>
        </div>

        <?php if ($sales['exempt_clients']) { ?>
            <?= reportSectionOpen('Exempt sales by client', count($sales['exempt_clients']) . ' client' . (count($sales['exempt_clients']) === 1 ? '' : 's')) ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0 itflow-report-table">
                        <thead class="table-light">
                        <tr>
                            <th>Client</th>
                            <th>Certificate</th>
                            <th>Expires</th>
                            <th class="text-end">Exempt sales</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($sales['exempt_clients'] as $client_id => $client) { ?>
                            <tr>
                                <td><?= reportClientLink($client_id, $client['name']) ?></td>
                                <td><?= $client['number'] !== '' ? escapeHtml($client['number']) : '<span class="text-warning-emphasis">None on file</span>' ?></td>
                                <td>
                                    <?php if ($client['expires'] === '') { ?>
                                        <span class="text-body-secondary">–</span>
                                    <?php } else { ?>
                                        <?= escapeHtml(date('M j, Y', strtotime($client['expires']))) ?>
                                        <?php if ($client['expired']) { ?><span class="badge rounded-pill bg-warning-subtle text-warning-emphasis fw-normal ms-1">Expired</span><?php } ?>
                                    <?php } ?>
                                </td>
                                <td class="text-end"><?= reportMoney($client['amount']) ?></td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php } ?>

    <?php } ?>

</div>

<?php require_once "../../includes/footer.php"; ?>
<script src="/js/reports.js"></script>
