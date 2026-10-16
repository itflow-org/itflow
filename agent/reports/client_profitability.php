<?php

/*
 * Client Profitability - for each client, what they paid in the period
 * against the time spent on them: income less the expenses filed against the
 * client, divided by the hours logged on their tickets. That effective
 * hourly rate is compared with a target (your default hourly rate unless
 * another is entered), so a flat-fee client who takes more time than they
 * pay for stands out.
 *
 * Income is cash in (invoice payments and the client's revenue); hours are
 * time logged on ticket replies in the period, billable or not.
 */

require_once "includes/inc_report.php";
reportEnforce('client_profitability');

$period = reportPeriod('last_12');
$target = isset($_GET['target']) && $_GET['target'] !== '' ? round(max(0, floatval($_GET['target'])), 2) : round(floatval($config_default_hourly_rate), 2);
$with_tickets = reportFeatureOn('ticketing');

$clients = [];
$client = function ($row) use (&$clients) {
    $id = intval($row['client_id']);
    if (!isset($clients[$id])) {
        $clients[$id] = ['client_id' => $id, 'client' => $row['client_name'], 'abbr' => $row['client_abbreviation'] ?? '',
            'income' => 0.0, 'costs' => 0.0, 'seconds' => 0, 'billable_seconds' => 0, 'tickets' => 0, 'mrr' => 0.0];
    }
    return $id;
};
$scope = clientScopeSql('client_id');

// Money in
$sql = mysqli_query($mysqli, "SELECT client_id, client_name, client_abbreviation, SUM(amount) AS v FROM (
        SELECT invoice_client_id AS client_id, payment_amount AS amount FROM payments
            JOIN invoices ON invoice_id = payment_invoice_id
            WHERE " . reportRangeSql('payment_date', $period) . "
        UNION ALL
        SELECT revenue_client_id, revenue_amount FROM revenues
            WHERE revenue_client_id > 0 AND " . dashboardRevenueSql() . " AND " . reportRangeSql('revenue_date', $period) . "
    ) AS income
    JOIN clients USING (client_id)
    WHERE 1 = 1 $scope
    GROUP BY client_id, client_name, client_abbreviation");
while ($row = mysqli_fetch_assoc($sql)) {
    $clients[$client($row)]['income'] = floatval($row['v']);
}

