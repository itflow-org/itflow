<?php

/*
 * SLA by Client - for each client, how their tickets with an SLA did in the
 * period: first replies and resolutions in time, the typical first reply,
 * and the average SLA clock to resolve - business hours with the clock
 * running, so paused time does not count, which is what the SLA itself
 * judged. Worst first, so the clients to talk to are at the top. One
 * agent's tickets only when an agent is picked.
 */

require_once "includes/inc_report.php";
reportEnforce('sla_by_client');

$period = reportPeriod('last_90');
$agent_id = reportAgentId();
$filter = reportTicketSql(0, $agent_id) . " AND ticket_sla_id > 0 AND " . reportRangeSql('ticket_created_at', $period);

$clients = [];
$sql = mysqli_query($mysqli, "SELECT client_id, client_name, client_abbreviation, " . REPORT_SLA_COLUMNS . "
    FROM tickets JOIN clients ON ticket_client_id = client_id WHERE $filter GROUP BY client_id, client_name, client_abbreviation");
while ($row = mysqli_fetch_assoc($sql)) {
    $clients[intval($row['client_id'])] = ['client_id' => intval($row['client_id']), 'client' => $row['client_name'], 'abbr' => $row['client_abbreviation'],
        'response' => null, 'resolve_clock' => null, 'breached_open' => 0] + reportSlaFigures($row);
}

$responses = [];
$sql = mysqli_query($mysqli, "SELECT ticket_client_id AS client_id, TIMESTAMPDIFF(SECOND, ticket_created_at, ticket_first_response_at) AS s FROM tickets WHERE $filter AND ticket_first_response_at IS NOT NULL");
while ($row = mysqli_fetch_assoc($sql)) {
    $responses[intval($row['client_id'])][] = floatval($row['s']);
}

// The SLA clock spent on resolved tickets - logged business minutes, or for a
// ticket with none logged (a response-only plan, or one resolved before
// pausing existed) the business time between being raised and resolved
$clock = [];
$sql = mysqli_query($mysqli, "SELECT ticket_client_id AS client_id, ticket_created_at, ticket_resolved_at, SUM(sla_history_minutes) AS logged
    FROM tickets LEFT JOIN sla_history ON sla_history_ticket_id = ticket_id
    WHERE $filter AND ticket_resolved_at IS NOT NULL
    GROUP BY ticket_id, ticket_client_id, ticket_created_at, ticket_resolved_at");
while ($row = mysqli_fetch_assoc($sql)) {
    $minutes = $row['logged'] === null ? businessMinutesBetween($row['ticket_created_at'], $row['ticket_resolved_at']) : intval($row['logged']);
    $clock[intval($row['client_id'])][] = $minutes * 60;
}

$sql = mysqli_query($mysqli, "SELECT ticket_client_id AS client_id, COUNT(ticket_id) AS n FROM tickets
    WHERE " . reportTicketSql(0, $agent_id) . " AND " . TICKET_OPEN_SQL . " AND " . dashboardSlaBreachedSql() . " GROUP BY ticket_client_id");
while ($row = mysqli_fetch_assoc($sql)) {
    if (isset($clients[intval($row['client_id'])])) {
        $clients[intval($row['client_id'])]['breached_open'] = intval($row['n']);
    }
}

foreach ($clients as $id => &$row) {
    $row['response'] = reportMedian($responses[$id] ?? []);
    $row['resolve_clock'] = !empty($clock[$id]) ? array_sum($clock[$id]) / count($clock[$id]) : null;
    $row['worst'] = min($row['response_percent'] ?? 101, $row['resolution_percent'] ?? 101);
}
unset($row);
uasort($clients, function ($a, $b) { return $a['worst'] <=> $b['worst'] ?: $b['tickets'] <=> $a['tickets']; });
$clients = array_values($clients);

// tickets.php's assigned= filter, so a click keeps to the same agent
$agent_link = $agent_id ? "&assigned=$agent_id" : '';

$columns = [
    'client' => ['label' => 'Client', 'html' => function ($row) {
        return reportClientCell($row['client_id'], $row['client'], $row['abbr'], 'tickets.php');
    }],
    'tickets' => ['label' => 'Tickets', 'type' => 'count'],
    // The late counts sit beside their share on screen; the export keeps them apart
    'response_missed' => ['label' => 'Replied late', 'type' => 'count', 'screen' => false],
    'response_percent' => ['label' => 'Response', 'type' => 'percent', 'title' => 'First replies in time, of those judged', 'html' => function ($row) {
        return reportRateHtml($row['response_percent']) . reportLateHtml($row['response_missed']);
    }],
    'resolution_missed' => ['label' => 'Resolved late', 'type' => 'count', 'screen' => false],
    'resolution_percent' => ['label' => 'Resolution', 'type' => 'percent', 'title' => 'Resolved in time, of those judged', 'html' => function ($row) {
        return reportRateHtml($row['resolution_percent']) . reportLateHtml($row['resolution_missed']);
    }],
    'response' => ['label' => 'First reply', 'type' => 'duration', 'title' => 'Typical (middle) time to the first reply, on the clock'],
    'resolve_clock' => ['label' => 'Clock to resolve', 'type' => 'duration', 'title' => 'Average business time with the SLA clock running, to resolve'],
    'breached_open' => ['label' => 'Breached now', 'type' => 'count', 'csv_label' => 'Breached and still open', 'title' => 'Open tickets that have missed a target, whenever they were raised', 'html' => function ($row) use ($agent_link) {
        return $row['breached_open'] ? '<a class="text-danger-emphasis fw-semibold" href="/agent/tickets.php?client_id=' . $row['client_id'] . '&sla=breached' . $agent_link . '">' . $row['breached_open'] . '</a>' : '<span class="itflow-report-zero">–</span>';
    }],
];

$overall = reportSlaCompliance($filter);
$totals = ['client' => 'Total', 'tickets' => $overall['tickets'], 'response_missed' => $overall['response_missed'], 'response_percent' => $overall['response_percent'],
    'resolution_missed' => $overall['resolution_missed'], 'resolution_percent' => $overall['resolution_percent'],
    'breached_open' => array_sum(array_column($clients, 'breached_open'))];

if (reportExporting()) {
    reportCsv('SLA by Client ' . $period['start'] . ' to ' . $period['end'], $columns, $clients, $totals);
}

require_once "includes/inc_report_layout.php";

$below = array_filter($clients, function ($c) { return $c['worst'] < 80; });
$chart_rows = array_slice($clients, 0, 15);

?>

<div class="itflow-report">

    <?php reportHeader('sla_by_client', [
        'period' => $period,
        'years' => reportPastYears("SELECT DISTINCT YEAR(ticket_created_at) AS y FROM tickets WHERE ticket_sla_id > 0"),
        'agent' => $agent_id,
    ]); ?>

    <?= reportStats([
        ['label' => 'Clients with SLA tickets', 'value' => reportCount(count($clients)), 'sub' => reportCount($overall['tickets']) . ' <span class="text-body-tertiary">tickets</span>'],
        ['label' => 'First reply in time', 'value_html' => reportRateHtml($overall['response_percent']), 'sub' => '<span class="text-body-tertiary">Across all clients</span>'],
        ['label' => 'Resolved in time', 'value_html' => reportRateHtml($overall['resolution_percent']), 'sub' => '<span class="text-body-tertiary">Across all clients</span>'],
        ['label' => 'Clients under 80%', 'value' => reportCount(count($below)), 'tone' => $below ? 'danger' : 'success', 'sub' => '<span class="text-body-tertiary">On response or resolution</span>'],
    ]) ?>

    <?php if ($chart_rows) { ?>
        <?= reportSectionOpen('Targets met', 'Worst first') ?>
            <div class="card-body">
                <?= reportChart([
                    'type' => 'bar',
                    'horizontal' => true,
                    'labels' => array_column($chart_rows, 'client'),
                    'series' => [
                        ['label' => 'First reply in time', 'data' => array_column($chart_rows, 'response_percent'), 'tone' => 'accent'],
                        ['label' => 'Resolved in time', 'data' => array_column($chart_rows, 'resolution_percent'), 'tone' => 'success'],
                    ],
                    'format' => 'percent',
                    'max' => 100,
                ], '', 'The share of SLA targets met for each client') ?>
            </div>
        </div>
    <?php } ?>

    <?= reportSectionOpen('By client', 'Worst first') ?>
        <?= reportTable($columns, $clients, ['empty' => reportEmpty('No ticket raised in this period carried an SLA.', 'fa-stopwatch', 'secondary'), 'totals' => $totals,
            'totals_html' => ['response_percent' => reportRateHtml($overall['response_percent']) . reportLateHtml($overall['response_missed']),
                'resolution_percent' => reportRateHtml($overall['resolution_percent']) . reportLateHtml($overall['resolution_missed'])]]) ?>
        <div class="card-footer small text-body-secondary">
            Tickets raised in the period that carry an SLA. Percentages count only targets already judged. The first reply runs on the clock;
            the SLA clock to resolve counts business hours with the clock running, so time paused (On Hold) is left out.
        </div>
    </div>

</div>

<?php require_once "../../includes/footer.php"; ?>
<script src="/js/reports.js"></script>
