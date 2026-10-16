<?php

/*
 * Technician Performance - each agent over the period: time logged and how
 * much of it was on billable tickets, utilization against the hours they
 * were available (working days in the period - the business days and
 * closure days set for SLAs - times the hours in a working day), replies
 * sent, tickets resolved and still open, the typical first reply on their
 * tickets, SLA response compliance and client ratings.
 *
 * Utilization is a guide, not a timesheet - it only counts time logged on
 * ticket replies.
 */

require_once "includes/inc_report.php";
reportEnforce('time_by_tech');

$period = reportPeriod('last_30');
$client_id = reportClientId();
$workday = isset($_GET['hours']) && floatval($_GET['hours']) > 0 ? min(24, round(floatval($_GET['hours']), 2)) : reportWorkdayHours();
$working_days = reportWorkingDays($period['start'], $period['end']);
$capacity = $working_days * $workday * 3600;
$scope = reportClientSql('ticket_client_id', $client_id);

// Every active agent, and anyone else with something in the period
$agents = [];
$sql = mysqli_query($mysqli, "SELECT user_id, user_name, user_avatar, user_status, user_archived_at FROM users WHERE user_type = 1 ORDER BY user_name");
while ($row = mysqli_fetch_assoc($sql)) {
    $agents[intval($row['user_id'])] = ['user_id' => intval($row['user_id']), 'agent' => $row['user_name'], 'avatar' => $row['user_avatar'] ?? '',
        'active' => intval($row['user_status']) === 1 && $row['user_archived_at'] === null,
        'seconds' => 0, 'billable' => 0, 'replies' => 0, 'tickets_touched' => 0, 'resolved' => 0, 'open_now' => 0, 'response' => null,
        'sla_met' => 0, 'sla_missed' => 0, 'good' => 0, 'bad' => 0];
}

