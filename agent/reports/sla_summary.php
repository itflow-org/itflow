<?php

/*
 * SLA Performance - how tickets carrying an SLA did against their response
 * and resolution targets, for tickets raised in the period: overall, month by
 * month, by priority and by agent, and the SLAs breached on tickets still
 * open now. A percentage counts only targets that have been judged - a ticket
 * still waiting for its first reply is neither a hit nor a miss yet.
 */

require_once "includes/inc_report.php";
reportEnforce('sla_summary');

$period = reportPeriod('last_90');
$client_id = reportClientId();
$filter = reportTicketSql($client_id) . " AND " . reportRangeSql('ticket_created_at', $period);

$overall = reportSlaCompliance($filter);
$previous = reportSlaCompliance(reportTicketSql($client_id) . " AND " . reportRangeSql('ticket_created_at', $period, true));
$breached_open = dashboardCount("SELECT COUNT(ticket_id) FROM tickets WHERE " . reportTicketSql($client_id) . " AND " . TICKET_OPEN_SQL . " AND " . dashboardSlaBreachedSql());

// Grouped figures
$grouped = function ($group_sql, $join = '') use ($filter) {
    global $mysqli;
    $rows = [];
    $sql = mysqli_query($mysqli, "SELECT $group_sql AS k, " . REPORT_SLA_COLUMNS . " FROM tickets $join WHERE ticket_sla_id > 0 AND $filter GROUP BY k");
    while ($row = mysqli_fetch_assoc($sql)) {
        $rows[(string) $row['k']] = reportSlaFigures($row);
    }
    return $rows;
};

$buckets = reportBuckets($period);
if ($buckets['unit'] === 'day') {
    // A day's handful of tickets makes a jumpy line - weeks read better
    $buckets = reportBuckets(['start' => $period['start'], 'end' => $period['end'], 'days' => 60]);
}
$by_bucket = $grouped(reportBucketSql('ticket_created_at', $buckets['unit']));
$by_priority = $grouped('ticket_priority');
$by_agent = $grouped('ticket_assigned_to');

$priority_rows = [];
foreach (['Urgent', 'High', 'Medium', 'Low'] as $priority) {
    if (isset($by_priority[$priority])) {
        $priority_rows[] = ['priority' => $priority] + $by_priority[$priority];
    }
}
$agent_rows = [];
$names = [];
$sql = mysqli_query($mysqli, "SELECT user_id, user_name, user_avatar FROM users WHERE user_type = 1");
while ($row = mysqli_fetch_assoc($sql)) {
    $names[intval($row['user_id'])] = $row;
}
foreach ($by_agent as $user_id => $figures) {
    $user_id = intval($user_id);
    $agent_rows[] = ['user_id' => $user_id, 'agent' => $names[$user_id]['user_name'] ?? '', 'avatar' => $names[$user_id]['user_avatar'] ?? ''] + $figures;
}
usort($agent_rows, function ($a, $b) { return $b['tickets'] <=> $a['tickets']; });

// On screen each share carries its late count; the export keeps every count apart
$figure_columns = [
    'tickets' => ['label' => 'Tickets', 'type' => 'count'],
    'response_met' => ['label' => 'Replied in time', 'type' => 'count', 'screen' => false],
    'response_missed' => ['label' => 'Replied late', 'type' => 'count', 'screen' => false],
    'response_percent' => ['label' => 'Response', 'type' => 'percent', 'title' => 'First replies in time, of those judged', 'html' => function ($row) {
        return reportRateHtml($row['response_percent']) . reportLateHtml($row['response_missed']);
    }],
    'resolution_met' => ['label' => 'Resolved in time', 'type' => 'count', 'screen' => false],
    'resolution_missed' => ['label' => 'Resolved late', 'type' => 'count', 'screen' => false],
    'resolution_percent' => ['label' => 'Resolution', 'type' => 'percent', 'title' => 'Resolved in time, of those judged', 'html' => function ($row) {
        return reportRateHtml($row['resolution_percent']) . reportLateHtml($row['resolution_missed']);
    }],
    'resolution_pending' => ['label' => 'Still running', 'type' => 'count', 'class' => 'text-body-secondary', 'title' => 'Resolution clock still running'],
];
$priority_columns = ['priority' => ['label' => 'Priority', 'html' => function ($row) {
    return reportPriorityDot($row['priority']) . escapeHtml($row['priority']);
}]] + $figure_columns;
$agent_columns = ['agent' => ['label' => 'Assigned to', 'csv' => function ($row) { return $row['user_id'] ? $row['agent'] : 'Unassigned'; }, 'html' => function ($row) {
    return formatAssignee($row['user_id'], $row['agent'], $row['avatar'], true);
}]] + $figure_columns;
$month_rows = [];
foreach ($buckets['keys'] as $i => $key) {
    if (isset($by_bucket[$key])) {
        $month_rows[] = ['when' => $buckets['labels'][$i]] + $by_bucket[$key];
    }
}
$month_columns = ['when' => ['label' => ucfirst($buckets['unit']), 'sort' => false]] + $figure_columns;