// Money out on their behalf
$sql = mysqli_query($mysqli, "SELECT client_id, client_name, client_abbreviation, SUM(expense_amount) AS v
    FROM expenses JOIN clients ON expense_client_id = client_id
    WHERE expense_vendor_id > 0 AND " . reportRangeSql('expense_date', $period) . " $scope
    GROUP BY client_id, client_name, client_abbreviation");
while ($row = mysqli_fetch_assoc($sql)) {
    $clients[$client($row)]['costs'] = floatval($row['v']);
}

// Time spent, and tickets raised
if ($with_tickets) {
    $sql = mysqli_query($mysqli, "SELECT client_id, client_name, client_abbreviation,
            SUM(TIME_TO_SEC(ticket_reply_time_worked)) AS s,
            SUM(CASE WHEN ticket_billable = 1 THEN TIME_TO_SEC(ticket_reply_time_worked) ELSE 0 END) AS b
        FROM ticket_replies
        JOIN tickets ON ticket_reply_ticket_id = ticket_id
        JOIN clients ON ticket_client_id = client_id
        WHERE ticket_reply_archived_at IS NULL AND ticket_reply_time_worked IS NOT NULL
        AND " . reportRangeSql('ticket_reply_created_at', $period) . " $scope
        GROUP BY client_id, client_name, client_abbreviation");
    while ($row = mysqli_fetch_assoc($sql)) {
        $id = $client($row);
        $clients[$id]['seconds'] = intval($row['s']);
        $clients[$id]['billable_seconds'] = intval($row['b']);
    }
    $sql = mysqli_query($mysqli, "SELECT client_id, client_name, client_abbreviation, COUNT(ticket_id) AS n
        FROM tickets JOIN clients ON ticket_client_id = client_id
        WHERE " . reportRangeSql('ticket_created_at', $period) . " $scope
        GROUP BY client_id, client_name, client_abbreviation");
    while ($row = mysqli_fetch_assoc($sql)) {
        if (isset($clients[intval($row['client_id'])])) {
            $clients[intval($row['client_id'])]['tickets'] = intval($row['n']);
        }
    }
}

// What they are on now, every month
$sql = mysqli_query($mysqli, "SELECT recurring_invoice_client_id AS client_id,
        SUM(" . recurringInvoiceMonthlySql() . ") AS v
    FROM recurring_invoices WHERE recurring_invoice_status = 1 AND recurring_invoice_archived_at IS NULL GROUP BY recurring_invoice_client_id");
while ($row = mysqli_fetch_assoc($sql)) {
    if (isset($clients[intval($row['client_id'])])) {
        $clients[intval($row['client_id'])]['mrr'] = floatval($row['v']);
    }
}

foreach ($clients as &$row) {
    $row['net'] = $row['income'] - $row['costs'];
    $row['hours'] = $row['seconds'] / 3600;
    $row['rate'] = $row['seconds'] > 0 ? $row['net'] / ($row['seconds'] / 3600) : null;
    $row['flag'] = '';
    if ($row['seconds'] >= 1800 && $row['net'] <= 0) {
        $row['flag'] = 'unpaid';
    } elseif ($row['rate'] !== null && $target > 0 && $row['rate'] < $target && $row['seconds'] >= 1800) {
        $row['flag'] = 'below';
    }
}
unset($row);

// Lowest rate first - the ones to look at - then those with no time logged
usort($clients, function ($a, $b) {
    if ($a['rate'] === null || $b['rate'] === null) {
        return ($a['rate'] === null) <=> ($b['rate'] === null) ?: $b['net'] <=> $a['net'];
    }
    return $a['rate'] <=> $b['rate'];
});

$columns = [
    'client' => ['label' => 'Client', 'html' => function ($row) {
        return reportClientCell($row['client_id'], $row['client'], $row['abbr']);
    }],
    'income' => ['label' => 'Income', 'type' => 'money'],
    'costs' => ['label' => 'Direct costs', 'type' => 'money', 'title' => 'Expenses filed against the client', 'html' => function ($row) {
        return $row['costs'] > 0 ? reportMoney($row['costs']) : '<span class="itflow-report-zero">–</span>';
    }],
    'net' => ['label' => 'Net', 'type' => 'money', 'class' => 'fw-semibold'],
];
if ($with_tickets) {
    $columns['seconds'] = ['label' => 'Hours', 'type' => 'hours', 'title' => 'Time logged on their tickets in the period'];
    $columns['billable_share'] = ['label' => 'Billable', 'type' => 'percent', 'title' => 'Share of the hours on billable tickets', 'class' => 'text-body-secondary'];
    $columns['rate'] = ['label' => 'Per hour', 'type' => 'money', 'title' => 'Net divided by hours - your effective hourly rate', 'html' => function ($row) use ($target) {
        if ($row['rate'] === null) {
            return '<span class="itflow-report-zero">No time</span>';
        }
        $tone = $row['flag'] ? 'text-danger fw-semibold' : ($target > 0 && $row['rate'] >= $target ? 'text-success-emphasis fw-semibold' : 'fw-semibold');
        return '<span class="' . $tone . '">' . reportMoney($row['rate']) . '</span>';
    }];
    $columns['tickets'] = ['label' => 'Tickets', 'type' => 'count'];
}
$columns['mrr'] = ['label' => 'Recurring / month', 'type' => 'money', 'html' => function ($row) {
    return $row['mrr'] > 0 ? reportMoney($row['mrr']) : '<span class="itflow-report-zero">–</span>';
}];
$columns['flag'] = ['label' => '', 'sort' => false, 'csv_label' => 'Flag', 'csv' => function ($row) {
    return ['unpaid' => 'Time but no income', 'below' => 'Below target'][$row['flag']] ?? '';
}, 'html' => function ($row) {
    if ($row['flag'] === 'unpaid') {
        return '<span class="badge rounded-pill bg-danger-subtle text-danger-emphasis border border-danger-subtle fw-normal">Time, no income</span>';
    }
    if ($row['flag'] === 'below') {
        return '<span class="badge rounded-pill bg-warning-subtle text-warning-emphasis border border-warning-subtle fw-normal">Below target</span>';
    }
    return '';
}];
foreach ($clients as &$row) {
    $row['billable_share'] = reportShare($row['billable_seconds'], $row['seconds']);
}
unset($row);

$income = array_sum(array_column($clients, 'income'));
$costs = array_sum(array_column($clients, 'costs'));
$seconds = array_sum(array_column($clients, 'seconds'));
$overall_rate = $seconds ? ($income - $costs) / ($seconds / 3600) : null;
$totals = ['client' => 'Total', 'income' => $income, 'costs' => $costs, 'net' => $income - $costs, 'seconds' => $seconds,
    'rate' => $overall_rate, 'tickets' => array_sum(array_column($clients, 'tickets')), 'mrr' => array_sum(array_column($clients, 'mrr')),
    'billable_share' => reportShare(array_sum(array_column($clients, 'billable_seconds')), $seconds)];

if (reportExporting()) {
    reportCsv('Client Profitability ' . $period['start'] . ' to ' . $period['end'], $columns, $clients, $totals);
}

require_once "includes/inc_report_layout.php";

$flagged = array_filter($clients, function ($row) { return $row['flag'] !== ''; });
$chart_rows = array_values(array_filter($clients, function ($row) { return $row['rate'] !== null && $row['seconds'] >= 1800; }));
usort($chart_rows, function ($a, $b) { return $b['seconds'] <=> $a['seconds']; });
$chart_rows = array_slice($chart_rows, 0, 15);
usort($chart_rows, function ($a, $b) { return $a['rate'] <=> $b['rate']; });

$target_control = '<div class="col-sm-6 col-lg-2"><div class="input-group" title="The hourly rate you aim to earn - your default hourly rate unless you enter another">'
    . '<span class="input-group-text"><i class="fas fa-fw fa-bullseye"></i></span>'
    . '<input type="text" inputmode="decimal" class="form-control" name="target" value="' . ($target > 0 ? escapeHtml(number_format($target, 2, '.', '')) : '') . '" placeholder="Target per hour" aria-label="Target per hour" data-report-autosubmit>'
    . '</div></div>';

?>

<div class="itflow-report">

    <?php reportHeader('client_profitability', [
        'period' => $period,
        'years' => reportPastYears("SELECT DISTINCT YEAR(payment_date) AS y FROM payments"),
        'controls' => $target_control,
    ]); ?>

    <?= reportStats([
        ['label' => 'Income from clients', 'value' => reportMoney($income), 'sub' => $costs > 0 ? escapeHtml(reportMoney($costs)) . ' <span class="text-body-tertiary">in direct costs</span>' : '<span class="text-body-tertiary">No direct costs filed</span>'],
        ['label' => 'Hours worked', 'value' => $with_tickets ? reportHours($seconds) : '-', 'sub' => $with_tickets ? escapeHtml(reportPercent(reportShare(array_sum(array_column($clients, 'billable_seconds')), $seconds))) . ' <span class="text-body-tertiary">on billable tickets</span>' : '<span class="text-body-tertiary">Ticketing is off</span>'],
        ['label' => 'Earned per hour', 'value' => $overall_rate === null ? '-' : reportMoney($overall_rate), 'tone' => $overall_rate !== null && $target > 0 ? ($overall_rate >= $target ? 'success' : 'danger') : '',
            'sub' => $target > 0 ? 'Target <span class="text-body">' . reportMoney($target) . '</span>' : '<span class="text-body-tertiary">Enter a target above</span>'],
        ['label' => 'Clients to look at', 'value' => reportCount(count($flagged)), 'tone' => $flagged ? 'danger' : 'success',
            'sub' => '<span class="text-body-tertiary">Below target, or time with no income</span>'],
    ]) ?>

    <?php if ($with_tickets && $chart_rows) { ?>
        <?= reportSectionOpen('Earned per hour', 'The ' . count($chart_rows) . ' clients you spent the most time on - red is below target') ?>
            <div class="card-body">
                <?= reportChart([
                    'type' => 'bar',
                    'horizontal' => true,
                    'labels' => array_map(function ($r) { return $r['client']; }, $chart_rows),
                    'series' => [['label' => 'Per hour', 'data' => array_map(function ($r) { return round($r['rate'], 2); }, $chart_rows),
                        'tones' => array_map(function ($r) use ($target) { return $r['flag'] ? 'danger' : ($target > 0 ? 'success' : 'accent'); }, $chart_rows)]],
                    'format' => 'money',
                    'legend' => false,
                ], '', 'What each client earned per hour of work') ?>
            </div>
        </div>
    <?php } ?>

    <?= reportSectionOpen('By client', 'Lowest earning per hour first', 'itflow-report-long') ?>
        <?= reportTable($columns, $clients, ['empty' => 'No income or time logged for any client in this period.', 'totals' => $totals]) ?>
        <div class="card-footer small text-body-secondary">
            Net is income (payments and revenue) less the expenses filed against the client. Per hour is net divided by the hours logged on their tickets in the same period -
            a client who pays in one period for work done in another reads high or low; a longer period evens that out.
        </div>
    </div>

</div>

<?php require_once "../../includes/footer.php"; ?>
<script src="/js/reports.js"></script>
