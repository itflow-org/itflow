<?php

/*
 * Spend by Vendor - who the money goes to: every vendor paid in the period,
 * largest first, with how many expenses, their share of all spending, the
 * category most of it went under, what it was the period before, and what
 * is committed to them every month in recurring expenses.
 *
 * "Paid 600 or more" narrows it to the vendors a US business may owe a 1099
 * for - pick a calendar year (This year, Last year or a year) for that.
 */

require_once "includes/inc_report.php";
reportEnforce('expense_by_vendor');

$period = reportPeriod('this_year');
$only_1099 = !empty($_GET['over600']);

$range = reportRangeSql('expense_date', $period);
$prev_range = reportRangeSql('expense_date', $period, true);

$vendors = [];
$sql = mysqli_query($mysqli, "
    SELECT expense_vendor_id AS vendor_id, vendor_name, vendor_client_id, COUNT(expense_id) AS n, SUM(expense_amount) AS v, MAX(expense_date) AS last_paid
    FROM expenses
    LEFT JOIN vendors ON expense_vendor_id = vendor_id
    WHERE expense_vendor_id > 0 AND $range
    GROUP BY expense_vendor_id, vendor_name, vendor_client_id
    ORDER BY v DESC");
while ($row = mysqli_fetch_assoc($sql)) {
    $vendors[intval($row['vendor_id'])] = [
        'vendor_id' => intval($row['vendor_id']),
        'vendor' => $row['vendor_name'] ?? 'Deleted vendor',
        'exists' => $row['vendor_name'] !== null,
        'count' => intval($row['n']),
        'total' => floatval($row['v']),
        'last' => $row['last_paid'],
        'prev' => 0.0,
        'category' => '',
        'recurring' => 0.0,
    ];
}

$sql = mysqli_query($mysqli, "SELECT expense_vendor_id AS vendor_id, SUM(expense_amount) AS v FROM expenses WHERE expense_vendor_id > 0 AND $prev_range GROUP BY expense_vendor_id");
$prev_total = 0.0;
while ($row = mysqli_fetch_assoc($sql)) {
    $prev_total += floatval($row['v']);
    if (isset($vendors[intval($row['vendor_id'])])) {
        $vendors[intval($row['vendor_id'])]['prev'] = floatval($row['v']);
    }
}

// The category most of each vendor's money went under
$sql = mysqli_query($mysqli, "SELECT expense_vendor_id AS vendor_id, category_name, SUM(expense_amount) AS v
    FROM expenses LEFT JOIN categories ON expense_category_id = category_id
    WHERE expense_vendor_id > 0 AND $range
    GROUP BY expense_vendor_id, category_name ORDER BY v DESC");
while ($row = mysqli_fetch_assoc($sql)) {
    $id = intval($row['vendor_id']);
    if (isset($vendors[$id]) && $vendors[$id]['category'] === '') {
        $vendors[$id]['category'] = $row['category_name'] ?? '';
    }
}

// What each is owed every month from now on
$sql = mysqli_query($mysqli, "SELECT recurring_expense_vendor_id AS vendor_id,
        SUM(" . expenseRecurringMonthlySql() . ") AS v
    FROM recurring_expenses
    WHERE " . recurringExpenseRunningSql() . "
    GROUP BY recurring_expense_vendor_id");
while ($row = mysqli_fetch_assoc($sql)) {
    if (isset($vendors[intval($row['vendor_id'])])) {
        $vendors[intval($row['vendor_id'])]['recurring'] = floatval($row['v']);
    }
}

$total = array_sum(array_column($vendors, 'total'));
$count_1099 = count(array_filter($vendors, function ($v) {
    return $v['total'] >= 600;
}));
if ($only_1099) {
    $vendors = array_filter($vendors, function ($v) {
        return $v['total'] >= 600;
    });
}
foreach ($vendors as &$vendor) {
    $vendor['share'] = reportShare($vendor['total'], $total, 1);
    $vendor['change'] = reportChange($vendor['total'], $vendor['prev']);
}
unset($vendor);

$columns = [
    'vendor' => ['label' => 'Vendor', 'html' => function ($row) {
        $name = escapeHtml($row['vendor']);
        return $row['exists'] ? '<a class="fw-semibold text-body" href="/agent/vendor.php?vendor_id=' . $row['vendor_id'] . '">' . $name . '</a>' : '<span class="text-body-secondary fst-italic">' . $name . '</span>';
    }],
    'category' => ['label' => 'Mostly', 'class' => 'small text-body-secondary'],
    'count' => ['label' => 'Expenses', 'type' => 'count'],
    'total' => ['label' => 'Paid', 'type' => 'money', 'class' => 'fw-semibold', 'html' => function ($row) use ($period) {
        return '<a class="itflow-report-amount" href="/agent/expenses.php?vendor=' . $row['vendor_id'] . '&canned_date=custom&dtf=' . $period['start'] . '&dtt=' . $period['end'] . '">' . reportMoney($row['total']) . '</a>';
    }],
    'share' => ['label' => 'Share', 'type' => 'percent', 'html' => function ($row) {
        return '<div class="d-flex align-items-center justify-content-end gap-2"><div class="itflow-report-meter d-none d-lg-flex" style="width: 5rem"><span style="width: ' . min(100, floatval($row['share'])) . '%"></span></div>'
            . '<span class="small text-body-secondary">' . reportPercent($row['share']) . '</span></div>';
    }],
    'prev' => ['label' => 'Period before', 'type' => 'money', 'class' => 'text-body-secondary'],
    'change' => ['label' => 'Change', 'type' => 'percent', 'csv' => false, 'html' => function ($row) {
        return $row['change'] === null ? '<span class="itflow-report-zero">New</span>' : '<span class="small">' . reportChangeHtml($row['total'], $row['prev'], 'down') . '</span>';
    }],
    'recurring' => ['label' => 'Recurring / month', 'type' => 'money', 'title' => 'Active recurring expenses to this vendor, per month', 'html' => function ($row) {
        return $row['recurring'] > 0 ? reportMoney($row['recurring']) : '<span class="itflow-report-zero">–</span>';
    }],
    'last' => ['label' => 'Last paid', 'type' => 'date', 'class' => 'text-nowrap'],
];

if (reportExporting()) {
    reportCsv('Spend by Vendor ' . $period['start'] . ' to ' . $period['end'], $columns, array_values($vendors), ['vendor' => 'Total', 'total' => $only_1099 ? array_sum(array_column($vendors, 'total')) : $total]);
}

require_once "includes/inc_report_layout.php";

$top = array_slice(array_values($vendors), 0, 12);
$biggest = $top[0] ?? null;

?>

<div class="itflow-report">

    <?php reportHeader('expense_by_vendor', [
        'period' => $period,
        'years' => reportPastYears("SELECT DISTINCT YEAR(expense_date) AS y FROM expenses WHERE expense_vendor_id > 0"),
        'controls' => reportSwitchControl('over600', $only_1099, 'Only vendors paid 600 or more'),
    ]); ?>

    <?= reportStats([
        ['label' => 'Paid to vendors', 'value' => reportMoney($total), 'sub' => reportChangeHtml($total, $prev_total, 'down', $period['compare']),
            'url' => '/agent/expenses.php?canned_date=custom&dtf=' . $period['start'] . '&dtt=' . $period['end']],
        ['label' => 'Vendors paid', 'value' => reportCount(count($vendors)), 'sub' => '<span class="text-body-tertiary">' . ($only_1099 ? 'Paid 600 or more' : 'In the period') . '</span>'],
        ['label' => 'Biggest', 'value' => $biggest ? $biggest['vendor'] : '-', 'sub' => $biggest ? reportMoney($biggest['total']) . ' <span class="text-body-tertiary">· ' . reportPercent($biggest['share']) . ' of spending</span>' : ''],
        ['label' => 'Paid 600 or more', 'value' => reportCount($count_1099), 'sub' => '<span class="text-body-tertiary">Vendors to check for a 1099</span>', 'url' => reportUrl(['over600' => 1])],
    ]) ?>

    <?php if ($top) { ?>
        <?= reportSectionOpen('Top vendors', escapeHtml($period['range'])) ?>
            <div class="card-body">
                <?= reportChart([
                    'type' => 'bar',
                    'horizontal' => true,
                    'labels' => array_map(function ($v) { return $v['vendor']; }, $top),
                    'series' => [['label' => 'Paid', 'data' => array_map(function ($v) { return round($v['total'], 2); }, $top), 'tone' => 'accent']],
                    'format' => 'money',
                    'legend' => false,
                ], '', 'What was paid to each of the biggest vendors') ?>
            </div>
        </div>
    <?php } ?>

    <?= reportSectionOpen($only_1099 ? 'Vendors paid 600 or more' : 'Every vendor paid', reportCount(count($vendors)) . ' vendor' . (count($vendors) === 1 ? '' : 's')) ?>
        <?= reportTable($columns, array_values($vendors), [
            'empty' => 'No vendor was paid in this period.',
            'totals' => ['vendor' => 'Total', 'count' => array_sum(array_column($vendors, 'count')), 'total' => array_sum(array_column($vendors, 'total')),
                'prev' => array_sum(array_column($vendors, 'prev')), 'recurring' => array_sum(array_column($vendors, 'recurring'))],
        ]) ?>
        <div class="card-footer small text-body-secondary">
            Expenses with a vendor, by the date they were paid. "Period before" is <?= escapeHtml($period['compare']) ?>.
            <?php if ($only_1099) { ?>A 1099 is filed per calendar year - pick This year, Last year or a year above, and check with your accountant which vendors need one.<?php } ?>
        </div>
    </div>

</div>

<?php require_once "../../includes/footer.php"; ?>
<script src="/js/reports.js"></script>
