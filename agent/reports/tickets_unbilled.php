<?php

/*
 * Unbilled Work - billable tickets that are resolved or closed but on no
 * invoice yet: money left on the table. Each ticket's time is rounded to the
 * nearest 15 minutes and priced at the client's hourly rate - the same
 * quantity and price Invoice fills in on the ticket - so the value
 * here is what invoicing them would come to. Per client, then every ticket,
 * the longest waiting first.
 */

require_once "includes/inc_report.php";
reportEnforce('tickets_unbilled');

$client_id = reportClientId();
$default_rate = floatval($config_default_hourly_rate);

$tickets = [];
$sql = mysqli_query($mysqli, "
    SELECT ticket_id, ticket_prefix, ticket_number, ticket_subject, ticket_client_id, ticket_resolved_at, ticket_closed_at, ticket_assigned_to,
        user_name, user_avatar, client_name, client_abbreviation, client_rate,
        COALESCE((SELECT SUM(TIME_TO_SEC(ticket_reply_time_worked)) FROM ticket_replies WHERE ticket_reply_ticket_id = ticket_id AND ticket_reply_archived_at IS NULL), 0) AS seconds,
        DATEDIFF(CURDATE(), COALESCE(ticket_resolved_at, ticket_closed_at)) AS waiting
    FROM tickets
    JOIN clients ON ticket_client_id = client_id
    LEFT JOIN users ON ticket_assigned_to = user_id
    WHERE ticket_billable = 1 AND ticket_invoice_id = 0 AND ticket_archived_at IS NULL
    AND (ticket_resolved_at IS NOT NULL OR ticket_closed_at IS NOT NULL)
    " . reportClientSql('ticket_client_id', $client_id) . "
    ORDER BY waiting DESC, ticket_number ASC");
while ($row = mysqli_fetch_assoc($sql)) {
    $seconds = intval($row['seconds']);
    // To the nearest quarter hour, as roundToNearest15Min() does for the invoice line
    $quarters = intdiv($seconds, 900) + ($seconds % 900 > 450 ? 1 : 0);
    $row['billable_hours'] = $quarters * 0.25;
    $rate = floatval($row['client_rate']) > 0 ? floatval($row['client_rate']) : $default_rate;
    $row['rate'] = $rate;
    $row['default_rate'] = floatval($row['client_rate']) <= 0;
    $row['value'] = $row['billable_hours'] * $rate;
    $row['seconds'] = $seconds;
    $row['waiting'] = intval($row['waiting']);
    $tickets[] = $row;
}

$clients = [];
foreach ($tickets as $ticket) {
    $id = intval($ticket['ticket_client_id']);
    $clients[$id] = $clients[$id] ?? ['client_id' => $id, 'client' => $ticket['client_name'], 'abbr' => $ticket['client_abbreviation'], 'rate' => $ticket['rate'],
        'default_rate' => $ticket['default_rate'], 'tickets' => 0, 'seconds' => 0, 'billable_hours' => 0.0, 'value' => 0.0, 'oldest' => 0, 'no_time' => 0];
    $clients[$id]['tickets']++;
    $clients[$id]['seconds'] += $ticket['seconds'];
    $clients[$id]['billable_hours'] += $ticket['billable_hours'];
    $clients[$id]['value'] += $ticket['value'];
    $clients[$id]['oldest'] = max($clients[$id]['oldest'], $ticket['waiting']);
    $clients[$id]['no_time'] += $ticket['billable_hours'] <= 0 ? 1 : 0;
}
uasort($clients, function ($a, $b) { return $b['value'] <=> $a['value'] ?: $b['tickets'] <=> $a['tickets']; });
$clients = array_values($clients);

$waiting_html = function ($days) {
    $tone = $days > 30 ? 'danger' : ($days > 7 ? 'warning' : 'secondary');
    return '<span class="badge rounded-pill bg-' . $tone . '-subtle text-' . $tone . '-emphasis border border-' . $tone . '-subtle fw-normal">'
        . ($days <= 0 ? 'Today' : $days . ' day' . ($days === 1 ? '' : 's')) . '</span>';
};
$rate_html = function ($row) {
    if ($row['rate'] <= 0) {
        return '<span class="text-warning-emphasis small" title="Set an hourly rate on the client, or a default in Admin > Settings > Invoices">No rate</span>';
    }
    return reportMoney($row['rate']) . ($row['default_rate'] ? ' <span class="small text-body-tertiary" title="The client has no rate of its own - this is the default hourly rate">default</span>' : '');
};

$client_columns = [
    'client' => ['label' => 'Client', 'html' => function ($row) {
        return reportClientCell($row['client_id'], $row['client'], $row['abbr']);
    }],
    'tickets' => ['label' => 'Tickets', 'type' => 'count', 'html' => function ($row) {
        return '<a class="itflow-report-amount" href="/agent/tickets.php?client_id=' . $row['client_id'] . '&billing=unbilled&state=closed">' . reportCount($row['tickets']) . '</a>'
            . ($row['no_time'] ? ' <span class="small text-warning-emphasis" title="Tickets with under 8 minutes logged - nothing to bill unless you add time">(' . $row['no_time'] . ' with no time)</span>' : '');
    }],
    'seconds' => ['label' => 'Time logged', 'type' => 'hours', 'decimals' => 2],
    'billable_hours' => ['label' => 'To bill', 'type' => 'decimal', 'decimals' => 2, 'title' => 'Hours rounded to the nearest 15 minutes, ticket by ticket'],
    'rate' => ['label' => 'Rate', 'type' => 'money', 'html' => $rate_html],
    'value' => ['label' => 'Value', 'type' => 'money', 'class' => 'fw-semibold'],
    'oldest' => ['label' => 'Oldest waiting', 'type' => 'count', 'csv_label' => 'Oldest days waiting', 'html' => function ($row) use ($waiting_html) {
        return $waiting_html($row['oldest']);
    }],
];
$ticket_columns = [
    'ticket' => ['label' => 'Ticket', 'csv' => function ($row) { return ticketReference($row) . ' ' . $row['ticket_subject']; }, 'html' => function ($row) {
        return reportTicketLink($row);
    }, 'class' => 'itflow-report-text'],
    'client_name' => ['label' => 'Client', 'html' => function ($row) {
        return reportClientCell($row['ticket_client_id'], $row['client_name'], $row['client_abbreviation']);
    }],
    'user_name' => ['label' => 'Agent', 'html' => function ($row) {
        return reportAgentCell($row['ticket_assigned_to'], $row['user_name'] ?? '', $row['user_avatar'] ?? '');
    }],
    'resolved' => ['label' => 'Done', 'type' => 'date', 'class' => 'text-nowrap', 'csv' => function ($row) {
        return substr($row['ticket_resolved_at'] ?: $row['ticket_closed_at'], 0, 10);
    }, 'html' => function ($row) {
        return escapeHtml(date('M j, Y', strtotime($row['ticket_resolved_at'] ?: $row['ticket_closed_at'])));
    }],
    'waiting' => ['label' => 'Waiting', 'type' => 'count', 'csv_label' => 'Days waiting', 'html' => function ($row) use ($waiting_html) {
        return $waiting_html($row['waiting']);
    }],
    'seconds' => ['label' => 'Time logged', 'type' => 'hours', 'decimals' => 2, 'html' => function ($row) {
        return $row['seconds'] <= 0 ? '<span class="text-warning-emphasis" title="No time logged on this ticket">None</span>' : reportHours($row['seconds'], 2);
    }],
    'billable_hours' => ['label' => 'To bill', 'type' => 'decimal', 'decimals' => 2],
    'value' => ['label' => 'Value', 'type' => 'money', 'class' => 'fw-semibold'],
];
foreach ($tickets as &$ticket) {
    $ticket['resolved'] = $ticket['ticket_resolved_at'] ?: $ticket['ticket_closed_at'];
}
unset($ticket);

$totals = ['tickets' => count($tickets), 'seconds' => array_sum(array_column($tickets, 'seconds')),
    'billable_hours' => array_sum(array_column($tickets, 'billable_hours')), 'value' => array_sum(array_column($tickets, 'value'))];

if (reportExporting('tickets')) {
    reportCsv('Unbilled Tickets', $ticket_columns, $tickets, ['ticket' => 'Total'] + $totals);
}
if (reportExporting()) {
    reportCsv('Unbilled Work by Client', $client_columns, $clients, ['client' => 'Total'] + $totals);
}

require_once "includes/inc_report_layout.php";

$no_time = count(array_filter($tickets, function ($t) { return $t['billable_hours'] <= 0; }));
$no_rate = count(array_filter($tickets, function ($t) { return $t['rate'] <= 0; }));
$over_30 = array_filter($tickets, function ($t) { return $t['waiting'] > 30; });

?>

<div class="itflow-report">

    <?php reportHeader('tickets_unbilled', [
        'client' => $client_id,
        'subtitle' => 'As of ' . date('M j, Y'),
        'export' => ['clients' => 'By client', 'tickets' => 'Every ticket'],
    ]); ?>

    <?= reportStats([
        ['label' => 'Not invoiced yet', 'value' => reportMoney($totals['value']), 'tone' => $totals['value'] > 0 ? 'warning' : '',
            'sub' => '<span class="text-body-tertiary">At each client\'s rate</span>'],
        ['label' => 'Tickets', 'value' => reportCount(count($tickets)), 'url' => '/agent/tickets.php?billing=unbilled&state=closed',
            'sub' => reportCount(count($clients)) . ' <span class="text-body-tertiary">client' . (count($clients) === 1 ? '' : 's') . '</span>'],
        ['label' => 'Hours to bill', 'value' => number_format($totals['billable_hours'], 2), 'sub' => escapeHtml(reportHours($totals['seconds'], 2)) . ' <span class="text-body-tertiary">logged</span>', 'title' => 'Each ticket\'s time rounded to the nearest 15 minutes'],
        ['label' => 'Waiting over 30 days', 'value' => reportCount(count($over_30)), 'tone' => $over_30 ? 'danger' : 'success',
            'sub' => $over_30 ? escapeHtml(reportMoney(array_sum(array_column($over_30, 'value')))) . ' <span class="text-body-tertiary">of it</span>' : '<span class="text-body-tertiary">Nothing left that long</span>'],
    ]) ?>

    <?php if ($no_time || $no_rate) { ?>
        <div class="alert alert-warning d-flex gap-2 align-items-start">
            <i class="fas fa-exclamation-triangle mt-1"></i>
            <div>
                <?php if ($no_time) { ?><div><?= $no_time ?> billable ticket<?= $no_time === 1 ? ' has' : 's have' ?> under 8 minutes logged, so <?= $no_time === 1 ? 'it adds' : 'they add' ?> nothing here - log the time, or mark <?= $no_time === 1 ? 'it' : 'them' ?> not billable.</div><?php } ?>
                <?php if ($no_rate) { ?><div><?= $no_rate ?> ticket<?= $no_rate === 1 ? ' is' : 's are' ?> for a client with no hourly rate - set one on the client, or a default hourly rate in Admin > Settings.</div><?php } ?>
            </div>
        </div>
    <?php } ?>

    <?= reportSectionOpen('By client', 'Most to bill first') ?>
        <?= reportTable($client_columns, $clients, [
            'empty' => reportEmpty('You are all caught up - every billable ticket that is done has been invoiced.'),
            'totals' => ['client' => 'Total'] + $totals,
        ]) ?>
    </div>

    <?php if ($tickets) { ?>
        <?= reportSectionOpen('Every ticket', 'Longest waiting first', 'itflow-report-long') ?>
            <?= reportTable($ticket_columns, $tickets, ['limit' => 300, 'export_table' => 'tickets', 'totals' => ['ticket' => 'Total'] + $totals]) ?>
            <div class="card-footer small text-body-secondary">
                Billable tickets that are resolved or closed and on no invoice. To bill one, open it and choose Invoice - the hours and rate here are what it fills in.
            </div>
        </div>
    <?php } ?>

</div>

<?php require_once "../../includes/footer.php"; ?>
<script src="/js/reports.js"></script>
