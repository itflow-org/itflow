<?php

/*
 * Tickets by Client - for each client in the period: tickets opened (and how
 * many were urgent or high), resolved, still open now, time logged, how
 * quickly they typically got a first reply and a resolution, SLA response
 * compliance and how they rated their tickets. Busiest first; one agent's
 * tickets only when an agent is picked.
 */

require_once "includes/inc_report.php";
reportEnforce('ticket_by_client');

$period = reportPeriod('last_90');
$agent_id = reportAgentId();
$filter = reportTicketSql(0, $agent_id);
$opened_in = reportRangeSql('ticket_created_at', $period);

$clients = [];
$client = function ($row) use (&$clients) {
    $id = intval($row['client_id']);
    $clients[$id] = $clients[$id] ?? ['client_id' => $id, 'client' => $row['client_name'], 'abbr' => $row['client_abbreviation'],
        'opened' => 0, 'urgent' => 0, 'resolved' => 0, 'open_now' => 0, 'seconds' => 0, 'response' => null, 'resolve' => null,
        'sla_met' => 0, 'sla_missed' => 0, 'good' => 0, 'bad' => 0, 'prev' => 0];
    return $id;
};

$sql = mysqli_query($mysqli, "SELECT client_id, client_name, client_abbreviation, COUNT(ticket_id) AS opened,
        SUM(ticket_priority IN ('Urgent', 'High')) AS urgent,
        SUM(ticket_response_sla_met = 1) AS sla_met, SUM(ticket_response_sla_met = 0) AS sla_missed
    FROM tickets JOIN clients ON ticket_client_id = client_id
    WHERE $filter AND $opened_in GROUP BY client_id, client_name, client_abbreviation");
while ($row = mysqli_fetch_assoc($sql)) {
    $id = $client($row);
    $clients[$id]['opened'] = intval($row['opened']);
    $clients[$id]['urgent'] = intval($row['urgent']);
    $clients[$id]['sla_met'] = intval($row['sla_met']);
    $clients[$id]['sla_missed'] = intval($row['sla_missed']);
}
$sql = mysqli_query($mysqli, "SELECT client_id, client_name, client_abbreviation, COUNT(ticket_id) AS n
    FROM tickets JOIN clients ON ticket_client_id = client_id
    WHERE $filter AND " . reportRangeSql('ticket_resolved_at', $period) . " GROUP BY client_id, client_name, client_abbreviation");
while ($row = mysqli_fetch_assoc($sql)) {
    $clients[$client($row)]['resolved'] = intval($row['n']);
}
$sql = mysqli_query($mysqli, "SELECT client_id, client_name, client_abbreviation, COUNT(ticket_id) AS n
    FROM tickets JOIN clients ON ticket_client_id = client_id
    WHERE $filter AND " . TICKET_OPEN_SQL . " GROUP BY client_id, client_name, client_abbreviation");
while ($row = mysqli_fetch_assoc($sql)) {
    $clients[$client($row)]['open_now'] = intval($row['n']);
}
$sql = mysqli_query($mysqli, "SELECT client_id, client_name, client_abbreviation, SUM(TIME_TO_SEC(ticket_reply_time_worked)) AS s
    FROM ticket_replies JOIN tickets ON ticket_reply_ticket_id = ticket_id JOIN clients ON ticket_client_id = client_id
    WHERE $filter AND ticket_reply_archived_at IS NULL AND " . reportRangeSql('ticket_reply_created_at', $period) . " GROUP BY client_id, client_name, client_abbreviation");
while ($row = mysqli_fetch_assoc($sql)) {
    $clients[$client($row)]['seconds'] = intval($row['s']);
}
$sql = mysqli_query($mysqli, "SELECT ticket_client_id AS client_id, SUM(ticket_feedback = 'Good') AS good, SUM(ticket_feedback = 'Bad') AS bad
    FROM tickets WHERE $filter AND " . reportRangeSql('ticket_closed_at', $period) . " GROUP BY ticket_client_id");
while ($row = mysqli_fetch_assoc($sql)) {
    if (isset($clients[intval($row['client_id'])])) {
        $clients[intval($row['client_id'])]['good'] = intval($row['good']);
        $clients[intval($row['client_id'])]['bad'] = intval($row['bad']);
    }
}
$sql = mysqli_query($mysqli, "SELECT ticket_client_id AS client_id, COUNT(ticket_id) AS n FROM tickets WHERE $filter AND " . reportRangeSql('ticket_created_at', $period, true) . " GROUP BY ticket_client_id");
while ($row = mysqli_fetch_assoc($sql)) {
    if (isset($clients[intval($row['client_id'])])) {
        $clients[intval($row['client_id'])]['prev'] = intval($row['n']);
    }
}

// The typical (middle) first reply and resolution, per client
$times = ['response' => [], 'resolve' => []];
$sql = mysqli_query($mysqli, "SELECT ticket_client_id AS client_id, TIMESTAMPDIFF(SECOND, ticket_created_at, ticket_first_response_at) AS s
    FROM tickets WHERE $filter AND $opened_in AND ticket_first_response_at IS NOT NULL");
while ($row = mysqli_fetch_assoc($sql)) {
    $times['response'][intval($row['client_id'])][] = floatval($row['s']);
}
$sql = mysqli_query($mysqli, "SELECT ticket_client_id AS client_id, TIMESTAMPDIFF(SECOND, ticket_created_at, ticket_resolved_at) AS s
    FROM tickets WHERE $filter AND " . reportRangeSql('ticket_resolved_at', $period));
while ($row = mysqli_fetch_assoc($sql)) {
    $times['resolve'][intval($row['client_id'])][] = floatval($row['s']);
}
foreach ($clients as $id => &$row) {
    $row['response'] = reportMedian($times['response'][$id] ?? []);
    $row['resolve'] = reportMedian($times['resolve'][$id] ?? []);
    $row['sla'] = reportShare($row['sla_met'], $row['sla_met'] + $row['sla_missed']);
    $row['csat'] = reportShare($row['good'], $row['good'] + $row['bad']);
    $row['change'] = reportChange($row['opened'], $row['prev']);
}
unset($row);
uasort($clients, function ($a, $b) { return $b['opened'] <=> $a['opened'] ?: $b['open_now'] <=> $a['open_now']; });
$clients = array_values($clients);

$columns = [
    'client' => ['label' => 'Client', 'html' => function ($row) {
        return reportClientCell($row['client_id'], $row['client'], $row['abbr'], 'tickets.php');
    }],
    'opened' => ['label' => 'Opened', 'type' => 'count', 'class' => 'fw-semibold'],
    'change' => ['label' => 'Change', 'type' => 'percent', 'csv' => false, 'html' => function ($row) {
        return $row['change'] === null ? '<span class="itflow-report-zero">–</span>' : '<span class="small">' . reportChangeHtml($row['opened'], $row['prev'], 'down') . '</span>';
    }],
    'urgent' => ['label' => 'Urgent or high', 'type' => 'count', 'html' => function ($row) {
        return $row['urgent'] ? '<span class="text-danger-emphasis">' . $row['urgent'] . '</span>' : '<span class="itflow-report-zero">–</span>';
    }],
    'resolved' => ['label' => 'Resolved', 'type' => 'count'],
    'open_now' => ['label' => 'Open now', 'type' => 'count', 'html' => function ($row) {
        return $row['open_now'] ? '<a class="itflow-report-amount" href="/agent/tickets.php?client_id=' . $row['client_id'] . '">' . $row['open_now'] . '</a>' : '<span class="itflow-report-zero">–</span>';
    }],
    'seconds' => ['label' => 'Time logged', 'type' => 'hours'],
    'response' => ['label' => 'First reply', 'type' => 'duration', 'title' => 'Typical (middle) time to the first reply'],
    'resolve' => ['label' => 'To resolve', 'type' => 'duration', 'title' => 'Typical (middle) time to resolve'],
    'sla' => ['label' => 'SLA response', 'type' => 'percent', 'html' => function ($row) {
        return reportRateHtml($row['sla']);
    }],
    'csat' => ['label' => 'Satisfaction', 'type' => 'percent', 'title' => 'Good ratings out of all good and bad', 'html' => function ($row) {
        return $row['csat'] === null ? '<span class="itflow-report-zero">–</span>' : reportRateHtml($row['csat'], 90, 75) . ' <span class="small text-body-tertiary">' . ($row['good'] + $row['bad']) . '</span>';
    }],
];

$totals = ['client' => 'Total'];
foreach (['opened', 'urgent', 'resolved', 'open_now', 'seconds'] as $key) {
    $totals[$key] = array_sum(array_column($clients, $key));
}
$all_response = reportMedian(array_merge(...array_values($times['response'] ?: [[]])));
$all_resolve = reportMedian(array_merge(...array_values($times['resolve'] ?: [[]])));
$totals['response'] = $all_response;
$totals['resolve'] = $all_resolve;
$totals['sla'] = reportShare(array_sum(array_column($clients, 'sla_met')), array_sum(array_column($clients, 'sla_met')) + array_sum(array_column($clients, 'sla_missed')));
$totals['csat'] = reportShare(array_sum(array_column($clients, 'good')), array_sum(array_column($clients, 'good')) + array_sum(array_column($clients, 'bad')));

if (reportExporting()) {
    reportCsv('Tickets by Client ' . $period['start'] . ' to ' . $period['end'], $columns, $clients, $totals);
}

require_once "includes/inc_report_layout.php";

$top = array_slice(array_filter($clients, function ($c) { return $c['opened'] > 0; }), 0, 12);
$busiest = $clients[0] ?? null;
$with_tickets = count(array_filter($clients, function ($c) { return $c['opened'] > 0; }));

?>

<div class="itflow-report">

    <?php reportHeader('ticket_by_client', [
        'period' => $period,
        'years' => reportPastYears("SELECT DISTINCT YEAR(ticket_created_at) AS y FROM tickets"),
        'agent' => $agent_id,
    ]); ?>

    <?= reportStats([
        ['label' => 'Tickets opened', 'value' => reportCount($totals['opened']), 'sub' => reportCount($with_tickets) . ' <span class="text-body-tertiary">clients raised them</span>'],
        ['label' => 'Average per client', 'value' => $with_tickets ? number_format($totals['opened'] / $with_tickets, 1) : '-', 'sub' => '<span class="text-body-tertiary">Of those who raised any</span>'],
        ['label' => 'Busiest', 'value' => $busiest && $busiest['opened'] ? $busiest['client'] : '-', 'sub' => $busiest && $busiest['opened'] ? $busiest['opened'] . ' <span class="text-body-tertiary">tickets · ' . reportPercent(reportShare($busiest['opened'], $totals['opened'])) . ' of all</span>' : ''],
        ['label' => 'Time logged', 'value' => reportHours($totals['seconds']) . ' h', 'sub' => $with_tickets ? reportHours($totals['seconds'] / $with_tickets) . ' h <span class="text-body-tertiary">per client</span>' : ''],
    ]) ?>

    <?php if ($top) { ?>
        <?= reportSectionOpen('Clients raising the most', 'By priority') ?>
            <div class="card-body">
                <?php
                // Each client's tickets split by priority
                $ids = array_column($top, 'client_id');
                $split = [];
                $sql = mysqli_query($mysqli, "SELECT ticket_client_id AS client_id, ticket_priority AS p, COUNT(ticket_id) AS n FROM tickets
                    WHERE $filter AND $opened_in AND ticket_client_id IN (" . implode(',', array_map('intval', $ids)) . ") GROUP BY ticket_client_id, ticket_priority");
                while ($row = mysqli_fetch_assoc($sql)) {
                    $split[$row['p']][intval($row['client_id'])] = intval($row['n']);
                }
                $series = [];
                foreach (['Urgent' => 'dark', 'High' => 'danger', 'Medium' => 'warning', 'Low' => 'info'] as $priority => $tone) {
                    $series[] = ['label' => $priority, 'tone' => $tone, 'stack' => 'p', 'data' => array_map(function ($id) use ($split, $priority) { return $split[$priority][$id] ?? 0; }, $ids)];
                }
                echo reportChart(['type' => 'bar', 'horizontal' => true, 'stacked' => true, 'labels' => array_column($top, 'client'), 'series' => $series, 'format' => 'count'], '', 'Tickets opened per client, by priority');
                ?>
            </div>
        </div>
    <?php } ?>

    <?= reportSectionOpen('By client', 'Busiest first', 'itflow-report-long') ?>
        <?= reportTable($columns, $clients, ['empty' => 'No tickets in this period.', 'totals' => $totals, 'totals_html' => ['sla' => reportRateHtml($totals['sla']), 'csat' => $totals['csat'] === null ? '' : reportRateHtml($totals['csat'], 90, 75)]]) ?>
        <div class="card-footer small text-body-secondary">
            Opened counts tickets raised in the period; resolved counts those resolved in it, whenever raised. Times run on the clock and are the middle ticket's, so a few long ones do not skew them.
            SLA response is the share of first-reply targets met. Satisfaction is from client ratings on tickets closed in the period.
        </div>
    </div>

</div>

<?php require_once "../../includes/footer.php"; ?>
<script src="/js/reports.js"></script>