if (reportExporting('agents')) {
    reportCsv('SLA by Agent', $agent_columns, $agent_rows);
}
if (reportExporting('priorities')) {
    reportCsv('SLA by Priority', $priority_columns, $priority_rows);
}
if (reportExporting()) {
    reportCsv('SLA by ' . ucfirst($buckets['unit']), $month_columns, $month_rows, ['when' => 'Total'] + $overall);
}

require_once "includes/inc_report_layout.php";

$has_sla = $overall['tickets'] > 0;

?>

<div class="itflow-report">

    <?php reportHeader('sla_summary', [
        'period' => $period,
        'years' => reportPastYears("SELECT DISTINCT YEAR(ticket_created_at) AS y FROM tickets WHERE ticket_sla_id > 0"),
        'client' => $client_id,
        'export' => ['months' => 'By ' . $buckets['unit'], 'priorities' => 'By priority', 'agents' => 'By agent'],
    ]); ?>

    <?= reportStats([
        ['label' => 'Tickets with an SLA', 'value' => reportCount($overall['tickets']), 'sub' => '<span class="text-body-tertiary">Raised in the period</span>'],
        ['label' => 'First reply in time', 'value_html' => reportRateHtml($overall['response_percent']),
            'sub' => $previous['response_percent'] !== null ? 'Was <span class="text-body">' . reportPercent($previous['response_percent']) . '</span> <span class="text-body-tertiary">' . escapeHtml($period['compare']) . '</span>' : ''],
        ['label' => 'Resolved in time', 'value_html' => reportRateHtml($overall['resolution_percent']),
            'sub' => $previous['resolution_percent'] !== null ? 'Was <span class="text-body">' . reportPercent($previous['resolution_percent']) . '</span> <span class="text-body-tertiary">' . escapeHtml($period['compare']) . '</span>' : ''],
        ['label' => 'Breached and still open', 'value' => reportCount($breached_open), 'tone' => $breached_open ? 'danger' : 'success', 'url' => '/agent/tickets.php?sla=breached' . ($client_id ? "&client_id=$client_id" : ''),
            'sub' => '<span class="text-body-tertiary">Whenever they were raised</span>'],
    ]) ?>

    <?php if (!$has_sla) { ?>
        <div class="card"><div class="card-body"><?= reportEmpty('No ticket raised in this period carried an SLA. Set SLAs up in Admin > SLA to start measuring them.', 'fa-stopwatch', 'secondary') ?></div></div>
    <?php } else { ?>

        <?= reportSectionOpen('Targets met over the period', 'Bars are tickets, lines the share met') ?>
            <div class="card-body">
                <?php
                $keys = $buckets['keys'];
                echo reportChart([
                    'type' => 'bar',
                    'labels' => $buckets['labels'],
                    'series' => [
                        ['label' => 'Tickets with an SLA', 'data' => array_map(function ($k) use ($by_bucket) { return $by_bucket[$k]['tickets'] ?? 0; }, $keys), 'tone' => 'faint'],
                        ['label' => 'First reply in time', 'data' => array_map(function ($k) use ($by_bucket) { return $by_bucket[$k]['response_percent'] ?? null; }, $keys), 'tone' => 'accent', 'type' => 'line', 'axis' => 'y2'],
                        ['label' => 'Resolved in time', 'data' => array_map(function ($k) use ($by_bucket) { return $by_bucket[$k]['resolution_percent'] ?? null; }, $keys), 'tone' => 'success', 'type' => 'line', 'axis' => 'y2'],
                    ],
                    'format' => 'count',
                    'format2' => 'percent',
                    'max2' => 100,
                ], '', 'SLA tickets and the share of targets met over the period');
                ?>
            </div>
        </div>

        <?= reportSectionOpen('By priority', '') ?>
            <?= reportTable($priority_columns, $priority_rows, ['sortable' => false]) ?>
        </div>

        <?= reportSectionOpen('By agent', 'Who the ticket is assigned to') ?>
            <?= reportTable($agent_columns, $agent_rows) ?>
        </div>

        <?= reportSectionOpen('By ' . $buckets['unit'], 'When the tickets were raised') ?>
            <?= reportTable($month_columns, $month_rows, ['sortable' => false, 'totals' => ['when' => 'Total'] + $overall,
                'totals_html' => ['response_percent' => reportRateHtml($overall['response_percent']) . reportLateHtml($overall['response_missed']),
                    'resolution_percent' => reportRateHtml($overall['resolution_percent']) . reportLateHtml($overall['resolution_missed'])]]) ?>
            <div class="card-footer small text-body-secondary">
                Tickets raised in the period that carry an SLA. Targets run in business hours, and a status that pauses the SLA stops the resolution clock.
                Percentages count only targets already judged; tickets raised before SLAs were set up carry none and are not counted.
            </div>
        </div>

    <?php } ?>

</div>

<?php require_once "../../includes/footer.php"; ?>
<script src="/js/reports.js"></script>
