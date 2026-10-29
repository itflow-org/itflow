<?php

/*
 * Ticket Overview - the service desk over a period: tickets opened and
 * resolved, how quickly the first reply went out and how long tickets took
 * to resolve (the middle ticket - a few that sat open for weeks do not skew
 * it), where they came from, how urgent, what about, and on which days and
 * hours they arrive. Narrow it to one client or one agent's tickets.
 *
 * Times are wall-clock, from the ticket being raised; the SLA reports
 * measure business hours instead.
 */

require_once "includes/inc_report.php";
reportEnforce('ticket_summary');

$period = reportPeriod('last_90');
$client_id = reportClientId();
$agent_id = reportAgentId();
$filter = reportTicketSql($client_id, $agent_id);
$opened_in = reportRangeSql('ticket_created_at', $period);
$opened_before = reportRangeSql('ticket_created_at', $period, true);
$resolved_in = reportRangeSql('ticket_resolved_at', $period);

$opened = dashboardCount("SELECT COUNT(ticket_id) FROM tickets WHERE $filter AND $opened_in");
$opened_prev = dashboardCount("SELECT COUNT(ticket_id) FROM tickets WHERE $filter AND $opened_before");
$resolved = dashboardCount("SELECT COUNT(ticket_id) FROM tickets WHERE $filter AND $resolved_in");
$resolved_prev = dashboardCount("SELECT COUNT(ticket_id) FROM tickets WHERE $filter AND " . reportRangeSql('ticket_resolved_at', $period, true));
$open_now = dashboardCount("SELECT COUNT(ticket_id) FROM tickets WHERE $filter AND " . TICKET_OPEN_SQL);

$response = reportMedian(reportColumn("SELECT TIMESTAMPDIFF(SECOND, ticket_created_at, ticket_first_response_at) FROM tickets WHERE $filter AND $opened_in AND ticket_first_response_at IS NOT NULL"));
$response_prev = reportMedian(reportColumn("SELECT TIMESTAMPDIFF(SECOND, ticket_created_at, ticket_first_response_at) FROM tickets WHERE $filter AND $opened_before AND ticket_first_response_at IS NOT NULL"));
$resolve = reportMedian(reportColumn("SELECT TIMESTAMPDIFF(SECOND, ticket_created_at, ticket_resolved_at) FROM tickets WHERE $filter AND $resolved_in"));
$resolve_prev = reportMedian(reportColumn("SELECT TIMESTAMPDIFF(SECOND, ticket_created_at, ticket_resolved_at) FROM tickets WHERE $filter AND " . reportRangeSql('ticket_resolved_at', $period, true)));

