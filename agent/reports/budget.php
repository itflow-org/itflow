<?php

/*
 * Budget vs Actual - what you planned to spend each month by expense
 * category, against what you did spend (vendor expenses, as the Expenses
 * report counts them).
 *
 * Summary shows the year per category: the budget, how much of it the months
 * so far allow, what has been spent, and what is left. By month lays the year
 * out as a grid. Edit (financial write access) sets the amounts - each
 * month, or the same amount every month - or copies last year's in one go.
 * Saving goes to agent/post/budget.php.
 */

require_once "includes/inc_report.php";
reportEnforce('budget');

$next_year = intval(date('Y')) + 1;
[$year, $years] = reportYear("SELECT budget_year AS y FROM budget
    UNION SELECT YEAR(expense_date) FROM expenses WHERE expense_vendor_id > 0
    UNION SELECT $next_year");
$view = in_array($_GET['view'] ?? '', ['month', 'edit'], true) ? $_GET['view'] : 'summary';
$can_edit = lookupUserPermission('module_financial') >= 2;
if ($view === 'edit' && !$can_edit) {
    $view = 'summary';
}

$current_year = intval(date('Y'));
$months_elapsed = reportMonthsElapsed($year);
// How far through the year we are, to the day - what the budget should have covered by now
$year_elapsed = $year < $current_year ? 1.0 : ($year > $current_year ? 0.0 : (intval(date('z')) + 1) / (intval(date('L')) ? 366 : 365));

// The plan: [category => [1..12 => amount]]
$budget = [];
$sql = mysqli_query($mysqli, "SELECT budget_category_id, budget_month, SUM(budget_amount) AS amount FROM budget WHERE budget_year = $year GROUP BY budget_category_id, budget_month");
while ($row = mysqli_fetch_assoc($sql)) {
    $cat = intval($row['budget_category_id']);
    $budget[$cat] = $budget[$cat] ?? array_fill(1, 12, 0.0);
    $month = intval($row['budget_month']);
    if ($month >= 1 && $month <= 12) {
        $budget[$cat][$month] += floatval($row['amount']);
    }
}
$has_prev_budget = intval(dashboardCount("SELECT COUNT(*) FROM budget WHERE budget_year = " . ($year - 1)));

// What was spent
$actual = reportExpensesByCategory("$year-01-01", "$year-12-31");
$categories = reportCategoryRows('Expense', $actual);
foreach ($budget as $cat => $months) {
    if (!isset($categories[$cat])) {
        // Budget against a category that is archived or gone - still show it so it can be cleared
        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT category_id, category_name, category_color, category_archived_at FROM categories WHERE category_id = $cat"));
        $categories[$cat] = $row ?: ['category_id' => $cat, 'category_name' => 'Deleted category', 'category_color' => '', 'category_archived_at' => date('Y-m-d')];
    }
}
uasort($categories, function ($a, $b) {
    return strcasecmp($a['category_name'], $b['category_name']);
});
unset($categories[0]);

// One line per category
$lines = [];
foreach ($categories as $cat => $category) {
    $plan = $budget[$cat] ?? array_fill(1, 12, 0.0);
    $spent = reportGridRow($actual, $cat);
    $plan_total = array_sum($plan);
    $plan_to_date = 0.0;
    for ($m = 1; $m <= $months_elapsed; $m++) {
        $plan_to_date += $plan[$m];
    }
    // The month we are in only counts as far as we are through it
    if ($year === $current_year && $months_elapsed) {
        $plan_to_date -= $plan[$months_elapsed] * (1 - intval(date('j')) / intval(date('t')));
    }
    $lines[$cat] = [
        'name' => $category['category_name'], 'archived' => !empty($category['category_archived_at']),
        'plan' => $plan, 'plan_total' => $plan_total, 'plan_to_date' => $plan_to_date,
        'spent' => $spent['months'], 'spent_total' => $spent['total'],
    ];
}
$shown = array_filter($lines, function ($line) {
    return $line['plan_total'] > 0 || $line['spent_total'] > 0;
});

$plan_total = array_sum(array_column($lines, 'plan_total'));
$plan_to_date = array_sum(array_column($lines, 'plan_to_date'));
$spent_total = array_sum(array_column($lines, 'spent_total'));
$over = array_filter($shown, function ($line) {
    return $line['plan_total'] > 0 && $line['spent_total'] > $line['plan_to_date'] + 0.005;
});
$unbudgeted = array_filter($shown, function ($line) {
    return $line['plan_total'] <= 0 && $line['spent_total'] > 0;
});

if (reportExporting()) {
    $columns = ['name' => ['label' => 'Category']];
    for ($m = 1; $m <= 12; $m++) {
        $columns["b$m"] = ['label' => date('M', mktime(0, 0, 0, $m, 1)) . ' budget', 'type' => 'money'];
        $columns["a$m"] = ['label' => date('M', mktime(0, 0, 0, $m, 1)) . ' actual', 'type' => 'money'];
    }
    $columns['plan_total'] = ['label' => "$year budget", 'type' => 'money'];
    $columns['spent_total'] = ['label' => "$year actual", 'type' => 'money'];
    $columns['left'] = ['label' => 'Remaining', 'type' => 'money'];
    $rows = [];
    foreach ($shown as $line) {
        $row = ['name' => $line['name'], 'plan_total' => $line['plan_total'], 'spent_total' => $line['spent_total'], 'left' => $line['plan_total'] - $line['spent_total']];
        for ($m = 1; $m <= 12; $m++) {
            $row["b$m"] = $line['plan'][$m];
            $row["a$m"] = $line['spent'][$m];
        }
        $rows[] = $row;
    }
    reportCsv("Budget vs Actual $year", $columns, $rows, ['name' => 'Total', 'plan_total' => $plan_total, 'spent_total' => $spent_total, 'left' => $plan_total - $spent_total]);
}

// Month by month, all categories together
$plan_months = array_fill(1, 12, 0.0);
$spent_months = array_fill(1, 12, 0.0);
foreach ($lines as $line) {
    for ($m = 1; $m <= 12; $m++) {
        $plan_months[$m] += $line['plan'][$m];
        $spent_months[$m] += $line['spent'][$m];
    }
}

require_once "includes/inc_report_layout.php";

$money_input = function ($value) {
    return $value > 0 ? number_format($value, 2, '.', '') : '';
};

$used_meter = function ($spent, $plan) {
    if ($plan <= 0) {
        return $spent > 0 ? '<span class="badge rounded-pill bg-warning-subtle text-warning-emphasis border border-warning-subtle fw-normal">No budget</span>' : '';
    }
    $share = $spent / $plan * 100;
    $tone = $share > 100 ? 'bg-danger' : ($share > 90 ? 'bg-warning' : '');
    return '<div class="d-flex align-items-center gap-2"><div class="itflow-report-meter flex-grow-1"><span class="' . $tone . '" style="width: ' . min(100, round($share, 1)) . '%"></span></div>'
        . '<span class="small text-nowrap ' . ($share > 100 ? 'text-danger-emphasis fw-semibold' : 'text-body-secondary') . '">' . reportPercent(round($share)) . '</span></div>';
};

?>

<div class="itflow-report">

    <?php reportHeader('budget', [
        'year' => [$year, $years],
        'controls' => reportSegmentControl('view', $view, array_merge(['summary' => 'Summary', 'month' => 'By month'], $can_edit ? ['edit' => 'Edit budget'] : [])),
        'export' => $view !== 'edit',
    ]); ?>

    <?php if ($view === 'edit') { ?>

        <form action="/agent/post.php" method="post" autocomplete="off" data-budget-form data-currency="<?= escapeHtml($session_company_currency) ?>">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="year" value="<?= $year ?>">

            <div class="card itflow-report-section itflow-report-long">
                <div class="card-header d-flex flex-wrap align-items-center gap-2">
                    <h3 class="card-title mb-0 me-auto">Plan for <?= $year ?></h3>
                    <?php if ($has_prev_budget) { ?>
                        <button type="submit" name="copy_budget" value="1" class="btn btn-outline-secondary btn-sm confirm-link"
                                data-confirm-title="Copy <?= $year - 1 ?>'s budget?" data-confirm-text="Every amount in <?= $year ?> is replaced with <?= $year - 1 ?>'s, month for month." data-confirm-button="Copy it">
                            <i class="fas fa-fw fa-copy me-1"></i>Copy <?= $year - 1 ?>
                        </button>
                    <?php } ?>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0 itflow-report-table itflow-report-grid itflow-report-budget-edit">
                        <thead class="table-light">
                        <tr>
                            <th>Category</th>
                            <th title="Type an amount and press Fill to put it in every month">Every month</th>
                            <?php for ($m = 1; $m <= 12; $m++) { ?>
                                <th class="text-end"><?= date('M', mktime(0, 0, 0, $m, 1)) ?></th>
                            <?php } ?>
                            <th class="text-end"><?= $year ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($lines as $cat => $line) {
                            if ($line['archived'] && $line['plan_total'] <= 0) {
                                continue;
                            } ?>
                            <tr data-budget-row>
                                <td><?= escapeHtml($line['name']) ?><?php if ($line['archived']) { ?> <span class="badge bg-secondary-subtle text-secondary-emphasis fw-normal">Archived</span><?php } ?></td>
                                <td>
                                    <div class="input-group input-group-sm flex-nowrap">
                                        <input type="text" inputmode="decimal" class="form-control" data-budget-fill-value aria-label="Amount for every month of <?= escapeHtml($line['name']) ?>" placeholder="0.00">
                                        <button type="button" class="btn btn-outline-secondary" data-budget-fill title="Put this amount in every month">Fill</button>
                                    </div>
                                </td>
                                <?php for ($m = 1; $m <= 12; $m++) { ?>
                                    <td><input type="text" inputmode="decimal" pattern="[0-9]*[.,]?[0-9]{0,2}" class="form-control form-control-sm text-end" name="budget[<?= $cat ?>][<?= $m ?>]" value="<?= $money_input($line['plan'][$m]) ?>" placeholder="0" aria-label="<?= escapeHtml($line['name']) ?>, <?= date('F', mktime(0, 0, 0, $m, 1)) ?>" data-budget-cell></td>
                                <?php } ?>
                                <td class="text-end fw-semibold" data-budget-row-total><?= reportMoney($line['plan_total']) ?></td>
                            </tr>
                        <?php } ?>
                        </tbody>
                        <tfoot>
                        <tr class="fw-semibold">
                            <td>Total</td>
                            <td></td>
                            <?php for ($m = 1; $m <= 12; $m++) { ?>
                                <td class="text-end" data-budget-month-total="<?= $m ?>"><?= reportMoney($plan_months[$m]) ?></td>
                            <?php } ?>
                            <td class="text-end" data-budget-total><?= reportMoney($plan_total) ?></td>
                        </tr>
                        </tfoot>
                    </table>
                </div>
                <div class="card-footer d-flex flex-wrap align-items-center gap-2">
                    <button type="submit" name="save_budget" value="1" class="btn btn-primary"><i class="fas fa-check me-2"></i>Save budget</button>
                    <a class="btn btn-light" href="<?= escapeHtml(reportUrl(['view' => null])) ?>">Cancel</a>
                    <span class="small text-body-secondary ms-sm-2">Leave a month empty for no budget. Amounts are per month, before tax as you record expenses.</span>
                </div>
            </div>
        </form>

    <?php } else { ?>

        <?php
        $used = reportShare($spent_total, $plan_total);
        echo reportStats([
            ['label' => $year === $current_year ? 'Budget this year' : "Budget $year", 'value' => reportMoney($plan_total),
                'sub' => $plan_total > 0 ? escapeHtml(reportMoney($plan_total / 12)) . ' <span class="text-body-tertiary">a month on average</span>' : '<span class="text-body-tertiary">No budget set yet</span>'],
            ['label' => 'Spent', 'value' => reportMoney($spent_total), 'url' => "/agent/expenses.php?canned_date=custom&dtf=$year-01-01&dtt=$year-12-31",
                'sub' => $used !== null ? escapeHtml(reportPercent($used)) . ' <span class="text-body-tertiary">of the budget' . ($year === $current_year ? ', ' . round($year_elapsed * 100) . '% of the year gone' : '') . '</span>' : ''],
            ['label' => $year === $current_year ? 'Against budget to date' : 'Against budget', 'value' => reportMoney(abs($plan_to_date - $spent_total)),
                'tone' => $plan_total > 0 ? ($spent_total > $plan_to_date ? 'danger' : 'success') : '',
                'sub' => $plan_total > 0 ? ($spent_total > $plan_to_date ? 'Over what the budget allows by now' : 'Under what the budget allows by now') : ''],
            ['label' => 'Remaining this year', 'value' => reportMoney($plan_total - $spent_total), 'tone' => $plan_total - $spent_total < 0 ? 'danger' : '',
                'sub' => count($over) ? '<span class="text-danger-emphasis fw-semibold">' . count($over) . ' categor' . (count($over) === 1 ? 'y' : 'ies') . ' over budget</span>' : '<span class="text-body-tertiary">No category over budget</span>'],
        ]);
        ?>

        <?php if ($plan_total <= 0) { ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="fas fa-bullseye fa-2x text-body-tertiary d-block mb-2"></i>
                    <div class="fw-semibold">No budget for <?= $year ?> yet</div>
                    <div class="small text-body-secondary mb-3">Set what you plan to spend each month by category, and this report compares it with what you actually spend.</div>
                    <?php if ($can_edit) { ?>
                        <a class="btn btn-primary" href="<?= escapeHtml(reportUrl(['view' => 'edit'])) ?>"><i class="fas fa-pen me-2"></i>Set the <?= $year ?> budget</a>
                    <?php } ?>
                </div>
            </div>
        <?php } ?>

        <?= reportSectionOpen('Budget and spending by month', escapeHtml((string) $year)) ?>
            <div class="card-body">
                <?php
                $months_shown = $year === $current_year ? intval(date('n')) : ($year > $current_year ? 0 : 12);
                echo reportChart([
                    'type' => 'bar',
                    'labels' => array_map(function ($m) { return date('M', mktime(0, 0, 0, $m, 1)); }, range(1, 12)),
                    'series' => [
                        ['label' => 'Spent', 'data' => array_map(function ($m) use ($spent_months, $months_shown) { return $m <= $months_shown ? round($spent_months[$m], 2) : null; }, range(1, 12)), 'tone' => 'accent'],
                        ['label' => 'Budget', 'data' => array_map(function ($m) use ($plan_months) { return round($plan_months[$m], 2); }, range(1, 12)), 'tone' => 'muted', 'type' => 'line', 'dashed' => true],
                    ],
                    'format' => 'money',
                ], '', 'Spending per month against the budget');
                ?>
            </div>
        </div>

        <?php if ($view === 'month') { ?>

            <?= reportSectionOpen('By month', 'Spent, with the budget under it - red where it went over', 'itflow-report-long') ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0 itflow-report-table itflow-report-grid">
                        <thead class="table-light">
                        <tr>
                            <th>Category</th>
                            <?php for ($m = 1; $m <= 12; $m++) { ?>
                                <th class="text-end"><?= date('M', mktime(0, 0, 0, $m, 1)) ?></th>
                            <?php } ?>
                            <th class="text-end"><?= $year ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($shown as $cat => $line) { ?>
                            <tr>
                                <td><?= escapeHtml($line['name']) ?></td>
                                <?php for ($m = 1; $m <= 12; $m++) {
                                    $spent = $line['spent'][$m];
                                    $plan = $line['plan'][$m];
                                    [$dtf, $dtt] = reportColumnDates($year, $m, $m); ?>
                                    <td class="text-end">
                                        <div class="<?= $plan > 0 && $spent > $plan + 0.005 ? 'text-danger fw-semibold' : '' ?>"><?= reportGridAmount($spent, "/agent/expenses.php?category=$cat&canned_date=custom&dtf=$dtf&dtt=$dtt") ?></div>
                                        <div class="small text-body-tertiary"><?= $plan > 0 ? reportMoneyWhole($plan) : '&nbsp;' ?></div>
                                    </td>
                                <?php } ?>
                                <td class="text-end">
                                    <div class="fw-semibold <?= $line['plan_total'] > 0 && $line['spent_total'] > $line['plan_total'] ? 'text-danger' : '' ?>"><?= reportGridAmount($line['spent_total']) ?></div>
                                    <div class="small text-body-tertiary"><?= $line['plan_total'] > 0 ? reportMoneyWhole($line['plan_total']) : '&nbsp;' ?></div>
                                </td>
                            </tr>
                        <?php } ?>
                        <?php if (!$shown) { ?>
                            <tr><td colspan="14" class="text-center text-body-secondary py-4">No budget and no spending in <?= $year ?>.</td></tr>
                        <?php } ?>
                        </tbody>
                        <tfoot>
                        <tr class="fw-semibold">
                            <td>Total</td>
                            <?php for ($m = 1; $m <= 12; $m++) { ?>
                                <td class="text-end">
                                    <div class="<?= $plan_months[$m] > 0 && $spent_months[$m] > $plan_months[$m] + 0.005 ? 'text-danger' : '' ?>"><?= reportGridAmount($spent_months[$m]) ?></div>
                                    <div class="small text-body-tertiary fw-normal"><?= $plan_months[$m] > 0 ? reportMoneyWhole($plan_months[$m]) : '&nbsp;' ?></div>
                                </td>
                            <?php } ?>
                            <td class="text-end">
                                <div><?= reportGridAmount($spent_total) ?></div>
                                <div class="small text-body-tertiary fw-normal"><?= reportMoneyWhole($plan_total) ?></div>
                            </td>
                        </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

        <?php } else { ?>

            <?php
            $columns = [
                'name' => ['label' => 'Category', 'html' => function ($row) {
                    return escapeHtml($row['name']) . ($row['archived'] ? ' <span class="badge bg-secondary-subtle text-secondary-emphasis fw-normal">Archived</span>' : '');
                }],
                'plan_total' => ['label' => "Budget $year", 'type' => 'money', 'html' => function ($row) {
                    return $row['plan_total'] > 0 ? reportMoney($row['plan_total']) : '<span class="itflow-report-zero">–</span>';
                }],
                'plan_to_date' => ['label' => $year === $current_year ? 'Budget to date' : 'Budget', 'type' => 'money', 'title' => 'What the budget allows up to today', 'html' => function ($row) {
                    return $row['plan_total'] > 0 ? reportMoney($row['plan_to_date']) : '<span class="itflow-report-zero">–</span>';
                }],
                'spent_total' => ['label' => 'Spent', 'type' => 'money', 'html' => function ($row) use ($year) {
                    if (abs($row['spent_total']) < 0.005) {
                        return '<span class="itflow-report-zero">–</span>';
                    }
                    return '<a class="itflow-report-amount" href="/agent/expenses.php?category=' . intval($row['cat']) . "&canned_date=custom&dtf=$year-01-01&dtt=$year-12-31\">" . reportMoney($row['spent_total']) . '</a>';
                }],
                'difference' => ['label' => 'Under / over', 'type' => 'money', 'title' => 'Budget to date less what was spent - red is over', 'html' => function ($row) {
                    if ($row['plan_total'] <= 0) {
                        return '<span class="itflow-report-zero">–</span>';
                    }
                    return '<span class="' . ($row['difference'] < 0 ? 'text-danger fw-semibold' : 'text-success-emphasis') . '">' . reportMoney($row['difference']) . '</span>';
                }],
                'remaining' => ['label' => 'Left this year', 'type' => 'money', 'html' => function ($row) {
                    if ($row['plan_total'] <= 0) {
                        return '<span class="itflow-report-zero">–</span>';
                    }
                    return '<span class="' . ($row['remaining'] < 0 ? 'text-danger' : '') . '">' . reportMoney($row['remaining']) . '</span>';
                }],
                'used' => ['label' => 'Used', 'type' => 'percent', 'class' => 'itflow-report-used', 'html' => function ($row) use ($used_meter) {
                    return $used_meter($row['spent_total'], $row['plan_total']);
                }],
            ];
            if ($year !== $current_year) {
                // A past year is all "to date", a future one has none yet
                unset($columns['plan_to_date']);
                $columns['difference']['title'] = 'The budget less what was spent - red is over';
            }
            $rows = [];
            foreach ($shown as $cat => $line) {
                $rows[] = [
                    'cat' => $cat, 'name' => $line['name'], 'archived' => $line['archived'],
                    'plan_total' => $line['plan_total'], 'plan_to_date' => $line['plan_to_date'], 'spent_total' => $line['spent_total'],
                    'difference' => $line['plan_to_date'] - $line['spent_total'], 'remaining' => $line['plan_total'] - $line['spent_total'],
                    'used' => reportShare($line['spent_total'], $line['plan_total']),
                ];
            }
            ?>

            <?= reportSectionOpen('By category', $unbudgeted ? '<span class="text-warning-emphasis">' . count($unbudgeted) . ' categor' . (count($unbudgeted) === 1 ? 'y has' : 'ies have') . ' spending but no budget</span>' : '') ?>
                <?= reportTable($columns, $rows, [
                    'empty' => "No budget and no spending in $year.",
                    'totals' => ['name' => 'Total', 'plan_total' => $plan_total, 'plan_to_date' => $plan_to_date, 'spent_total' => $spent_total, 'difference' => $plan_to_date - $spent_total, 'remaining' => $plan_total - $spent_total],
                    'totals_html' => ['used' => $used_meter($spent_total, $plan_total), 'difference' => '<span class="' . ($plan_to_date - $spent_total < 0 ? 'text-danger' : 'text-success-emphasis') . '">' . reportMoney($plan_to_date - $spent_total) . '</span>'],
                ]) ?>
                <div class="card-footer small text-body-secondary">
                    Spending is expenses with a vendor, by the date paid. <?= $year === $current_year ? 'Budget to date counts the months so far, and this month as far as today.' : '' ?>
                    <?php if ($can_edit) { ?><a href="<?= escapeHtml(reportUrl(['view' => 'edit'])) ?>">Edit the <?= $year ?> budget</a><?php } ?>
                </div>
            </div>

        <?php } ?>

    <?php } ?>

</div>

<?php require_once "../../includes/footer.php"; ?>
<script src="/js/reports.js"></script>
