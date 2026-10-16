<?php

/*
 * Mileage - the trips logged in the period: miles by agent and by client,
 * every trip listed, and what it comes to at a rate per mile you enter (your
 * reimbursement or tax-deduction rate - nothing is assumed). Miles count as
 * entered on each trip (the whole trip - both ways for a round trip), the
 * same as the Trips list and the dashboard. A trip's date opens it on the
 * Trips list; its ticket and site show under the purpose.
 */

require_once "includes/inc_report.php";
reportEnforce('mileage');

$period = reportPeriod('this_year');
$client_id = reportClientId();
$agent_id = reportAgentId();
$rate = round(max(0, floatval(str_replace(',', '.', (string) ($_GET['rate'] ?? '')))), 4);

$where = "trip_archived_at IS NULL AND " . reportRangeSql('trip_date', $period)
    . reportClientSql('trip_client_id', $client_id)
    . ($agent_id ? " AND trip_user_id = $agent_id" : '');

$trips = [];
$sql = mysqli_query($mysqli, "SELECT trip_id, trip_date, trip_purpose, trip_source, trip_destination, trip_miles, round_trip,
        trip_user_id, user_name, user_avatar, trip_client_id, client_name, client_abbreviation,
        trip_location_id, location_name, trip_ticket_id, ticket_prefix, ticket_number
    FROM trips
    LEFT JOIN users ON trip_user_id = user_id
    LEFT JOIN clients ON trip_client_id = client_id
    LEFT JOIN locations ON location_id = trip_location_id
    LEFT JOIN tickets ON ticket_id = trip_ticket_id
    WHERE $where
    ORDER BY trip_date DESC, trip_id DESC");
while ($row = mysqli_fetch_assoc($sql)) {
    $row['miles'] = floatval($row['trip_miles']);
    $row['value'] = $rate ? $row['miles'] * $rate : null;
    $trips[] = $row;
}

$miles = array_sum(array_column($trips, 'miles'));
$prev_miles = dashboardSum("SELECT SUM(trip_miles) FROM trips WHERE trip_archived_at IS NULL AND " . reportRangeSql('trip_date', $period, true)
    . reportClientSql('trip_client_id', $client_id) . ($agent_id ? " AND trip_user_id = $agent_id" : ''));

// Grouped by agent and by client
$by_agent = [];
$by_client = [];
foreach ($trips as $trip) {
    $a = intval($trip['trip_user_id']);
    $by_agent[$a] = $by_agent[$a] ?? ['user_id' => $a, 'agent' => $trip['user_name'] ?? '', 'avatar' => $trip['user_avatar'] ?? '', 'trips' => 0, 'miles' => 0.0];
    $by_agent[$a]['trips']++;
    $by_agent[$a]['miles'] += $trip['miles'];
    $c = intval($trip['trip_client_id']);
    $by_client[$c] = $by_client[$c] ?? ['client_id' => $c, 'client' => $c ? $trip['client_name'] : 'No client (our own)', 'abbr' => $trip['client_abbreviation'] ?? '', 'trips' => 0, 'miles' => 0.0];
    $by_client[$c]['trips']++;
    $by_client[$c]['miles'] += $trip['miles'];
}
foreach ($by_agent as &$row) {
    $row['value'] = $rate ? $row['miles'] * $rate : null;
}
unset($row);
foreach ($by_client as &$row) {
    $row['value'] = $rate ? $row['miles'] * $rate : null;
}
unset($row);
usort($by_agent, function ($a, $b) { return $b['miles'] <=> $a['miles']; });
usort($by_client, function ($a, $b) { return $b['miles'] <=> $a['miles']; });

$value_column = ['label' => 'At ' . ($rate ? reportMoney($rate) . ' a mile' : 'your rate'), 'type' => 'money', 'csv_label' => 'Amount'];

$trip_columns = [
    'trip_date' => ['label' => 'Date', 'type' => 'date', 'class' => 'text-nowrap', 'html' => function ($row) {
        // Opens the trip's quick look on the Trips list
        return '<a href="/agent/trips.php?open=' . intval($row['trip_id']) . '">' . escapeHtml(date('M j, Y', strtotime($row['trip_date']))) . '</a>';
    }],
    'user_name' => ['label' => 'Agent', 'html' => function ($row) {
        return reportAgentCell($row['trip_user_id'], $row['user_name'] ?? '', $row['user_avatar'] ?? '', 'No one');
    }],
    'client_name' => ['label' => 'Client', 'html' => function ($row) {
        return reportClientCell($row['trip_client_id'], $row['client_name'] ?? '', $row['client_abbreviation'] ?? '');
    }],
    'trip_purpose' => ['label' => 'Purpose', 'class' => 'itflow-report-text', 'html' => function ($row) {
        $for = [];
        if ($row['ticket_number'] !== null && intval($row['trip_ticket_id'])) {
            $for[] = '<span class="font-monospace">' . escapeHtml(ticketReference($row)) . '</span>';
        }
        if ($row['location_name'] !== null && intval($row['trip_location_id'])) {
            $for[] = '<i class="fas fa-map-marker-alt me-1"></i>' . escapeHtml($row['location_name']);
        }
        return escapeHtml($row['trip_purpose']) . ($for ? '<div class="small text-body-secondary">' . implode(' &middot; ', $for) . '</div>' : '');
    }],

    'route' => ['label' => 'From → to', 'class' => 'small text-body-secondary', 'csv' => function ($row) {
        return $row['trip_source'] . ' → ' . $row['trip_destination'];
    }, 'html' => function ($row) {
        return escapeHtml($row['trip_source']) . ' <i class="fas fa-long-arrow-alt-right mx-1"></i> ' . escapeHtml($row['trip_destination']);
    }],
    'round_trip' => ['label' => 'Round trip', 'csv' => function ($row) {
        return intval($row['round_trip']) ? 'Yes' : 'No';
    }, 'html' => function ($row) {
        return intval($row['round_trip']) ? '<i class="fas fa-sync-alt text-body-secondary" title="Round trip"></i>' : '';
    }, 'class' => 'text-center'],
    'miles' => ['label' => 'Miles', 'type' => 'decimal', 'decimals' => 1],
];
if ($rate) {
    $trip_columns['value'] = $value_column;
}
// The CSV has the site and ticket in columns of their own
$trip_csv_columns = array_slice($trip_columns, 0, 4, true) + [
    'location_name' => ['label' => 'Site', 'csv' => function ($row) {
        return intval($row['trip_location_id']) ? (string) $row['location_name'] : '';
    }],
    'ticket' => ['label' => 'Ticket', 'csv' => function ($row) {
        return $row['ticket_number'] !== null && intval($row['trip_ticket_id']) ? ticketReference($row) : '';
    }],
] + array_slice($trip_columns, 4, null, true);

$agent_columns = [
    'agent' => ['label' => 'Agent', 'html' => function ($row) {
        return reportAgentCell($row['user_id'], $row['agent'], $row['avatar'], 'No one');
    }],
    'trips' => ['label' => 'Trips', 'type' => 'count'],
    'miles' => ['label' => 'Miles', 'type' => 'decimal', 'decimals' => 1],
];
$client_columns = [
    'client' => ['label' => 'Client', 'html' => function ($row) {
        return $row['client_id'] ? reportClientCell($row['client_id'], $row['client'], $row['abbr']) : '<span class="text-body-secondary fst-italic">No client (our own)</span>';
    }],
    'trips' => ['label' => 'Trips', 'type' => 'count'],
    'miles' => ['label' => 'Miles', 'type' => 'decimal', 'decimals' => 1],
];
if ($rate) {
    $agent_columns['value'] = $value_column;
    $client_columns['value'] = $value_column;
}

$totals = ['trips' => count($trips), 'miles' => $miles, 'value' => $rate ? $miles * $rate : null];
$name = 'Mileage ' . $period['start'] . ' to ' . $period['end'];
if (reportExporting('agents')) {
    reportCsv("$name by agent", $agent_columns, $by_agent, ['agent' => 'Total'] + $totals);
}
if (reportExporting('clients')) {
    reportCsv("$name by client", $client_columns, $by_client, ['client' => 'Total'] + $totals);
}
if (reportExporting()) {
    reportCsv($name, $trip_csv_columns, $trips, ['trip_date' => 'Total', 'miles' => $miles, 'value' => $totals['value']]);
}

$buckets = reportBuckets($period);
$miles_series = reportSeries("SELECT " . reportBucketSql('trip_date', $buckets['unit']) . " AS b, SUM(trip_miles) AS v FROM trips WHERE $where GROUP BY b", $buckets['keys']);

require_once "includes/inc_report_layout.php";

$rate_control = '<div class="col-sm-6 col-lg-2"><div class="input-group">'
    . '<span class="input-group-text"><i class="fas fa-fw fa-dollar-sign"></i></span>'
    . '<input type="text" inputmode="decimal" class="form-control" name="rate" value="' . ($rate ? escapeHtml(rtrim(rtrim(number_format($rate, 4, '.', ''), '0'), '.')) : '') . '" placeholder="Rate per mile" aria-label="Rate per mile" data-report-autosubmit title="Your reimbursement rate per mile - press Enter">'
    . '</div></div>';

?>

<div class="itflow-report">

    <?php reportHeader('mileage', [
        'period' => $period,
        'years' => reportPastYears("SELECT DISTINCT YEAR(trip_date) AS y FROM trips"),
        'client' => $client_id,
        'agent' => $agent_id,
        'controls' => $rate_control,
        'export' => ['trips' => 'Every trip', 'agents' => 'By agent', 'clients' => 'By client'],
    ]); ?>

    <?= reportStats([
        ['label' => 'Miles', 'value' => number_format($miles, 1), 'sub' => reportChangeHtml($miles, $prev_miles, 'up', $period['compare'])],
        ['label' => 'Trips', 'value' => reportCount(count($trips)), 'sub' => count($trips) ? reportCount(count(array_filter(array_column($trips, 'round_trip')))) . ' <span class="text-body-tertiary">round trips</span>' : ''],
        ['label' => 'Average trip', 'value' => count($trips) ? number_format($miles / count($trips), 1) . ' mi' : '-', 'sub' => '<span class="text-body-tertiary">Miles a trip</span>'],
        ['label' => $rate ? 'At ' . reportMoney($rate) . ' a mile' : 'Reimbursement', 'value' => $rate ? reportMoney($miles * $rate) : '-',
            'sub' => $rate ? '<span class="text-body-tertiary">Miles × your rate</span>' : '<span class="text-body-tertiary">Enter a rate per mile above</span>'],
    ]) ?>

    <?= reportSectionOpen('Miles driven', escapeHtml($period['range'])) ?>
        <div class="card-body">
            <?= reportChart([
                'type' => 'bar',
                'labels' => $buckets['labels'],
                'series' => [['label' => 'Miles', 'data' => $miles_series, 'tone' => 'accent']],
                'format' => 'decimal',
                'legend' => false,
            ], 'sm', 'Miles driven over the period') ?>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-xl-6">
            <?= reportSectionOpen('By agent', '', 'h-100 mb-0') ?>
                <?= reportTable($agent_columns, $by_agent, ['empty' => 'No trips in this period.', 'totals' => ['agent' => 'Total'] + $totals]) ?>
            </div>
        </div>
        <div class="col-xl-6">
            <?= reportSectionOpen('By client', '', 'h-100 mb-0') ?>
                <?= reportTable($client_columns, $by_client, ['empty' => 'No trips in this period.', 'totals' => ['client' => 'Total'] + $totals]) ?>
            </div>
        </div>
    </div>

    <?= reportSectionOpen('Every trip', reportCount(count($trips)) . ' trip' . (count($trips) === 1 ? '' : 's'), 'itflow-report-long') ?>
        <?= reportTable($trip_columns, $trips, ['empty' => 'No trips in this period.', 'limit' => 250, 'export_table' => 'trips', 'totals' => ['trip_date' => 'Total', 'miles' => $miles, 'value' => $totals['value']]]) ?>
        <div class="card-footer small text-body-secondary">
            Miles count as entered on each trip. Archived trips are left out. <a href="/agent/trips.php">Log a trip</a>
        </div>
    </div>

</div>

<?php require_once "../../includes/footer.php"; ?>
<script src="/js/reports.js"></script>
