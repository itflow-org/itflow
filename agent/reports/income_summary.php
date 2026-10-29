<?php

/*
 * Income - a year of money received, by income category and month. Invoice
 * payments are filed under the category of the invoice they paid; other
 * income (money in that isn't for an invoice) under its own, and under No
 * category without one. The money coming in from a transfer between our
 * accounts isn't income. The same income the Income list, the dashboard and
 * the Profit & Loss count (functions/income.php). Compared with last year to
 * the same date.
 */

require_once "includes/inc_report.php";
reportEnforce('income_summary');

[$year, $years] = reportYear("SELECT YEAR(payment_date) AS y FROM payments UNION SELECT YEAR(revenue_date) FROM revenues");
$current_year = $year === intval(date('Y'));
$from = "$year-01-01";
$to = $current_year ? date('Y-m-d') : "$year-12-31";
$prev_from = ($year - 1) . '-01-01';
$prev_to = $current_year ? dashboardShiftMonths(date('Y-m-d'), -12) : ($year - 1) . '-12-31';
$compare = $current_year ? 'the same time last year' : (string) ($year - 1);

$grid = reportIncomeByCategory($from, $to);
$categories = reportCategoryRows('Income', $grid);
$categories = reportCategoriesWithAmounts($categories, $grid);
$totals = reportGridTotals($grid);

$prev_grid = reportIncomeByCategory($prev_from, $prev_to);
$prev_total = reportGridTotals($prev_grid)['total'];

if (reportExporting()) {
    reportCategoryYearCsv($categories, $grid, $year, "Income $year", 'Total income');
}

// Where it came from: invoices paid, or other income
$from_invoices = dashboardSum("SELECT SUM(payment_amount) FROM payments WHERE " . reportRangeSql('payment_date', ['start' => $from, 'end' => $to]));
$from_other = $totals['total'] - $from_invoices;

$months_elapsed = reportMonthsElapsed($year);
$average = $months_elapsed ? $totals['total'] / $months_elapsed : 0;
$best_month = 0;
foreach ($totals['months'] as $m => $v) {
    if ($v > ($totals['months'][$best_month] ?? 0)) {
        $best_month = $m;
    }
}

// Last year month by month, as a line behind this year's bars
$prev_full = reportGridTotals(reportIncomeByCategory(($year - 1) . '-01-01', ($year - 1) . '-12-31'))['months'];

require_once "includes/inc_report_layout.php";

?>

<div class="itflow-report">

    <?php reportHeader('income_summary', [
        'year' => [$year, $years],
        'subtitle' => $current_year ? "$year to date" : (string) $year,
    ]); ?>

    <?= reportStats([
        ['label' => 'Income', 'value' => reportMoney($totals['total']), 'sub' => reportChangeHtml($totals['total'], $prev_total, 'up', $compare),
            'url' => "/agent/income.php?canned_date=custom&dtf=$from&dtt=$to"],
        ['label' => 'Invoice payments', 'value' => reportMoney($from_invoices), 'sub' => escapeHtml(reportPercent(reportShare($from_invoices, $totals['total']))) . ' <span class="text-body-tertiary">of income</span>',
            'url' => "/agent/income.php?show=payments&canned_date=custom&dtf=$from&dtt=$to"],
        ['label' => 'Other income', 'value' => reportMoney($from_other), 'sub' => escapeHtml(reportPercent(reportShare($from_other, $totals['total']))) . ' <span class="text-body-tertiary">of income</span>',
            'url' => "/agent/income.php?show=other&canned_date=custom&dtf=$from&dtt=$to"],
        ['label' => 'Monthly average', 'value' => reportMoney($average), 'sub' => $best_month ? 'Best month <span class="text-body">' . date('F', mktime(0, 0, 0, $best_month, 1)) . '</span>' : ''],
    ]) ?>

    <?= reportSectionOpen('Income by month', escapeHtml("$year, with " . ($year - 1) . ' as a line')) ?>
        <div class="card-body">
            <?php
            $series = reportCategoryChartSeries($categories, $grid, $current_year ? intval(date('n')) : 12);
            $series[] = ['label' => (string) ($year - 1), 'data' => array_map(function ($v) { return round($v, 2); }, array_values($prev_full)), 'tone' => 'muted', 'type' => 'line', 'dashed' => true];
            echo reportChart([
                'type' => 'bar',
                'labels' => array_map(function ($m) { return date('M', mktime(0, 0, 0, $m, 1)); }, range(1, 12)),
                'series' => $series,
                'stacked' => true,
                'format' => 'money',
            ], 'lg', 'Income per month by category, with last year as a line');
            ?>
        </div>
    </div>

    <?= reportSectionOpen('By category', 'Click an amount to see the payments and other income behind it', 'itflow-report-long') ?>
        <?= reportCategoryYearTable($categories, $grid, $year, 'income.php', 'Total income', 'show=uncategorised') ?>
        <div class="card-footer small text-body-secondary">
            Payments are filed under the category of the invoice they paid, other income under its own - No category when it has none. Transfers between your own accounts are left out.
        </div>
    </div>

</div>

<?php require_once "../../includes/footer.php"; ?>
<script src="/js/reports.js"></script>
