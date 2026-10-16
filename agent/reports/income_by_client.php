<?php

/*
 * Income by Client - what each client paid in the period (invoice payments,
 * and revenue entered against them), the most first, against the period
 * before, with their share of the total and what they are on every month in
 * recurring invoices. Clients this user may not see are left out.
 */

require_once "includes/inc_report.php";
reportEnforce('income_by_client');

$period = reportPeriod('this_year');
$only_600 = !empty($_GET['over600']);

$income_sql = function ($range_payment, $range_revenue) {
    return "SELECT client_id, client_name, client_abbreviation, SUM(amount) AS v, COUNT(*) AS n, MAX(paid_on) AS last_paid FROM (
            SELECT invoice_client_id AS client_id, payment_amount AS amount, payment_date AS paid_on FROM payments
                JOIN invoices ON invoice_id = payment_invoice_id
                WHERE $range_payment
            UNION ALL
            SELECT revenue_client_id, revenue_amount, revenue_date FROM revenues
                WHERE revenue_client_id > 0 AND " . dashboardRevenueSql() . " AND $range_revenue
        ) AS income
        JOIN clients USING (client_id)
        WHERE 1 = 1 " . clientScopeSql('client_id') . "
        GROUP BY client_id, client_name, client_abbreviation";
};

$clients = [];
$sql = mysqli_query($mysqli, $income_sql(reportRangeSql('payment_date', $period), reportRangeSql('revenue_date', $period)));
while ($row = mysqli_fetch_assoc($sql)) {
    $clients[intval($row['client_id'])] = ['client_id' => intval($row['client_id']), 'client' => $row['client_name'], 'abbr' => $row['client_abbreviation'],
        'paid' => floatval($row['v']), 'payments' => intval($row['n']), 'last_paid' => $row['last_paid'], 'prev' => 0.0, 'mrr' => 0.0];
}
$prev_total = 0.0;
$sql = mysqli_query($mysqli, $income_sql(reportRangeSql('payment_date', $period, true), reportRangeSql('revenue_date', $period, true)));
while ($row = mysqli_fetch_assoc($sql)) {
    $prev_total += floatval($row['v']);
    if (isset($clients[intval($row['client_id'])])) {
        $clients[intval($row['client_id'])]['prev'] = floatval($row['v']);
    }
}
$sql = mysqli_query($mysqli, "SELECT recurring_invoice_client_id AS client_id,
        SUM(CASE WHEN recurring_invoice_frequency = 'year' THEN recurring_invoice_amount / 12 ELSE recurring_invoice_amount END) AS v
    FROM recurring_invoices WHERE recurring_invoice_status = 1 AND recurring_invoice_archived_at IS NULL GROUP BY recurring_invoice_client_id");
while ($row = mysqli_fetch_assoc($sql)) {
    if (isset($clients[intval($row['client_id'])])) {
        $clients[intval($row['client_id'])]['mrr'] = floatval($row['v']);
    }
}

$total = array_sum(array_column($clients, 'paid'));
if ($only_600) {
    $clients = array_filter($clients, function ($c) { return $c['paid'] >= 600; });
}
uasort($clients, function ($a, $b) { return $b['paid'] <=> $a['paid']; });
foreach ($clients as &$row) {
    $row['share'] = reportShare($row['paid'], $total, 1);
    $row['change'] = reportChange($row['paid'], $row['prev']);
}
unset($row);
$clients = array_values($clients);

$columns = [
    'client' => ['label' => 'Client', 'html' => function ($row) {
        return reportClientCell($row['client_id'], $row['client'], $row['abbr']);
    }],
    'paid' => ['label' => 'Paid', 'type' => 'money', 'class' => 'fw-semibold', 'html' => function ($row) use ($period) {
        return '<a class="itflow-report-amount" href="/agent/income.php?client_id=' . $row['client_id'] . '&canned_date=custom&dtf=' . $period['start'] . '&dtt=' . $period['end'] . '">' . reportMoney($row['paid']) . '</a>';
    }],
    'share' => ['label' => 'Share', 'type' => 'percent', 'html' => function ($row) {
        return '<div class="d-flex align-items-center justify-content-end gap-2"><div class="itflow-report-meter d-none d-lg-flex" style="width: 5rem"><span style="width: ' . min(100, floatval($row['share'])) . '%"></span></div>'
            . '<span class="small text-body-secondary">' . reportPercent($row['share']) . '</span></div>';
    }],
    'prev' => ['label' => 'Period before', 'type' => 'money', 'class' => 'text-body-secondary'],
    'change' => ['label' => 'Change', 'type' => 'percent', 'csv' => false, 'html' => function ($row) {
        return $row['change'] === null ? '<span class="itflow-report-zero">New</span>' : '<span class="small">' . reportChangeHtml($row['paid'], $row['prev'], 'up') . '</span>';
    }],
    'payments' => ['label' => 'Payments', 'type' => 'count'],
    'mrr' => ['label' => 'Recurring / month', 'type' => 'money', 'html' => function ($row) {
        return $row['mrr'] > 0 ? reportMoney($row['mrr']) : '<span class="itflow-report-zero">–</span>';
    }],
    'last_paid' => ['label' => 'Last paid', 'type' => 'date', 'class' => 'text-nowrap'],
];
$totals = ['client' => 'Total', 'paid' => array_sum(array_column($clients, 'paid')), 'prev' => array_sum(array_column($clients, 'prev')),
    'payments' => array_sum(array_column($clients, 'payments')), 'mrr' => array_sum(array_column($clients, 'mrr'))];

if (reportExporting()) {
    reportCsv('Income by Client ' . $period['start'] . ' to ' . $period['end'], $columns, $clients, $totals);
}

require_once "includes/inc_report_layout.php";

$top = array_slice($clients, 0, 12);
$top_five_share = reportShare(array_sum(array_column(array_slice($clients, 0, 5), 'paid')), $total);

?>

<div class="itflow-report">

    <?php reportHeader('income_by_client', [
        'period' => $period,
        'years' => reportPastYears("SELECT DISTINCT YEAR(payment_date) AS y FROM payments"),
        'controls' => reportSwitchControl('over600', $only_600, 'Only clients who paid 600 or more'),
    ]); ?>

    <?= reportStats([
        ['label' => 'Paid by clients', 'value' => reportMoney($total), 'sub' => reportChangeHtml($total, $prev_total, 'up', $period['compare'])],
        ['label' => 'Clients who paid', 'value' => reportCount(count($clients)), 'sub' => '<span class="text-body-tertiary">' . ($only_600 ? 'Paid 600 or more' : 'In the period') . '</span>'],
        ['label' => 'Average per client', 'value' => count($clients) ? reportMoney(array_sum(array_column($clients, 'paid')) / count($clients)) : '-', 'sub' => '<span class="text-body-tertiary">Over the period</span>'],
        ['label' => 'Top five', 'value' => reportPercent($top_five_share), 'sub' => '<span class="text-body-tertiary">of income - how much rides on a few clients</span>',
            'tone' => $top_five_share !== null && $top_five_share >= 60 ? 'warning' : ''],
    ]) ?>

    <?php if ($top) { ?>
        <?= reportSectionOpen('Who paid the most', escapeHtml($period['range'])) ?>
            <div class="card-body">
                <?= reportChart([
                    'type' => 'bar',
                    'horizontal' => true,
                    'labels' => array_map(function ($c) { return $c['client']; }, $top),
                    'series' => [
                        ['label' => 'This period', 'data' => array_map(function ($c) { return round($c['paid'], 2); }, $top), 'tone' => 'accent'],
                        ['label' => 'Period before', 'data' => array_map(function ($c) { return round($c['prev'], 2); }, $top), 'tone' => 'faint'],
                    ],
                    'format' => 'money',
                ], '', 'What each of the biggest clients paid, this period and the period before') ?>
            </div>
        </div>
    <?php } ?>

    <?= reportSectionOpen('By client', 'Most paid first') ?>
        <?= reportTable($columns, $clients, ['empty' => 'No client paid anything in this period.', 'totals' => $totals]) ?>
        <div class="card-footer small text-body-secondary">
            Invoice payments by the date they arrived, plus revenue entered against the client. "Period before" is <?= escapeHtml($period['compare']) ?>.
        </div>
    </div>

</div>

<?php require_once "../../includes/footer.php"; ?>
<script src="/js/reports.js"></script>