$time_logged = dashboardSum("SELECT SUM(TIME_TO_SEC(ticket_reply_time_worked)) FROM ticket_replies JOIN tickets ON ticket_reply_ticket_id = ticket_id
    WHERE $filter AND ticket_reply_archived_at IS NULL AND " . reportRangeSql('ticket_reply_created_at', $period));

// Opened and resolved over the period
$buckets = reportBuckets($period);
$opened_series = reportSeries("SELECT " . reportBucketSql('ticket_created_at', $buckets['unit']) . " AS b, COUNT(ticket_id) AS v FROM tickets WHERE $filter AND $opened_in GROUP BY b", $buckets['keys']);
$resolved_series = reportSeries("SELECT " . reportBucketSql('ticket_resolved_at', $buckets['unit']) . " AS b, COUNT(ticket_id) AS v FROM tickets WHERE $filter AND $resolved_in GROUP BY b", $buckets['keys']);

// Breakdowns of what was opened
$breakdown = function ($column) use ($filter, $opened_in) {
    global $mysqli;
    $rows = [];
    $sql = mysqli_query($mysqli, "SELECT $column AS k, COUNT(ticket_id) AS n FROM tickets WHERE $filter AND $opened_in GROUP BY k ORDER BY n DESC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $rows[(string) $row['k']] = intval($row['n']);
    }
    return $rows;
};
$sources = $breakdown("CASE WHEN ticket_source IN ('Email', 'Portal', 'Agent', 'API', 'Recurring', 'Project') THEN ticket_source WHEN ticket_source LIKE '%Recurring%' THEN 'Recurring' ELSE COALESCE(NULLIF(ticket_source, ''), 'Agent') END");
$priorities = $breakdown('ticket_priority');

// By category: opened, resolved, how long, how much time
$category_names = reportTicketCategories();
$categories = [];
$sql = mysqli_query($mysqli, "SELECT ticket_category AS cat, COUNT(ticket_id) AS opened, SUM(ticket_resolved_at IS NOT NULL) AS resolved,
        SUM(ticket_priority IN ('Urgent', 'High')) AS urgent
    FROM tickets WHERE $filter AND $opened_in GROUP BY ticket_category");
while ($row = mysqli_fetch_assoc($sql)) {
    $cat = intval($row['cat']);
    $categories[$cat] = ['category' => $category_names[$cat] ?? 'No category', 'cat' => $cat, 'opened' => intval($row['opened']), 'resolved' => intval($row['resolved']),
        'urgent' => intval($row['urgent']), 'seconds' => 0, 'resolve' => null];
}
$resolve_times = [];
$sql = mysqli_query($mysqli, "SELECT ticket_category AS cat, TIMESTAMPDIFF(SECOND, ticket_created_at, ticket_resolved_at) AS s FROM tickets WHERE $filter AND $opened_in AND ticket_resolved_at IS NOT NULL");
while ($row = mysqli_fetch_assoc($sql)) {
    $resolve_times[intval($row['cat'])][] = floatval($row['s']);
}
$sql = mysqli_query($mysqli, "SELECT ticket_category AS cat, SUM(TIME_TO_SEC(ticket_reply_time_worked)) AS s FROM ticket_replies JOIN tickets ON ticket_reply_ticket_id = ticket_id
    WHERE $filter AND $opened_in AND ticket_reply_archived_at IS NULL GROUP BY ticket_category");
while ($row = mysqli_fetch_assoc($sql)) {
    if (isset($categories[intval($row['cat'])])) {
        $categories[intval($row['cat'])]['seconds'] = intval($row['s']);
    }
}
foreach ($categories as $cat => &$row) {
    $row['resolve'] = reportMedian($resolve_times[$cat] ?? []);
    $row['share'] = reportShare($row['opened'], $opened, 1);
}
unset($row);
uasort($categories, function ($a, $b) { return $b['opened'] <=> $a['opened']; });
$categories = array_values($categories);

// Weekday by hour, of what was opened
$heat = [];
$sql = mysqli_query($mysqli, "SELECT WEEKDAY(ticket_created_at) + 1 AS d, HOUR(ticket_created_at) AS h, COUNT(ticket_id) AS n FROM tickets WHERE $filter AND $opened_in GROUP BY d, h");
while ($row = mysqli_fetch_assoc($sql)) {
    $heat[intval($row['d'])][intval($row['h'])] = intval($row['n']);
}

// Who raises the most
$top_clients = [];
if (!$client_id) {
    $sql = mysqli_query($mysqli, "SELECT ticket_client_id AS client_id, client_name, client_abbreviation, COUNT(ticket_id) AS n
        FROM tickets JOIN clients ON ticket_client_id = client_id WHERE $filter AND $opened_in
        GROUP BY ticket_client_id, client_name, client_abbreviation ORDER BY n DESC LIMIT 8");
    while ($row = mysqli_fetch_assoc($sql)) {
        $top_clients[] = $row;
    }
}
$top_contacts = [];
$sql = mysqli_query($mysqli, "SELECT contact_id, contact_name, contact_client_id, client_name, COUNT(ticket_id) AS n
    FROM tickets JOIN contacts ON ticket_contact_id = contact_id LEFT JOIN clients ON contact_client_id = client_id
    WHERE $filter AND $opened_in GROUP BY contact_id, contact_name, contact_client_id, client_name ORDER BY n DESC LIMIT 8");
while ($row = mysqli_fetch_assoc($sql)) {
    $top_contacts[] = $row;
}

$category_columns = [
    'category' => ['label' => 'Category'],
    'opened' => ['label' => 'Opened', 'type' => 'count'],
    'share' => ['label' => 'Share', 'type' => 'percent', 'class' => 'text-body-secondary'],
    'urgent' => ['label' => 'Urgent or high', 'type' => 'count'],
    'resolved' => ['label' => 'Resolved', 'type' => 'count'],
    'resolve' => ['label' => 'Typical time to resolve', 'type' => 'duration', 'title' => 'The middle ticket - half took longer, half less'],
    'seconds' => ['label' => 'Time logged', 'type' => 'hours'],
];

if (reportExporting('tickets')) {
    $tickets = [];
    $sql = mysqli_query($mysqli, "SELECT ticket_id, ticket_prefix, ticket_number, ticket_subject, ticket_category, ticket_priority, ticket_source, ticket_status_name,
            ticket_created_at, ticket_first_response_at, ticket_resolved_at, client_name, user_name,
            (SELECT SUM(TIME_TO_SEC(ticket_reply_time_worked)) FROM ticket_replies WHERE ticket_reply_ticket_id = ticket_id AND ticket_reply_archived_at IS NULL) AS seconds
        FROM tickets
        LEFT JOIN clients ON ticket_client_id = client_id
        LEFT JOIN users ON ticket_assigned_to = user_id
        LEFT JOIN ticket_statuses ON ticket_status = ticket_status_id
        WHERE $filter AND $opened_in ORDER BY ticket_created_at ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $row['category'] = $category_names[intval($row['ticket_category'])] ?? '';
        $row['response'] = $row['ticket_first_response_at'] ? strtotime($row['ticket_first_response_at']) - strtotime($row['ticket_created_at']) : null;
        $tickets[] = $row;
    }
    reportCsv('Tickets ' . $period['start'] . ' to ' . $period['end'], [
        'ticket' => ['label' => 'Ticket', 'csv' => function ($row) { return ticketReference($row); }],
        'ticket_subject' => ['label' => 'Subject'],
        'client_name' => ['label' => 'Client'],
        'category' => ['label' => 'Category'],
        'ticket_priority' => ['label' => 'Priority'],
        'ticket_source' => ['label' => 'Source'],
        'ticket_status_name' => ['label' => 'Status'],
        'user_name' => ['label' => 'Assigned to'],
        'ticket_created_at' => ['label' => 'Opened'],
        'response' => ['label' => 'Hours to first reply', 'type' => 'hours'],
        'ticket_resolved_at' => ['label' => 'Resolved'],
        'seconds' => ['label' => 'Hours logged', 'type' => 'hours'],
    ], $tickets);
}
if (reportExporting()) {
    reportCsv('Tickets by Category ' . $period['start'] . ' to ' . $period['end'], $category_columns, $categories, ['category' => 'Total', 'opened' => $opened]);
}

require_once "includes/inc_report_layout.php";

$ticket_list = '/agent/tickets.php?' . http_build_query(array_filter(['client_id' => $client_id ?: null, 'assigned' => $agent_id ?: null]));
$source_icons = ['Email' => 'envelope', 'Portal' => 'globe', 'Agent' => 'headset', 'API' => 'plug', 'Recurring' => 'redo-alt', 'Project' => 'project-diagram'];
$priority_tones = ['Urgent' => 'dark', 'High' => 'danger', 'Medium' => 'warning', 'Low' => 'info'];
$source_rows = reportTopN(array_map(function ($k, $v) { return ['label' => $k, 'value' => $v]; }, array_keys($sources), array_values($sources)), 6);
$source_colors = array_map(function ($i) { return reportPalette($i); }, array_keys($source_rows));
$priority_order = array_filter(['Urgent' => $priorities['Urgent'] ?? 0, 'High' => $priorities['High'] ?? 0, 'Medium' => $priorities['Medium'] ?? 0, 'Low' => $priorities['Low'] ?? 0]);
$top_categories = array_slice($categories, 0, 10);

?>

<div class="itflow-report">

    <?php reportHeader('ticket_summary', [
        'period' => $period,
        'years' => reportPastYears("SELECT DISTINCT YEAR(ticket_created_at) AS y FROM tickets"),
        'client' => $client_id,
        'agent' => $agent_id,
        'export' => ['categories' => 'By category', 'tickets' => 'Every ticket opened'],
    ]); ?>

    <?= reportStats([
        ['label' => 'Opened', 'value' => reportCount($opened), 'sub' => reportChangeHtml($opened, $opened_prev, 'down', $period['compare']), 'url' => $ticket_list . '&show=all'],
        ['label' => 'Resolved', 'value' => reportCount($resolved), 'sub' => reportChangeHtml($resolved, $resolved_prev, 'up', $period['compare'])],
        ['label' => 'Open now', 'value' => reportCount($open_now), 'url' => $ticket_list, 'sub' => '<span class="text-body-tertiary">Whenever they were raised</span>'],
        ['label' => 'First reply', 'value' => reportDuration($response), 'title' => 'The middle ticket - half got a first reply sooner, half later',
            'sub' => $response_prev !== null ? 'Typically · <span class="text-body-tertiary">was ' . reportDuration($response_prev) . '</span>' : '<span class="text-body-tertiary">Typically</span>'],
        ['label' => 'Time to resolve', 'value' => reportDuration($resolve), 'title' => 'The middle ticket - half were resolved sooner, half later',
            'sub' => $resolve_prev !== null ? 'Typically · <span class="text-body-tertiary">was ' . reportDuration($resolve_prev) . '</span>' : '<span class="text-body-tertiary">Typically</span>'],
        ['label' => 'Time logged', 'value' => reportHours($time_logged) . ' h', 'sub' => '<span class="text-body-tertiary">On replies in the period</span>'],
    ]) ?>

    <?= reportSectionOpen('Opened and resolved', escapeHtml($period['range']) . ' · by ' . $buckets['unit']) ?>
        <div class="card-body">
            <?= reportChart([
                'type' => 'bar',
                'labels' => $buckets['labels'],
                'series' => [
                    ['label' => 'Opened', 'data' => $opened_series, 'tone' => 'accent'],
                    ['label' => 'Resolved', 'data' => $resolved_series, 'tone' => 'success'],
                ],
                'format' => 'count',
            ], '', 'Tickets opened and resolved over the period') ?>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-md-6 col-xl-3">
            <?= reportSectionOpen('Where they came from', '', 'h-100 mb-0') ?>
                <div class="card-body">
                    <?php if ($source_rows) { ?>
                        <?= reportChart(['type' => 'doughnut', 'labels' => array_column($source_rows, 'label'), 'series' => [['label' => 'Tickets', 'data' => array_column($source_rows, 'value')]], 'colors' => $source_colors, 'format' => 'count'], 'donut', 'Tickets by source') ?>
                        <div class="mt-3"><?= reportLegendHtml(array_column($source_rows, 'label'), array_column($source_rows, 'value'), $source_colors, 'count') ?></div>
                    <?php } else { ?>
                        <div class="text-center text-body-secondary py-4">No tickets.</div>
                    <?php } ?>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <?= reportSectionOpen('How urgent', '', 'h-100 mb-0') ?>
                <div class="card-body">
                    <?php if ($priority_order) {
                        $priority_colors = ['Urgent' => 'dark', 'High' => 'danger', 'Medium' => 'warning', 'Low' => 'info'];
                        $colors = array_map(function ($p) use ($priority_colors) { return $priority_colors[$p]; }, array_keys($priority_order)); ?>
                        <?= reportChart(['type' => 'doughnut', 'labels' => array_keys($priority_order), 'series' => [['label' => 'Tickets', 'data' => array_values($priority_order)]], 'colors' => $colors, 'format' => 'count'], 'donut', 'Tickets by priority') ?>
                        <div class="mt-3"><?= reportLegendHtml(array_keys($priority_order), array_values($priority_order), $colors, 'count') ?></div>
                    <?php } else { ?>
                        <div class="text-center text-body-secondary py-4">No tickets.</div>
                    <?php } ?>
                </div>
            </div>
        </div>
        <div class="col-xl-6">
            <?= reportSectionOpen('When they come in', 'Opened, by weekday and hour', 'h-100 mb-0') ?>
                <div class="card-body">
                    <?= reportHeatmap($heat) ?>
                    <p class="small text-body-secondary mt-2 mb-0">Darker is busier - useful for planning who is on when.</p>
                </div>
            </div>
        </div>
    </div>

    <?php if ($top_categories) { ?>
        <?= reportSectionOpen('What they were about', 'The most common categories') ?>
            <div class="card-body">
                <?= reportChart([
                    'type' => 'bar',
                    'horizontal' => true,
                    'labels' => array_column($top_categories, 'category'),
                    'series' => [['label' => 'Opened', 'data' => array_column($top_categories, 'opened'), 'tone' => 'accent']],
                    'format' => 'count',
                    'legend' => false,
                ], '', 'Tickets opened in the most common categories') ?>
            </div>
        </div>
    <?php } ?>

    <div class="row g-3">
        <?php if (!$client_id) { ?>
            <div class="col-lg-6">
                <?= reportSectionOpen('Clients raising the most', '', 'h-100 mb-0') ?>
                    <?php if ($top_clients) {
                        $max = max(1, intval($top_clients[0]['n'])); ?>
                        <div class="card-body pt-2">
                            <div class="itflow-dash-bars">
                                <?php foreach ($top_clients as $row) { ?>
                                    <a class="itflow-dash-bar" href="/agent/tickets.php?client_id=<?= intval($row['client_id']) ?>&show=all">
                                        <span class="itflow-dash-bar-label"><?= escapeHtml($row['client_name']) ?></span>
                                        <span class="itflow-dash-bar-value"><?= intval($row['n']) ?></span>
                                        <span class="itflow-dash-bar-track"><span class="itflow-dash-bar-fill" style="width: <?= round(intval($row['n']) / $max * 100, 1) ?>%"></span></span>
                                    </a>
                                <?php } ?>
                            </div>
                        </div>
                    <?php } else { ?>
                        <div class="card-body text-center text-body-secondary py-4">No tickets.</div>
                    <?php } ?>
                </div>
            </div>
        <?php } ?>
        <div class="<?= $client_id ? 'col-12' : 'col-lg-6' ?>">
            <?= reportSectionOpen('People raising the most', 'Contacts, with their client', 'h-100 mb-0') ?>
                <?php if ($top_contacts) {
                    $max = max(1, intval($top_contacts[0]['n'])); ?>
                    <div class="card-body pt-2">
                        <div class="itflow-dash-bars">
                            <?php foreach ($top_contacts as $row) { ?>
                                <a class="itflow-dash-bar" href="/agent/contact.php?client_id=<?= intval($row['contact_client_id']) ?>&contact_id=<?= intval($row['contact_id']) ?>">
                                    <span class="itflow-dash-bar-label"><?= escapeHtml($row['contact_name']) ?> <span class="small text-body-secondary"><?= escapeHtml($row['client_name'] ?? '') ?></span></span>
                                    <span class="itflow-dash-bar-value"><?= intval($row['n']) ?></span>
                                    <span class="itflow-dash-bar-track"><span class="itflow-dash-bar-fill bg-info" style="width: <?= round(intval($row['n']) / $max * 100, 1) ?>%"></span></span>
                                </a>
                            <?php } ?>
                        </div>
                    </div>
                <?php } else { ?>
                    <div class="card-body text-center text-body-secondary py-4">No tickets with a contact.</div>
                <?php } ?>
            </div>
        </div>
    </div>

    <?= reportSectionOpen('By category', 'Tickets opened in the period') ?>
        <?= reportTable($category_columns, $categories, ['empty' => 'No tickets were opened in this period.', 'totals' => ['category' => 'Total', 'opened' => $opened,
            'urgent' => array_sum(array_column($categories, 'urgent')), 'resolved' => array_sum(array_column($categories, 'resolved')), 'seconds' => array_sum(array_column($categories, 'seconds'))]]) ?>
        <div class="card-footer small text-body-secondary">
            Times run on the clock from when a ticket was raised. "Typical" is the middle ticket, so a few that sat open for weeks do not skew it. Business-hours targets are in the SLA reports.
        </div>
    </div>

</div>

<?php require_once "../../includes/footer.php"; ?>
<script src="/js/reports.js"></script>