$sql = mysqli_query($mysqli, "SELECT ticket_reply_by AS u, SUM(TIME_TO_SEC(ticket_reply_time_worked)) AS s,
        SUM(CASE WHEN ticket_billable = 1 THEN TIME_TO_SEC(ticket_reply_time_worked) ELSE 0 END) AS b,
        SUM(ticket_reply_type = 'Public') AS replies, COUNT(DISTINCT ticket_id) AS touched
    FROM ticket_replies JOIN tickets ON ticket_reply_ticket_id = ticket_id
    WHERE ticket_reply_archived_at IS NULL AND tickets.ticket_archived_at IS NULL AND " . reportRangeSql('ticket_reply_created_at', $period) . " $scope
    GROUP BY ticket_reply_by");
while ($row = mysqli_fetch_assoc($sql)) {
    $u = intval($row['u']);
    if (isset($agents[$u])) {
        $agents[$u]['seconds'] = intval($row['s']);
        $agents[$u]['billable'] = intval($row['b']);
        $agents[$u]['replies'] = intval($row['replies']);
        $agents[$u]['tickets_touched'] = intval($row['touched']);
    }
}
$sql = mysqli_query($mysqli, "SELECT ticket_assigned_to AS u, COUNT(ticket_id) AS n, SUM(ticket_response_sla_met = 1) AS met, SUM(ticket_response_sla_met = 0) AS missed
    FROM tickets WHERE ticket_archived_at IS NULL AND " . reportRangeSql('ticket_resolved_at', $period) . " $scope GROUP BY ticket_assigned_to");
while ($row = mysqli_fetch_assoc($sql)) {
    $u = intval($row['u']);
    if (isset($agents[$u])) {
        $agents[$u]['resolved'] = intval($row['n']);
        $agents[$u]['sla_met'] = intval($row['met']);
        $agents[$u]['sla_missed'] = intval($row['missed']);
    }
}
$sql = mysqli_query($mysqli, "SELECT ticket_assigned_to AS u, COUNT(ticket_id) AS n FROM tickets WHERE ticket_archived_at IS NULL AND " . TICKET_OPEN_SQL . " $scope GROUP BY ticket_assigned_to");
$unassigned_open = 0;
while ($row = mysqli_fetch_assoc($sql)) {
    $u = intval($row['u']);
    if (isset($agents[$u])) {
        $agents[$u]['open_now'] = intval($row['n']);
    } elseif (!$u) {
        $unassigned_open = intval($row['n']);
    }
}
$sql = mysqli_query($mysqli, "SELECT ticket_assigned_to AS u, SUM(ticket_feedback = 'Good') AS good, SUM(ticket_feedback = 'Bad') AS bad
    FROM tickets WHERE ticket_archived_at IS NULL AND " . reportRangeSql('ticket_closed_at', $period) . " $scope GROUP BY ticket_assigned_to");
while ($row = mysqli_fetch_assoc($sql)) {
    if (isset($agents[intval($row['u'])])) {
        $agents[intval($row['u'])]['good'] = intval($row['good']);
        $agents[intval($row['u'])]['bad'] = intval($row['bad']);
    }
}
$responses = [];
$sql = mysqli_query($mysqli, "SELECT ticket_assigned_to AS u, TIMESTAMPDIFF(SECOND, ticket_created_at, ticket_first_response_at) AS s
    FROM tickets WHERE ticket_archived_at IS NULL AND ticket_first_response_at IS NOT NULL AND " . reportRangeSql('ticket_created_at', $period) . " $scope");
while ($row = mysqli_fetch_assoc($sql)) {
    $responses[intval($row['u'])][] = floatval($row['s']);
}

foreach ($agents as $u => &$row) {
    $row['billable_share'] = reportShare($row['billable'], $row['seconds']);
    $row['utilization'] = $capacity > 0 ? reportShare($row['seconds'], $capacity) : null;
    $row['response'] = reportMedian($responses[$u] ?? []);
    $row['sla'] = reportShare($row['sla_met'], $row['sla_met'] + $row['sla_missed']);
    $row['csat'] = reportShare($row['good'], $row['good'] + $row['bad']);
}
unset($row);
// Leave out anyone gone with nothing to show
$agents = array_values(array_filter($agents, function ($a) {
    return $a['active'] || $a['seconds'] || $a['resolved'] || $a['open_now'] || $a['replies'];
}));
usort($agents, function ($a, $b) { return $b['seconds'] <=> $a['seconds'] ?: strcasecmp($a['agent'], $b['agent']); });

$columns = [
    'agent' => ['label' => 'Agent', 'html' => function ($row) {
        return reportAgentCell($row['user_id'], $row['agent'], $row['avatar']) . ($row['active'] ? '' : ' <span class="badge bg-secondary-subtle text-secondary-emphasis fw-normal">Inactive</span>');
    }],
    'seconds' => ['label' => 'Hours logged', 'type' => 'hours', 'class' => 'fw-semibold'],
    'billable' => ['label' => 'Billable', 'type' => 'hours', 'title' => 'Time on billable tickets'],
    'billable_share' => ['label' => 'Billable share', 'type' => 'percent', 'class' => 'text-body-secondary'],
    'utilization' => ['label' => 'Utilization', 'type' => 'percent', 'title' => 'Hours logged against the hours available in the period', 'html' => function ($row) {
        if ($row['utilization'] === null) {
            return '<span class="itflow-report-zero">–</span>';
        }
        $share = $row['utilization'];
        $tone = $share >= 70 ? 'bg-success' : ($share >= 40 ? '' : 'bg-warning');
        return '<div class="d-flex align-items-center justify-content-end gap-2"><div class="itflow-report-meter d-none d-lg-flex" style="width: 5rem"><span class="' . $tone . '" style="width: ' . min(100, round($share, 1)) . '%"></span></div>'
            . '<span class="small">' . reportPercent(round($share)) . '</span></div>';
    }],
    'replies' => ['label' => 'Replies sent', 'type' => 'count', 'title' => 'Replies the client can see'],
    'tickets_touched' => ['label' => 'Tickets worked', 'type' => 'count', 'title' => 'Tickets they replied on or logged time to'],
    'resolved' => ['label' => 'Resolved', 'type' => 'count', 'title' => 'Tickets assigned to them, resolved in the period'],
    'open_now' => ['label' => 'Open now', 'type' => 'count', 'html' => function ($row) {
        return $row['open_now'] ? '<a class="itflow-report-amount" href="/agent/tickets.php?assigned=' . $row['user_id'] . '">' . $row['open_now'] . '</a>' : '<span class="itflow-report-zero">–</span>';
    }],
    'response' => ['label' => 'First reply', 'type' => 'duration', 'title' => 'Typical (middle) time to the first reply on tickets assigned to them'],
    'sla' => ['label' => 'SLA response', 'type' => 'percent', 'html' => function ($row) { return reportRateHtml($row['sla']); }],
    'csat' => ['label' => 'Satisfaction', 'type' => 'percent', 'html' => function ($row) {
        return $row['csat'] === null ? '<span class="itflow-report-zero">–</span>' : reportRateHtml($row['csat'], 90, 75) . ' <span class="small text-body-tertiary">' . ($row['good'] + $row['bad']) . '</span>';
    }],
];

$total_seconds = array_sum(array_column($agents, 'seconds'));
$total_billable = array_sum(array_column($agents, 'billable'));
$active_count = count(array_filter($agents, function ($a) { return $a['active']; }));
$totals = ['agent' => 'Total', 'seconds' => $total_seconds, 'billable' => $total_billable, 'billable_share' => reportShare($total_billable, $total_seconds),
    'utilization' => $capacity > 0 && $active_count ? reportShare($total_seconds, $capacity * $active_count) : null,
    'replies' => array_sum(array_column($agents, 'replies')), 'resolved' => array_sum(array_column($agents, 'resolved')), 'open_now' => array_sum(array_column($agents, 'open_now'))];

if (reportExporting()) {
    reportCsv('Technician Performance ' . $period['start'] . ' to ' . $period['end'], $columns, $agents, $totals);
}

// Hours per agent over the period, billable and not
$buckets = reportBuckets($period);
$weekly = [];
foreach (array_slice($agents, 0, 8) as $i => $agent) {
    $data = reportSeries("SELECT " . reportBucketSql('ticket_reply_created_at', $buckets['unit']) . " AS b, SUM(TIME_TO_SEC(ticket_reply_time_worked)) / 3600 AS v
        FROM ticket_replies JOIN tickets ON ticket_reply_ticket_id = ticket_id
        WHERE ticket_reply_by = " . intval($agent['user_id']) . " AND ticket_reply_archived_at IS NULL AND tickets.ticket_archived_at IS NULL AND " . reportRangeSql('ticket_reply_created_at', $period) . " $scope
        GROUP BY b", $buckets['keys']);
    if (array_sum($data) > 0) {
        $weekly[] = ['label' => $agent['agent'], 'data' => $data, 'tone' => reportPalette($i), 'type' => 'line'];
    }
}

require_once "includes/inc_report_layout.php";

$hours_control = '<div class="col-sm-6 col-lg-auto"><div class="input-group" title="Hours an agent works in a day - from the business hours in Admin > SLA unless you enter another">'
    . '<span class="input-group-text"><i class="fas fa-fw fa-business-time"></i></span>'
    . '<input type="text" inputmode="decimal" class="form-control" style="max-width: 6rem" name="hours" value="' . escapeHtml(rtrim(rtrim(number_format($workday, 2, '.', ''), '0'), '.')) . '" aria-label="Hours in a working day" data-report-autosubmit>'
    . '<span class="input-group-text">h a day</span></div></div>';
$with_time = array_values(array_filter($agents, function ($a) { return $a['seconds'] > 0; }));

?>

<div class="itflow-report">

    <?php reportHeader('time_by_tech', [
        'period' => $period,
        'years' => reportPastYears("SELECT DISTINCT YEAR(ticket_reply_created_at) AS y FROM ticket_replies"),
        'client' => $client_id,
        'controls' => $hours_control,
    ]); ?>

    <?= reportStats([
        ['label' => 'Hours logged', 'value' => reportHours($total_seconds) . ' h', 'sub' => '<span class="text-body-tertiary">By ' . count($with_time) . ' agent' . (count($with_time) === 1 ? '' : 's') . '</span>'],
        ['label' => 'Billable', 'value' => reportPercent(reportShare($total_billable, $total_seconds)), 'sub' => escapeHtml(reportHours($total_billable)) . ' h <span class="text-body-tertiary">on billable tickets</span>'],
        ['label' => 'Utilization', 'value' => reportPercent($totals['utilization'] === null ? null : round($totals['utilization'])),
            'sub' => '<span class="text-body-tertiary">Of ' . $working_days . ' working day' . ($working_days === 1 ? '' : 's') . ' × ' . escapeHtml(rtrim(rtrim(number_format($workday, 2), '0'), '.')) . ' h each</span>'],
        ['label' => 'Resolved', 'value' => reportCount($totals['resolved']), 'sub' => reportCount($totals['open_now']) . ' <span class="text-body-tertiary">open now' . ($unassigned_open ? ', ' . $unassigned_open . ' unassigned' : '') . '</span>'],
    ]) ?>

    <?php if ($with_time) { ?>
        <div class="row g-3">
            <div class="col-xl-5">
                <?= reportSectionOpen('Hours logged', 'Billable and not', 'h-100 mb-0') ?>
                    <div class="card-body">
                        <?= reportChart([
                            'type' => 'bar',
                            'horizontal' => true,
                            'stacked' => true,
                            'labels' => array_column($with_time, 'agent'),
                            'series' => [
                                ['label' => 'Billable', 'data' => array_map(function ($a) { return round($a['billable'] / 3600, 1); }, $with_time), 'tone' => 'accent', 'stack' => 'h'],
                                ['label' => 'Not billable', 'data' => array_map(function ($a) { return round(($a['seconds'] - $a['billable']) / 3600, 1); }, $with_time), 'tone' => 'faint', 'stack' => 'h'],
                            ],
                            'format' => 'hours',
                        ], '', 'Hours logged per agent, billable and not') ?>
                    </div>
                </div>
            </div>
            <div class="col-xl-7">
                <?= reportSectionOpen('Hours over the period', 'By ' . $buckets['unit'], 'h-100 mb-0') ?>
                    <div class="card-body">
                        <?= reportChart(['type' => 'line', 'labels' => $buckets['labels'], 'series' => $weekly, 'format' => 'hours', 'legend' => true], '', 'Hours logged by each agent over the period') ?>
                    </div>
                </div>
            </div>
        </div>
    <?php } ?>

    <?= reportSectionOpen('By agent', 'Most hours first') ?>
        <?= reportTable($columns, $agents, ['empty' => 'No agents.', 'totals' => $totals, 'totals_html' => ['utilization' => $totals['utilization'] === null ? '' : reportPercent(round($totals['utilization']))]]) ?>
        <div class="card-footer small text-body-secondary">
            Hours come from time logged on ticket replies in the period. Utilization compares them with the working days in the period
            (the business days in Admin > SLA, less closure days) at <?= escapeHtml(rtrim(rtrim(number_format($workday, 2), '0'), '.')) ?> hours a day - it is a guide, not a timesheet.
            Resolved, SLA and first reply go by who the ticket is assigned to.
        </div>
    </div>

</div>

<?php require_once "../../includes/footer.php"; ?>
<script src="/js/reports.js"></script>
