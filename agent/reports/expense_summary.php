<?php

/*
 * Expenses - a year of spending by expense category and month: expenses with
 * a vendor, the same as the Expenses list and the dashboard (an expense with
 * no vendor is the out side of a transfer between accounts). Compared with
 * last year to the same date; every amount opens the expenses behind it.
 */

require_once "includes/inc_report.php";
reportEnforce('expense_summary');

[$year, $years] = reportYear("SELECT YEAR(expense_date) AS y FROM expenses WHERE expense_vendor_id > 0");
$current_year = $year === intval(date('Y'));
$from = "$year-01-01";
$to = $current_year ? date('Y-m-d') : "$year-12-31";
$prev_from = ($year - 1) . '-01-01';
$prev_to = $current_year ? dashboardShiftMonths(date('Y-m-d'), -12) : ($year - 1) . '-12-31';
$compare = $current_year ? 'the same time last year' : (string) ($year - 1);

$grid = reportExpensesByCategory($from, $to);
$categories = reportCategoriesWithAmounts(reportCategoryRows('Expense', $grid), $grid);
$totals = reportGridTotals($grid);
$prev_total = reportGridTotals(reportExpensesByCategory($prev_from, $prev_to))['total'];

if (reportExporting()) {
    reportCategoryYearCsv($categories, $grid, $year, "Expenses $year", 'Total expenses');
}

$months_elapsed = reportMonthsElapsed($year);
$average = $months_elapsed ? $totals['total'] / $months_elapsed : 0;

$biggest = null;
foreach ($categories as $cat => $category) {
    if (!$biggest || $grid[$cat]['total'] > $grid[$biggest]['total']) {
        $biggest = $cat;
    }
}

// What is already committed every month
$recurring = dashboardSum("SELECT SUM(recurring_expense_amount) FROM recurring_expenses WHERE recurring_expense_status = 1 AND recurring_expense_archived_at IS NULL AND recurring_expense_vendor_id > 0 AND recurring_expense_frequency = 1")
    + dashboardSum("SELECT SUM(recurring_expense_amount) FROM recurring_expenses WHERE recurring_expense_status = 1 AND recurring_expense_archived_at IS NULL AND recurring_expense_vendor_id > 0 AND recurring_expense_frequency = 2") / 12;

$prev_full = reportGridTotals(reportExpensesByCategory(($year - 1) . '-01-01', ($year - 1) . '-12-31'))['months'];

require_once "includes/inc_report_layout.php";

?>

<div class="itflow-report">

    <?php reportHeader('expense_summary', [
        'year' => [$year, $years],
        'subtitle' => $current_year ? "$year to date" : (string) $year,
    ]); ?>

    <?= reportStats([
        ['label' => 'Spending', 'value' => reportMoney($totals['total']), 'sub' => reportChangeHtml($totals['total'], $prev_total, 'down', $compare),
            'url' => "/agent/expenses.php?canned_date=custom&dtf=$from&dtt=$to"],
        ['label' => 'Monthly average', 'value' => reportMoney($average), 'sub' => escapeHtml("Over $months_elapsed month" . ($months_elapsed === 1 ? '' : 's'))],
        ['label' => 'Biggest category', 'value' => $biggest !== null ? $categories[$biggest]['category_name'] : '-',
            'sub' => $biggest !== null ? reportMoney($grid[$biggest]['total']) . ' <span class="text-body-tertiary">· ' . reportPercent(reportShare($grid[$biggest]['total'], $totals['total'])) . '</span>' : ''],
        ['label' => 'Recurring', 'value' => reportMoney($recurring), 'sub' => '<span class="text-body-tertiary">Per month, committed now</span>', 'url' => '/agent/recurring_expenses.php'],
    ]) ?>

    <?= reportSectionOpen('Spending by month', escapeHtml("$year, with " . ($year - 1) . ' as a line')) ?>
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
            ], 'lg', 'Spending per month by category, with last year as a line');
            ?>
        </div>
    </div>

    <?= reportSectionOpen('By category', 'Click an amount to see the expenses behind it', 'itflow-report-long') ?>
        <?= reportCategoryYearTable($categories, $grid, $year, 'expenses.php', 'Total expenses') ?>
        <div class="card-footer small text-body-secondary">
            Expenses with a vendor, by the date they were paid. Transfers between your own accounts are left out.
        </div>
    </div>

</div>

<?php require_once "../../includes/footer.php"; ?>
<script src="/js/reports.js"></script>
