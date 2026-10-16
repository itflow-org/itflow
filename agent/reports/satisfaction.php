<?php

/*
 * Customer Satisfaction - how contacts rated their tickets once closed (the
 * Good / Bad buttons in the client portal), for tickets closed in the period:
 * the share rated good, how many get rated at all, the trend, by agent and by
 * client, what the rating follows (a late first reply, a slow fix) and every
 * bad rating so someone can follow up.
 */

require_once "includes/inc_report.php";
reportEnforce('satisfaction');

$period = reportPeriod('last_90');
$client_id = reportClientId();
$agent_id = reportAgentId();
$closed_sql = reportTicketSql($client_id, $agent_id) . " AND tickets.ticket_closed_at IS NOT NULL";
$filter = $closed_sql . " AND " . reportRangeSql('tickets.ticket_closed_at', $period);

const SATISFACTION_COLUMNS = "COUNT(tickets.ticket_id) AS closed,
    SUM(tickets.ticket_feedback IS NOT NULL AND tickets.ticket_feedback != '') AS rated,
    SUM(tickets.ticket_feedback = 'Good') AS good, SUM(tickets.ticket_feedback = 'Bad') AS bad";

/** Counts and shares from a row of SATISFACTION_COLUMNS. */
function satisfactionFigures($row) {
    $out = [];
    foreach (['closed', 'rated', 'good', 'bad'] as $key) {
        $out[$key] = intval($row[$key] ?? 0);
    }
    $out['score'] = reportShare($out['good'], $out['good'] + $out['bad'], 1);
    $out['rated_percent'] = reportShare($out['rated'], $out['closed'], 1);
    return $out;
}

$grouped = function ($group_sql, $where, $join = '') {
    global $mysqli;
    $rows = [];
    $sql = mysqli_query($mysqli, "SELECT $group_sql AS k, " . SATISFACTION_COLUMNS . " FROM tickets $join WHERE $where GROUP BY k");
    while ($row = mysqli_fetch_assoc($sql)) {
        $rows[(string) $row['k']] = satisfactionFigures($row);
    }
    return $rows;
};

