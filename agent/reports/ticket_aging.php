<?php

/*
 * Open Ticket Aging - the backlog right now: every open ticket by how long it
 * has been open, per agent and per client, then the oldest tickets one by
 * one with whose move it is (ours or the client's), their SLA and when
 * anything last happened. "Waiting on us" uses the same rule as the ticket
 * list's Needs reply view.
 */

require_once "includes/inc_report.php";
reportEnforce('ticket_aging');

$client_id = reportClientId();
$agent_filter = $_GET['agent'] ?? '';
$unassigned_only = $agent_filter === 'none';
$agent_id = $unassigned_only ? 0 : reportAgentId();

$where = reportTicketSql($client_id, $agent_id) . " AND " . TICKET_OPEN_SQL . ($unassigned_only ? " AND tickets.ticket_assigned_to = 0" : '');

$tickets = [];
$sql = mysqli_query($mysqli, "
    SELECT tickets.ticket_id, ticket_prefix, ticket_number, ticket_subject, ticket_priority, ticket_client_id, ticket_assigned_to, ticket_created_at, ticket_updated_at,
        ticket_resolved_at, ticket_closed_at, ticket_due_at, ticket_sla_id, ticket_first_response_at, ticket_response_due_at, ticket_resolution_due_at,
        ticket_response_sla_met, ticket_resolution_sla_met, ticket_response_sla_alert_stage, ticket_resolution_sla_alert_stage,
        ticket_status_name, ticket_status_color, ticket_status_pauses_sla, user_name, user_avatar, client_name, client_abbreviation,
        " . ticketLastSpokeSql() . " AS ticket_last_spoke,
        TIMESTAMPDIFF(SECOND, ticket_created_at, NOW()) AS age_seconds,
        (SELECT MAX(ticket_reply_created_at) FROM ticket_replies WHERE ticket_reply_ticket_id = tickets.ticket_id AND ticket_reply_archived_at IS NULL) AS last_reply
    FROM tickets
    LEFT JOIN ticket_statuses ON ticket_status = ticket_status_id
    LEFT JOIN users ON ticket_assigned_to = user_id
    LEFT JOIN clients ON ticket_client_id = client_id
    WHERE $where
    ORDER BY ticket_created_at ASC");
while ($row = mysqli_fetch_assoc($sql)) {
    $row['age_days'] = intval($row['age_seconds']) / 86400;
    $row['bucket'] = reportAgeBucket(intdiv(intval($row['age_seconds']), 86400));
    $row['move'] = ticketWhoseMove($row);
    $row['sla'] = ticketSlaState($row);
    $row['due'] = ticketDueState($row);
    $row['late'] = in_array($row['sla']['key'], ['breached'], true) || ($row['due']['key'] ?? '') === 'overdue';
    $row['last_activity'] = max($row['last_reply'] ?? '', $row['ticket_updated_at'] ?? '', $row['ticket_created_at']);
    $tickets[] = $row;
}

$bucket_keys = array_keys(REPORT_AGE_BUCKETS);
$empty = array_fill_keys($bucket_keys, 0) + ['total' => 0, 'us' => 0, 'late' => 0];
$totals = $empty;
$by_agent = [];
$by_client = [];
foreach ($tickets as $ticket) {
    $a = intval($ticket['ticket_assigned_to']);
    $c = intval($ticket['ticket_client_id']);
    $by_agent[$a] = $by_agent[$a] ?? ['user_id' => $a, 'agent' => $a ? ($ticket['user_name'] ?? '') : '', 'avatar' => $ticket['user_avatar'] ?? ''] + $empty;
    $by_client[$c] = $by_client[$c] ?? ['client_id' => $c, 'client' => $ticket['client_name'] ?? '', 'abbr' => $ticket['client_abbreviation'] ?? ''] + $empty;
    foreach ([&$by_agent[$a], &$by_client[$c], &$totals] as &$group) {
        $group[$ticket['bucket']]++;
        $group['total']++;
        $group['us'] += $ticket['move'] === 'us' ? 1 : 0;
        $group['late'] += $ticket['late'] ? 1 : 0;
    }
    unset($group);
}
$sort = function ($a, $b) {
    return $b['old'] + $b['90'] <=> $a['old'] + $a['90'] ?: $b['total'] <=> $a['total'];
};
uasort($by_agent, $sort);
uasort($by_client, $sort);

$count_cell = function ($key) {
    return function ($row) use ($key) {
        if (!$row[$key]) {
            return '<span class="itflow-report-zero">–</span>';
        }
        $tone = in_array($key, ['90', 'old'], true) ? 'text-danger-emphasis fw-semibold' : ($key === '30' ? 'text-warning-emphasis fw-semibold' : '');
        return '<span class="' . $tone . '">' . $row[$key] . '</span>';
    };
};
$bucket_columns = [];
foreach (REPORT_AGE_BUCKETS as $key => [$label]) {
    $bucket_columns[$key] = ['label' => $label, 'type' => 'count', 'html' => $count_cell($key)];
}
$tail_columns = [
    'total' => ['label' => 'Open', 'type' => 'count', 'class' => 'fw-semibold'],
    'us' => ['label' => 'Waiting on us', 'type' => 'count', 'title' => 'The client spoke last, or nobody has replied yet'],
    'late' => ['label' => 'Late', 'type' => 'count', 'title' => 'Past their SLA or their due date', 'html' => function ($row) {
        return $row['late'] ? '<span class="text-danger-emphasis fw-semibold">' . $row['late'] . '</span>' : '<span class="itflow-report-zero">–</span>';
    }],
];
$agent_columns = ['agent' => ['label' => 'Agent', 'csv' => function ($row) { return $row['user_id'] ? $row['agent'] : 'Unassigned'; }, 'html' => function ($row) use ($client_id) {
    $url = '/agent/tickets.php?' . http_build_query(array_filter(['client_id' => $client_id ?: null, 'assigned' => $row['user_id'] ?: 'unassigned']));
    return '<a class="text-decoration-none" href="' . escapeHtml($url) . '">' . formatAssignee($row['user_id'], $row['agent'], $row['avatar'], true) . '</a>';
}]] + $bucket_columns + $tail_columns;
$client_columns = ['client' => ['label' => 'Client', 'csv' => function ($row) { return $row['client_id'] ? $row['client'] : 'No client'; }, 'html' => function ($row) {
    return reportClientCell($row['client_id'], $row['client'], $row['abbr'], 'tickets.php');
}]] + $bucket_columns + $tail_columns;

$move_labels = ['us' => ['Waiting on us', 'danger', 'fa-reply'], 'client' => ['Waiting on client', 'secondary', 'fa-hourglass-half'], 'parked' => ['Parked', 'secondary', 'fa-pause']];
$ticket_columns = [
    'ticket' => ['label' => 'Ticket', 'class' => 'itflow-report-text', 'csv' => function ($row) { return ticketReference($row) . ' ' . $row['ticket_subject']; }, 'html' => function ($row) {
        return reportPriorityDot($row['ticket_priority']) . reportTicketLink($row);
    }],
    'client_name' => ['label' => 'Client', 'html' => function ($row) {
        return reportClientCell($row['ticket_client_id'], $row['client_name'] ?? '', $row['client_abbreviation'] ?? '');
    }],
    'user_name' => ['label' => 'Agent', 'csv' => function ($row) { return $row['user_name'] ?: 'Unassigned'; }, 'html' => function ($row) {
        return formatAssignee($row['ticket_assigned_to'], $row['user_name'] ?? '', $row['user_avatar'] ?? '', true);
    }],
    'ticket_status_name' => ['label' => 'Status', 'html' => function ($row) {
        $color = preg_match('/^#[0-9a-f]{3,8}$/i', (string) $row['ticket_status_color']) ? $row['ticket_status_color'] : '#6c757d';
        return '<span class="badge rounded-pill text-white" style="background-color: ' . $color . '">' . escapeHtml($row['ticket_status_name']) . '</span>';
    }],
    'move' => ['label' => 'Whose move', 'csv' => function ($row) use ($move_labels) { return $move_labels[$row['move']][0] ?? ''; }, 'html' => function ($row) use ($move_labels) {
        if (!isset($move_labels[$row['move']])) {
            return '';
        }
        [$label, $tone, $icon] = $move_labels[$row['move']];
        return '<span class="small text-nowrap text-' . $tone . '-emphasis"><i class="fas ' . $icon . ' me-1"></i>' . $label . '</span>';
    }],
    'sla' => ['label' => 'SLA / due', 'sort' => false, 'csv' => function ($row) { return trim(($row['sla']['key'] !== 'none' ? $row['sla']['key'] : '') . ' ' . ($row['due'] ? 'due ' . $row['ticket_due_at'] : '')); }, 'html' => function ($row) {
        return '<span class="d-inline-flex flex-wrap gap-1">' . ticketSlaPill($row['sla']) . ticketDuePill($row['due']) . '</span>';
    }],
    'age_days' => ['label' => 'Open for', 'type' => 'decimal', 'decimals' => 1, 'csv_label' => 'Days open', 'html' => function ($row) {
        $days = $row['age_days'];
        $tone = $days > 30 ? 'text-danger-emphasis fw-semibold' : ($days > 7 ? 'text-warning-emphasis fw-semibold' : '');
        return '<span class="' . $tone . '" title="Opened ' . escapeHtml($row['ticket_created_at']) . '">' . reportDuration($row['age_seconds']) . '</span>';
    }],
    'last_activity' => ['label' => 'Last activity', 'type' => 'date', 'csv' => function ($row) { return $row['last_activity']; }, 'html' => function ($row) {
        return '<span class="small text-body-secondary text-nowrap" title="' . escapeHtml($row['last_activity']) . '">' . escapeHtml(timeAgo($row['last_activity'])) . '</span>';
    }],
];

if (reportExporting('agents')) {
    reportCsv('Open Tickets by Agent', $agent_columns, array_values($by_agent), ['agent' => 'Total'] + $totals);
}
if (reportExporting('clients')) {
    reportCsv('Open Tickets by Client', $client_columns, array_values($by_client), ['client' => 'Total'] + $totals);
}
if (reportExporting()) {
    reportCsv('Open Tickets', $ticket_columns, $tickets);
}

require_once "includes/inc_report_layout.php";

$unassigned = $by_agent[0]['total'] ?? 0;
$oldest = $tickets[0] ?? null;
$bucket_tones = ['day' => 'success', '3' => 'info', '7' => 'accent', '30' => 'warning', '90' => 'orange', 'old' => 'danger'];

$agent_choices = '<div class="col-sm-6 col-lg-2"><select class="form-select select2" name="agent" data-report-autosubmit aria-label="Agent">'
    . '<option value="">- All agents -</option>'
    . '<option value="none"' . ($unassigned_only ? ' selected' : '') . ' data-icon="fas fa-user-slash">Unassigned</option>';
$sql = mysqli_query($mysqli, "SELECT user_id, user_name, user_avatar FROM users WHERE user_type = 1 AND ((user_archived_at IS NULL AND user_status = 1) OR user_id = $agent_id) ORDER BY user_name");
while ($row = mysqli_fetch_assoc($sql)) {
    $id = intval($row['user_id']);
    $agent_choices .= '<option value="' . $id . '"' . ($id === $agent_id ? ' selected' : '') . selectOptionUser($id, $row['user_name'], $row['user_avatar'] ?? '') . '>' . escapeHtml($row['user_name']) . '</option>';
}
$agent_choices .= '</select></div>';

?>

<div class="itflow-report">

    <?php reportHeader('ticket_aging', [
        'client' => $client_id,
        'controls' => $agent_choices,
        'subtitle' => 'As of ' . date('M j, Y g:i A'),
        'export' => ['tickets' => 'Every open ticket', 'agents' => 'By agent', 'clients' => 'By client'],
    ]); ?>

    <?= reportStats([
        ['label' => 'Open tickets', 'value' => reportCount(count($tickets)), 'url' => '/agent/tickets.php' . ($client_id ? "?client_id=$client_id" : ''),
            'sub' => reportCount($totals['30'] + $totals['90'] + $totals['old']) . ' <span class="text-body-tertiary">open over a week</span>'],
        ['label' => 'Waiting on us', 'value' => reportCount($totals['us']), 'tone' => $totals['us'] ? 'warning' : '', 'url' => '/agent/tickets.php?show=reply' . ($client_id ? "&client_id=$client_id" : ''),
            'sub' => '<span class="text-body-tertiary">The client spoke last, or no reply yet</span>'],
        ['label' => 'Late', 'value' => reportCount($totals['late']), 'tone' => $totals['late'] ? 'danger' : 'success', 'url' => '/agent/tickets.php?show=overdue' . ($client_id ? "&client_id=$client_id" : ''),
            'sub' => '<span class="text-body-tertiary">Past their SLA or due date</span>'],
        ['label' => 'Unassigned', 'value' => reportCount($unassigned), 'tone' => $unassigned ? 'warning' : '', 'url' => '/agent/tickets.php?assigned=unassigned' . ($client_id ? "&client_id=$client_id" : ''),
            'sub' => $oldest ? 'Oldest open <span class="text-body">' . reportDuration($oldest['age_seconds']) . '</span>' : ''],
    ]) ?>

    <?php if ($tickets) { ?>
        <?= reportSectionOpen('How long they have been open', '') ?>
            <div class="card-body">
                <?= reportChart([
                    'type' => 'bar',
                    'horizontal' => true,
                    'labels' => array_map(function ($b) { return $b[0]; }, array_values(REPORT_AGE_BUCKETS)),
                    'series' => [['label' => 'Open tickets', 'data' => array_map(function ($k) use ($totals) { return $totals[$k]; }, $bucket_keys),
                        'tones' => array_map(function ($k) use ($bucket_tones) { return $bucket_tones[$k]; }, $bucket_keys)]],
                    'format' => 'count',
                    'legend' => false,
                ], '', 'Open tickets by how long they have been open') ?>
            </div>
        </div>
    <?php } ?>

    <?= reportSectionOpen('By agent', 'The longest-open work first') ?>
        <?= reportTable($agent_columns, array_values($by_agent), ['empty' => reportEmpty('No open tickets.'), 'totals' => ['agent' => 'Total'] + $totals]) ?>
    </div>

    <?php if (!$client_id) { ?>
        <?= reportSectionOpen('By client', 'The longest-open work first') ?>
            <?= reportTable($client_columns, array_values($by_client), ['empty' => reportEmpty('No open tickets.'), 'limit' => 50, 'export_table' => 'clients', 'totals' => ['client' => 'Total'] + $totals]) ?>
        </div>
    <?php } ?>

    <?php if ($tickets) { ?>
        <?= reportSectionOpen('Every open ticket', 'Oldest first', 'itflow-report-long') ?>
            <?= reportTable($ticket_columns, $tickets, ['limit' => 300, 'export_table' => 'tickets']) ?>
            <div class="card-footer small text-body-secondary">
                Open is neither resolved nor closed. Ages run on the clock from when the ticket was raised; the SLA pills use business hours.
                "Waiting on us" means the client wrote last, or nobody has replied yet; a status that pauses the SLA (such as On Hold) parks a ticket.
            </div>
        </div>
    <?php } ?>

</div>

<?php require_once "../../includes/footer.php"; ?>
<script src="/js/reports.js"></script>
