<?php

/*
 * Profit & Loss - a year of income and spending by category, by month or by
 * quarter, with the net profit and margin under it and last year beside it.
 *
 * Cash basis, as the dashboard: income is invoice payments (filed under the
 * invoice's category) and revenues with a category; spending is vendor
 * expenses. Transfers between accounts are neither. In the current year it
 * runs to today and is compared with the same stretch of last year.
 */

require_once "includes/inc_report.php";
reportEnforce('profit_loss');

[$year, $years] = reportYear("SELECT YEAR(payment_date) AS y FROM payments
    UNION SELECT YEAR(revenue_date) FROM revenues
    UNION SELECT YEAR(expense_date) FROM expenses");
$view = ($_GET['view'] ?? 'month') === 'quarter' ? 'quarter' : 'month';
$current_year = $year === intval(date('Y'));

$from = "$year-01-01";
$to = $current_year ? date('Y-m-d') : "$year-12-31";
$prev_from = ($year - 1) . '-01-01';
$prev_to = $current_year ? dashboardShiftMonths(date('Y-m-d'), -12) : ($year - 1) . '-12-31';
$compare = $current_year ? 'the same time last year' : (string) ($year - 1);

$income = reportIncomeByCategory($from, $to);
$expenses = reportExpensesByCategory($from, $to);
$income_categories = reportCategoryRows('Income', $income);
$expense_categories = reportCategoryRows('Expense', $expenses);

$income_prev = reportIncomeByCategory($prev_from, $prev_to);
$expenses_prev = reportExpensesByCategory($prev_from, $prev_to);
reportCategoryRows('Income', $income_prev);
reportCategoryRows('Expense', $expenses_prev);

$income_total = reportGridTotals($income);
$expense_total = reportGridTotals($expenses);
$income_prev_total = reportGridTotals($income_prev)['total'];
$expense_prev_total = reportGridTotals($expenses_prev)['total'];

$profit = $income_total['total'] - $expense_total['total'];
$profit_prev = $income_prev_total - $expense_prev_total;
$margin = reportShare($profit, $income_total['total'], 1);
$margin_prev = reportShare($profit_prev, $income_prev_total, 1);

$columns = reportYearColumns($view);

// The statement's lines, for the table and the CSV alike
$category_lines = function ($categories, $grid, $prev_grid, $kind) use ($columns) {
    $lines = [];
    foreach ($categories as $cat => $category) {
        $row = reportGridRow($grid, $cat);
        $lines[] = [
            'kind' => $kind, 'cat' => $cat, 'name' => $category['category_name'],
            'archived' => !empty($category['category_archived_at']),
            'cells' => reportSumColumns($row['months'], $columns), 'total' => $row['total'],
            'prev' => reportGridRow($prev_grid, $cat)['total'],
        ];
    }
    return $lines;
};
// A category with nothing in either year is left off - a statement lists what happened
$active = function ($line) {
    return abs($line['total']) >= 0.005 || abs($line['prev']) >= 0.005;
};
$income_lines = array_values(array_filter($category_lines($income_categories, $income, $income_prev, 'income'), $active));
$expense_lines = array_values(array_filter($category_lines($expense_categories, $expenses, $expenses_prev, 'expense'), $active));
$income_line = ['kind' => 'income_total', 'name' => 'Total income', 'cells' => reportSumColumns($income_total['months'], $columns), 'total' => $income_total['total'], 'prev' => $income_prev_total];
$expense_line = ['kind' => 'expense_total', 'name' => 'Total expenses', 'cells' => reportSumColumns($expense_total['months'], $columns), 'total' => $expense_total['total'], 'prev' => $expense_prev_total];
$profit_cells = [];
foreach ($columns as $i => $column) {
    $profit_cells[$i] = $income_line['cells'][$i] - $expense_line['cells'][$i];
}
$profit_line = ['kind' => 'profit', 'name' => 'Net profit', 'cells' => $profit_cells, 'total' => $profit, 'prev' => $profit_prev];

if (reportExporting()) {
    $csv_columns = ['section' => ['label' => 'Section'], 'name' => ['label' => 'Category']];
    foreach ($columns as $i => [$label]) {
        $csv_columns["c$i"] = ['label' => $label, 'type' => 'money'];
    }
    $csv_columns['total'] = ['label' => $current_year ? "$year to date" : "$year total", 'type' => 'money'];
    $csv_columns['prev'] = ['label' => $current_year ? 'Same time last year' : (string) ($year - 1), 'type' => 'money'];
    $flatten = function ($line, $section) use ($columns) {
        $row = ['section' => $section, 'name' => $line['name'], 'total' => $line['total'], 'prev' => $line['prev']];
        foreach ($columns as $i => $column) {
            $row["c$i"] = $line['cells'][$i];
        }
        return $row;
    };
    $csv_rows = [];
    foreach ($income_lines as $line) {
        $csv_rows[] = $flatten($line, 'Income');
    }
    $csv_rows[] = $flatten($income_line, 'Income');
    foreach ($expense_lines as $line) {
        $csv_rows[] = $flatten($line, 'Expenses');
    }
    $csv_rows[] = $flatten($expense_line, 'Expenses');
    $csv_rows[] = $flatten($profit_line, 'Net profit');
    reportCsv("Profit and Loss $year", $csv_columns, $csv_rows);
}

// The chart goes a month at a time, whichever way the table is split
$months_shown = $current_year ? intval(date('n')) : 12;
$chart = ['income' => [], 'expenses' => [], 'profit' => []];
for ($m = 1; $m <= 12; $m++) {
    $in = round($income_total['months'][$m], 2);
    $out = round($expense_total['months'][$m], 2);
    $chart['income'][] = $m <= $months_shown ? $in : null;
    $chart['expenses'][] = $m <= $months_shown ? $out : null;
    $chart['profit'][] = $m <= $months_shown ? round($in - $out, 2) : null;
}

require_once "includes/inc_report_layout.php";

// One line of the statement
$render = function ($line, $class = '') use ($columns, $year) {
    $kind = $line['kind'];
    $cat = $line['cat'] ?? 0;
    $list = $kind === 'income' ? 'income.php' : ($kind === 'expense' ? 'expenses.php' : '');
    $html = '<tr' . ($class !== '' ? ' class="' . $class . '"' : '') . '><td' . ($list ? ' class="itflow-report-sub"' : '') . '>'
        . escapeHtml($line['name']) . (!empty($line['archived']) ? ' <span class="badge bg-secondary-subtle text-secondary-emphasis fw-normal">Archived</span>' : '') . '</td>';
    foreach ($columns as $i => [$label, $first, $last]) {
        $url = '';
        if ($list && $cat) {
            [$dtf, $dtt] = reportColumnDates($year, $first, $last);
            $url = "/agent/$list?category=$cat&canned_date=custom&dtf=$dtf&dtt=$dtt";
        }
        $amount = $line['cells'][$i];
        $html .= '<td class="text-end' . ($kind === 'profit' && $amount < 0 ? ' text-danger' : '') . '">' . reportGridAmount($amount, $url) . '</td>';
    }
    // Spending going up is not good news
    $good = in_array($kind, ['expense', 'expense_total'], true) ? 'down' : 'up';
    $html .= '<td class="text-end fw-semibold' . ($kind === 'profit' && $line['total'] < 0 ? ' text-danger' : '') . '">' . reportGridAmount($line['total']) . '</td>'
        . '<td class="text-end text-body-secondary">' . reportGridAmount($line['prev']) . '</td>'
        . '<td class="text-end small">' . (reportChange($line['total'], $line['prev']) === null ? '<span class="itflow-report-zero">–</span>' : reportChangeHtml($line['total'], $line['prev'], $good)) . '</td>'
        . '</tr>';
    return $html;
};
$span = count($columns) + 4;

?>

<div class="itflow-report">

    <?php reportHeader('profit_loss', [
        'year' => [$year, $years],
        'controls' => reportSegmentControl('view', $view, ['month' => 'Monthly', 'quarter' => 'Quarterly']),
        'subtitle' => $current_year ? "$year to date" : (string) $year,
        'print_meta' => $current_year ? "January 1 to " . date('F j, Y') : "Year $year",
    ]); ?>

    <?= reportStats([
        ['label' => 'Income', 'value' => reportMoney($income_total['total']), 'sub' => reportChangeHtml($income_total['total'], $income_prev_total, 'up', $compare),
            'url' => "/agent/income.php?canned_date=custom&dtf=$from&dtt=$to"],
        ['label' => 'Expenses', 'value' => reportMoney($expense_total['total']), 'sub' => reportChangeHtml($expense_total['total'], $expense_prev_total, 'down', $compare),
            'url' => "/agent/expenses.php?canned_date=custom&dtf=$from&dtt=$to"],
        ['label' => 'Net profit', 'value' => reportMoney($profit), 'tone' => $profit < 0 ? 'danger' : '', 'sub' => reportChangeHtml($profit, $profit_prev, 'up', $compare)],
        ['label' => 'Profit margin', 'value' => reportPercent($margin), 'tone' => $margin !== null && $margin < 0 ? 'danger' : '',
            'sub' => $margin_prev === null ? '<span class="text-body-tertiary">Nothing before to compare</span>' : escapeHtml(reportPercent($margin_prev)) . ' <span class="text-body-tertiary">' . escapeHtml($current_year ? 'same time last year' : 'in ' . ($year - 1)) . '</span>'],
    ]) ?>

    <?= reportSectionOpen('Income, expenses and profit by month', escapeHtml((string) $year)) ?>
        <div class="card-body">
            <?= reportChart([
                'type' => 'bar',
                'labels' => array_map(function ($m) { return date('M', mktime(0, 0, 0, $m, 1)); }, range(1, 12)),
                'series' => [
                    ['label' => 'Income', 'data' => $chart['income'], 'tone' => 'success'],
                    ['label' => 'Expenses', 'data' => $chart['expenses'], 'tone' => 'danger'],
                    ['label' => 'Net profit', 'data' => $chart['profit'], 'tone' => 'accent', 'type' => 'line'],
                ],
                'format' => 'money',
            ], 'lg', 'Income, expenses and net profit per month') ?>
        </div>
    </div>

    <?= reportSectionOpen('Statement', escapeHtml($current_year ? 'Last year is ' . ($year - 1) . ' to ' . date('M j', strtotime($prev_to)) : 'Last year is ' . ($year - 1)), 'itflow-report-long') ?>
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0 itflow-report-table itflow-report-grid">
                <thead class="table-light">
                <tr>
                    <th>Category</th>
                    <?php foreach ($columns as [$label, $first, $last]) { ?>
                        <th class="text-end"<?php if ($view === 'quarter') { ?> title="<?= date('M', mktime(0, 0, 0, $first, 1)) ?> – <?= date('M', mktime(0, 0, 0, $last, 1)) ?>"<?php } ?>><?= $label ?></th>
                    <?php } ?>
                    <th class="text-end"><?= $year ?></th>
                    <th class="text-end">Last year</th>
                    <th class="text-end">Change</th>
                </tr>
                </thead>
                <tbody>
                <tr class="itflow-report-group-row"><td colspan="<?= $span ?>">Income</td></tr>
                <?php foreach ($income_lines as $line) { echo $render($line); } ?>
                <?php if (!$income_lines) { ?><tr><td colspan="<?= $span ?>" class="itflow-report-sub text-body-secondary">No income this year or last.</td></tr><?php } ?>
                <?= $render($income_line, 'fw-semibold') ?>
                <tr class="itflow-report-group-row"><td colspan="<?= $span ?>">Expenses</td></tr>
                <?php foreach ($expense_lines as $line) { echo $render($line); } ?>
                <?php if (!$expense_lines) { ?><tr><td colspan="<?= $span ?>" class="itflow-report-sub text-body-secondary">No spending this year or last.</td></tr><?php } ?>
                <?= $render($expense_line, 'fw-semibold') ?>
                </tbody>
                <tfoot>
                <?= $render($profit_line, 'fw-semibold') ?>
                <tr>
                    <td>Margin</td>
                    <?php foreach ($columns as $i => $column) {
                        $cell_margin = reportShare($profit_line['cells'][$i], $income_line['cells'][$i], 1); ?>
                        <td class="text-end<?= $cell_margin !== null && $cell_margin < 0 ? ' text-danger' : '' ?>"><?= $cell_margin === null ? '<span class="itflow-report-zero">–</span>' : reportPercent($cell_margin) ?></td>
                    <?php } ?>
                    <td class="text-end fw-semibold<?= $margin !== null && $margin < 0 ? ' text-danger' : '' ?>"><?= reportPercent($margin) ?></td>
                    <td class="text-end text-body-secondary"><?= reportPercent($margin_prev) ?></td>
                    <td></td>
                </tr>
                </tfoot>
            </table>
        </div>
        <div class="card-footer small text-body-secondary">
            Cash basis - money counts when it arrives or leaves. Payments are filed under the category of the invoice they paid,
            and transfers between your own accounts are left out of both sides. Click an amount to see what makes it up.
        </div>
    </div>

</div>

<?php require_once "../../includes/footer.php"; ?>
<script src="/js/reports.js"></script>