$overall = satisfactionFigures(mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT " . SATISFACTION_COLUMNS . " FROM tickets WHERE $filter")));
$previous = satisfactionFigures(mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT " . SATISFACTION_COLUMNS . " FROM tickets
    WHERE $closed_sql AND " . reportRangeSql('tickets.ticket_closed_at', $period, true))));
$other = $overall['rated'] - $overall['good'] - $overall['bad'];

// Over the period - a day's two or three ratings make a jumpy line, so weeks at the least
$buckets = reportBuckets($period);
if ($buckets['unit'] === 'day') {
    $buckets = reportBuckets(['start' => $period['start'], 'end' => $period['end'], 'days' => 60]);
}
$by_bucket = $grouped(reportBucketSql('tickets.ticket_closed_at', $buckets['unit']), $filter);

// By agent
$names = [];
$sql = mysqli_query($mysqli, "SELECT user_id, user_name, user_avatar FROM users WHERE user_type = 1");
while ($row = mysqli_fetch_assoc($sql)) {
    $names[intval($row['user_id'])] = $row;
}
$agent_rows = [];
foreach ($grouped('tickets.ticket_assigned_to', $filter) as $user_id => $figures) {
    $user_id = intval($user_id);
    $agent_rows[] = ['user_id' => $user_id, 'agent' => $names[$user_id]['user_name'] ?? '', 'avatar' => $names[$user_id]['user_avatar'] ?? ''] + $figures;
}
usort($agent_rows, function ($a, $b) { return $b['rated'] <=> $a['rated'] ?: $b['closed'] <=> $a['closed']; });

// By client - the most bad ratings first, as those are the calls to make
$client_rows = [];
$sql = mysqli_query($mysqli, "SELECT client_id, client_name, client_abbreviation, " . SATISFACTION_COLUMNS . "
    FROM tickets JOIN clients ON tickets.ticket_client_id = client_id WHERE $filter GROUP BY client_id, client_name, client_abbreviation");
while ($row = mysqli_fetch_assoc($sql)) {
    $client_rows[] = ['client_id' => intval($row['client_id']), 'client' => $row['client_name'], 'abbr' => $row['client_abbreviation']] + satisfactionFigures($row);
}
usort($client_rows, function ($a, $b) {
    return $b['bad'] <=> $a['bad'] ?: ($a['score'] ?? 101) <=> ($b['score'] ?? 101) ?: $b['rated'] <=> $a['rated'];
});

// What the rating follows - the first reply against its SLA, and how long the fix took
$drivers = [];
$reply = $grouped('tickets.ticket_response_sla_met', $filter . " AND tickets.ticket_sla_id > 0");
foreach (['1' => 'First reply in time', '0' => 'First reply late'] as $key => $label) {
    if (isset($reply[$key])) {
        $drivers[] = ['label' => $label, 'group' => 'First reply against the SLA'] + $reply[$key];
    }
}
$fix = $grouped("CASE WHEN tickets.ticket_resolved_at IS NULL THEN 'none'
    WHEN TIMESTAMPDIFF(HOUR, tickets.ticket_created_at, tickets.ticket_resolved_at) < 24 THEN 'day'
    WHEN TIMESTAMPDIFF(HOUR, tickets.ticket_created_at, tickets.ticket_resolved_at) < 72 THEN 'three'
    WHEN TIMESTAMPDIFF(HOUR, tickets.ticket_created_at, tickets.ticket_resolved_at) < 168 THEN 'week' ELSE 'longer' END", $filter);
foreach (['day' => 'Resolved within a day', 'three' => 'Resolved in 1 to 3 days', 'week' => 'Resolved in 3 to 7 days', 'longer' => 'Resolved after a week'] as $key => $label) {
    if (isset($fix[$key])) {
        $drivers[] = ['label' => $label, 'group' => 'Time to resolve'] + $fix[$key];
    }
}

$driver_rows = [];
foreach ($drivers as $driver) {
    if (!$driver_rows || end($driver_rows)['group'] !== $driver['group']) {
        $driver_rows[] = ['_heading' => $driver['group'], 'group' => $driver['group']];
    }
    $driver_rows[] = $driver;
}

// Every bad rating, newest first
$bad_rows = [];
$sql = mysqli_query($mysqli, "SELECT tickets.ticket_id, ticket_prefix, ticket_number, ticket_subject, ticket_priority, ticket_client_id, ticket_assigned_to,
        ticket_created_at, ticket_first_response_at, ticket_resolved_at, ticket_closed_at, ticket_sla_id, ticket_response_sla_met, ticket_resolution_sla_met,
        client_name, client_abbreviation, contact_name, user_name, user_avatar,
        TIMESTAMPDIFF(SECOND, ticket_created_at, ticket_first_response_at) AS reply_seconds,
        TIMESTAMPDIFF(SECOND, ticket_created_at, COALESCE(ticket_resolved_at, ticket_closed_at)) AS resolve_seconds,
        (SELECT SUM(TIME_TO_SEC(ticket_reply_time_worked)) FROM ticket_replies WHERE ticket_reply_ticket_id = tickets.ticket_id AND ticket_reply_archived_at IS NULL) AS worked_seconds
    FROM tickets
    LEFT JOIN clients ON ticket_client_id = client_id
    LEFT JOIN contacts ON ticket_contact_id = contact_id
    LEFT JOIN users ON ticket_assigned_to = user_id
    WHERE $filter AND tickets.ticket_feedback = 'Bad'
    ORDER BY ticket_closed_at DESC");
while ($row = mysqli_fetch_assoc($sql)) {
    $bad_rows[] = $row;
}

$figure_columns = [
    'closed' => ['label' => 'Closed', 'type' => 'count'],
    'rated' => ['label' => 'Rated', 'type' => 'count'],
    'rated_percent' => ['label' => 'Rated share', 'type' => 'percent', 'class' => 'text-body-secondary', 'title' => 'Closed tickets the contact rated'],
    'good' => ['label' => 'Good', 'type' => 'count'],
    'bad' => ['label' => 'Bad', 'type' => 'count', 'html' => function ($row) {
        return $row['bad'] ? '<span class="text-danger-emphasis fw-semibold">' . $row['bad'] . '</span>' : '<span class="itflow-report-zero">–</span>';
    }],
    'score' => ['label' => 'Satisfaction', 'type' => 'percent', 'title' => 'Good ratings, of the good and bad', 'html' => function ($row) {
        return reportRateHtml($row['score']);
    }],
];
$agent_columns = ['agent' => ['label' => 'Assigned to', 'csv' => function ($row) { return $row['user_id'] ? $row['agent'] : 'Unassigned'; }, 'html' => function ($row) {
    return formatAssignee($row['user_id'], $row['agent'], $row['avatar'], true);
}]] + $figure_columns;
$client_columns = ['client' => ['label' => 'Client', 'html' => function ($row) {
    return reportClientCell($row['client_id'], $row['client'], $row['abbr'], 'tickets.php');
}]] + $figure_columns;
$driver_columns = [
    'label' => ['label' => 'How the ticket went', 'sort' => false],
    'rated' => ['label' => 'Rated', 'type' => 'count', 'sort' => false],
    'good' => ['label' => 'Good', 'type' => 'count', 'sort' => false],
    'bad' => $figure_columns['bad'] + ['sort' => false],
    'score' => $figure_columns['score'] + ['sort' => false],
    'meter' => ['label' => '', 'sort' => false, 'csv' => false, 'class' => 'w-25', 'html' => function ($row) {
        return $row['score'] === null ? '' : '<div class="progress itflow-report-meter" role="presentation"><div class="progress-bar bg-success" style="width: ' . round($row['score']) . '%"></div>'
            . '<div class="progress-bar bg-danger" style="width: ' . (100 - round($row['score'])) . '%"></div></div>';
    }],
];
$bad_columns = [
    'ticket' => ['label' => 'Ticket', 'class' => 'itflow-report-text', 'csv' => function ($row) { return ticketReference($row) . ' ' . $row['ticket_subject']; }, 'html' => function ($row) {
        return reportPriorityDot($row['ticket_priority']) . reportTicketLink($row);
    }],
    'client_name' => ['label' => 'Client', 'html' => function ($row) {
        return reportClientCell($row['ticket_client_id'], $row['client_name'] ?? '', $row['client_abbreviation'] ?? '');
    }],
    'contact_name' => ['label' => 'Rated by', 'html' => function ($row) {
        return $row['contact_name'] ? '<span class="text-nowrap">' . escapeHtml($row['contact_name']) . '</span>' : '<span class="text-body-tertiary">-</span>';
    }],
    'user_name' => ['label' => 'Assigned to', 'csv' => function ($row) { return $row['user_name'] ?: 'Unassigned'; }, 'html' => function ($row) {
        return formatAssignee($row['ticket_assigned_to'], $row['user_name'] ?? '', $row['user_avatar'] ?? '', true);
    }],
    'reply_seconds' => ['label' => 'First reply', 'type' => 'duration', 'html' => function ($row) {
        if ($row['reply_seconds'] === null) {
            return '<span class="text-body-tertiary">-</span>';
        }
        $late = $row['ticket_response_sla_met'] !== null && intval($row['ticket_response_sla_met']) === 0;
        return '<span class="' . ($late ? 'text-danger-emphasis fw-semibold' : '') . '"' . ($late ? ' title="Missed the response target"' : '') . '>'
            . reportDuration($row['reply_seconds']) . ($late ? ' <i class="fas fa-fw fa-exclamation-circle small"></i>' : '') . '</span>';
    }],
    'resolve_seconds' => ['label' => 'To resolve', 'type' => 'duration', 'html' => function ($row) {
        $late = $row['ticket_resolution_sla_met'] !== null && intval($row['ticket_resolution_sla_met']) === 0;
        return '<span class="' . ($late ? 'text-danger-emphasis fw-semibold' : '') . '"' . ($late ? ' title="Missed the resolution target"' : '') . '>'
            . reportDuration($row['resolve_seconds']) . ($late ? ' <i class="fas fa-fw fa-exclamation-circle small"></i>' : '') . '</span>';
    }],
    'worked_seconds' => ['label' => 'Time logged', 'type' => 'hours', 'decimals' => 1],
    'ticket_closed_at' => ['label' => 'Closed', 'type' => 'date'],
];
$month_columns = ['when' => ['label' => ucfirst($buckets['unit']), 'sort' => false]] + $figure_columns;
$month_rows = [];
foreach ($buckets['keys'] as $i => $key) {
    if (isset($by_bucket[$key])) {
        $month_rows[] = ['when' => $buckets['labels'][$i]] + $by_bucket[$key];
    }
}

if (reportExporting('agents')) {
    reportCsv('Satisfaction by Agent', $agent_columns, $agent_rows, ['agent' => 'Total'] + $overall);
}
if (reportExporting('clients')) {
    reportCsv('Satisfaction by Client', $client_columns, $client_rows, ['client' => 'Total'] + $overall);
}
if (reportExporting('bad')) {
    reportCsv('Bad Ratings', $bad_columns, $bad_rows);
}
if (reportExporting()) {
    reportCsv('Satisfaction by ' . ucfirst($buckets['unit']), $month_columns, $month_rows, ['when' => 'Total'] + $overall);
}

require_once "includes/inc_report_layout.php";

$bad_clients = count(array_unique(array_column($bad_rows, 'ticket_client_id')));
$was = function ($value) use ($period) {
    return $value === null ? '' : 'Was <span class="text-body">' . reportPercent($value) . '</span> <span class="text-body-tertiary">' . escapeHtml($period['compare']) . '</span>';
};

?>

<div class="itflow-report">

    <?php reportHeader('satisfaction', [
        'period' => $period,
        'years' => reportPastYears("SELECT DISTINCT YEAR(ticket_closed_at) AS y FROM tickets WHERE ticket_feedback IS NOT NULL AND ticket_closed_at IS NOT NULL"),
        'client' => $client_id,
        'agent' => $agent_id,
        'export' => ['months' => 'By ' . $buckets['unit'], 'agents' => 'By agent', 'clients' => 'By client', 'bad' => 'Bad ratings'],
    ]); ?>

    <?= reportStats([
        ['label' => 'Satisfaction', 'value_html' => reportRateHtml($overall['score']), 'title' => 'Good ratings, of the good and bad', 'sub' => $was($previous['score'])],
        ['label' => 'Ratings', 'value' => reportCount($overall['rated']),
            'sub' => $overall['closed'] ? reportPercent($overall['rated_percent']) . ' <span class="text-body-tertiary">of ' . reportCount($overall['closed']) . ' closed tickets</span>' : ''],
        ['label' => 'Rated good', 'value' => reportCount($overall['good']), 'tone' => $overall['good'] ? 'success' : '',
            'sub' => $previous['rated'] ? reportChangeHtml($overall['good'], $previous['good'], 'up', $period['compare']) : ''],
        ['label' => 'Rated bad', 'value' => reportCount($overall['bad']), 'tone' => $overall['bad'] ? 'danger' : 'success',
            'sub' => $overall['bad'] ? reportCount($bad_clients) . ' <span class="text-body-tertiary">' . ($bad_clients === 1 ? 'client' : 'clients') . ' to follow up</span>' : '<span class="text-body-tertiary">Nothing to follow up</span>'],
    ]) ?>

    <?php if (!$overall['rated']) { ?>
        <div class="card"><div class="card-body">
            <?= reportEmpty('No ticket closed in this period was rated. Contacts rate a ticket Good or Bad in the client portal once it is closed.', 'fa-smile', 'secondary') ?>
        </div></div>
    <?php } else { ?>

        <?= reportSectionOpen('Ratings over the period', 'Bars are ratings, the line the share rated good') ?>
            <div class="card-body">
                <?php
                $keys = $buckets['keys'];
                echo reportChart([
                    'type' => 'bar',
                    'labels' => $buckets['labels'],
                    'stacked' => true,
                    'series' => [
                        ['label' => 'Good', 'data' => array_map(function ($k) use ($by_bucket) { return $by_bucket[$k]['good'] ?? 0; }, $keys), 'tone' => 'success', 'stack' => 'ratings'],
                        ['label' => 'Bad', 'data' => array_map(function ($k) use ($by_bucket) { return $by_bucket[$k]['bad'] ?? 0; }, $keys), 'tone' => 'danger', 'stack' => 'ratings'],
                        ['label' => 'Satisfaction', 'data' => array_map(function ($k) use ($by_bucket) { return $by_bucket[$k]['score'] ?? null; }, $keys), 'tone' => 'accent', 'type' => 'line', 'axis' => 'y2'],
                    ],
                    'format' => 'count',
                    'format2' => 'percent',
                    'max2' => 100,
                ], '', 'Good and bad ratings and the share rated good over the period');
                ?>
            </div>
        </div>

        <?php if ($drivers) { ?>
            <?= reportSectionOpen('What the rating follows', 'Satisfaction by how the ticket went') ?>
                <?= reportTable($driver_columns, $driver_rows, ['sortable' => false]) ?>
            </div>
        <?php } ?>

        <?php if (!$agent_id) { ?>
            <?= reportSectionOpen('By agent', 'Who the ticket was assigned to') ?>
                <?= reportTable($agent_columns, $agent_rows, ['totals' => ['agent' => 'Total'] + $overall, 'totals_html' => ['score' => reportRateHtml($overall['score'])]]) ?>
            </div>
        <?php } ?>

        <?php if (!$client_id) { ?>
            <?= reportSectionOpen('By client', 'The most bad ratings first') ?>
                <?= reportTable($client_columns, $client_rows, ['limit' => 50, 'export_table' => 'clients', 'totals' => ['client' => 'Total'] + $overall, 'totals_html' => ['score' => reportRateHtml($overall['score'])]]) ?>
            </div>
        <?php } ?>

        <?= reportSectionOpen('Bad ratings', $bad_rows ? 'Newest first - worth a call' : '') ?>
            <?= reportTable($bad_columns, $bad_rows, ['limit' => 100, 'export_table' => 'bad', 'empty' => reportEmpty('No bad ratings in this period.', 'fa-smile', 'success')]) ?>
            <div class="card-footer small text-body-secondary">
                Tickets closed in the period. Contacts rate a ticket Good or Bad in the client portal once it is closed; satisfaction is the share rated good.
                Times run on the clock from when the ticket was raised; a red time missed its SLA target.
                <?php if ($other > 0) { ?>
                    <?= reportCount($other) ?> <?= $other === 1 ? 'rating has' : 'ratings have' ?> another value and <?= $other === 1 ? 'is' : 'are' ?> counted as rated, but neither good nor bad.
                <?php } ?>
            </div>
        </div>

    <?php } ?>

</div>

<?php require_once "../../includes/footer.php"; ?>
<script src="/js/reports.js"></script>
