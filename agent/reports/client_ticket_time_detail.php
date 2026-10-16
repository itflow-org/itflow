<?php

/*
 * Time Detail by Client - every ticket reply with time on it in the period,
 * grouped by client and ticket, with who logged it and what they wrote: the
 * detail behind an invoice, for a client who asks what the hours were for.
 *
 * Each reply is rounded up to the billing increment picked (6, 15 or 30
 * minutes) and those rounded figures are what add up to the ticket, client
 * and grand totals, priced at the client's hourly rate.
 */

require_once "includes/inc_report.php";
reportEnforce('client_ticket_time_detail');

$period = reportPeriod('last_month');
$client_id = reportClientId();
$agent_id = reportAgentId();
$billable_only = !empty($_GET['billable_only']);

$increments = ['0.1' => [360, '6 minutes'], '0.25' => [900, '15 minutes'], '0.5' => [1800, '30 minutes']];
$increment_key = isset($increments[$_GET['billing_increment'] ?? '']) ? $_GET['billing_increment'] : '0.25';
[$increment, $increment_label] = $increments[$increment_key];
$default_rate = floatval($config_default_hourly_rate);

$sql = mysqli_query($mysqli, "
    SELECT ticket_reply_id, ticket_reply, ticket_reply_type, ticket_reply_created_at, TIME_TO_SEC(ticket_reply_time_worked) AS seconds,
        ticket_reply_by, user_name, user_avatar,
        ticket_id, ticket_prefix, ticket_number, ticket_subject, ticket_billable, ticket_client_id, ticket_resolved_at, ticket_closed_at, ticket_invoice_id,
        client_name, client_abbreviation, client_rate
    FROM ticket_replies
    JOIN tickets ON ticket_reply_ticket_id = ticket_id
    JOIN clients ON ticket_client_id = client_id
    LEFT JOIN users ON ticket_reply_by = user_id
    WHERE ticket_reply_archived_at IS NULL AND ticket_reply_time_worked IS NOT NULL AND TIME_TO_SEC(ticket_reply_time_worked) > 0
    AND " . reportRangeSql('ticket_reply_created_at', $period) . "
    " . reportClientSql('ticket_client_id', $client_id) . "
    " . ($agent_id ? "AND ticket_reply_by = $agent_id" : '') . "
    " . ($billable_only ? "AND ticket_billable = 1" : '') . "
    ORDER BY client_name ASC, ticket_number ASC, ticket_reply_created_at ASC");

// Client > ticket > replies, with rounded totals at each level
$clients = [];
$replies = [];
while ($row = mysqli_fetch_assoc($sql)) {
    $seconds = intval($row['seconds']);
    $billed = intval(ceil($seconds / $increment) * $increment);
    $rate = floatval($row['client_rate']) > 0 ? floatval($row['client_rate']) : $default_rate;
    $row['seconds'] = $seconds;
    $row['billed'] = $billed;
    $row['rate'] = $rate;
    // Only time on a billable ticket is worth anything on an invoice
    $row['to_bill'] = intval($row['ticket_billable']) ? $billed : 0;
    $row['value'] = $row['to_bill'] / 3600 * $rate;
    $row['text'] = trim(preg_replace("/\n{3,}/", "\n\n", str_replace(["\r\n", "\r"], "\n", html_entity_decode(strip_tags(str_ireplace(['<br>', '<br/>', '<br />', '</p>', '</div>'], "\n", (string) $row['ticket_reply'])), ENT_QUOTES, 'UTF-8'))));
    $replies[] = $row;

    $c = intval($row['ticket_client_id']);
    $t = intval($row['ticket_id']);
    $clients[$c] = $clients[$c] ?? ['name' => $row['client_name'], 'abbr' => $row['client_abbreviation'], 'rate' => $rate, 'default_rate' => floatval($row['client_rate']) <= 0,
        'tickets' => [], 'seconds' => 0, 'billed' => 0, 'to_bill' => 0, 'value' => 0.0];
    $clients[$c]['tickets'][$t] = $clients[$c]['tickets'][$t] ?? ['row' => $row, 'replies' => [], 'seconds' => 0, 'billed' => 0, 'to_bill' => 0, 'value' => 0.0];
    $clients[$c]['tickets'][$t]['replies'][] = $row;
    foreach ([&$clients[$c], &$clients[$c]['tickets'][$t]] as &$level) {
        $level['seconds'] += $seconds;
        $level['billed'] += $billed;
        $level['to_bill'] += $row['to_bill'];
        $level['value'] += $row['value'];
    }
    unset($level);
}

$total_seconds = array_sum(array_column($replies, 'seconds'));
$total_billed = array_sum(array_column($replies, 'billed'));
$total_to_bill = array_sum(array_column($replies, 'to_bill'));
$total_value = array_sum(array_column($replies, 'value'));
$ticket_count = array_sum(array_map(function ($c) { return count($c['tickets']); }, $clients));

if (reportExporting()) {
    $columns = [
        'client_name' => ['label' => 'Client'],
        'ticket' => ['label' => 'Ticket', 'csv' => function ($row) { return ticketReference($row); }],
        'ticket_subject' => ['label' => 'Subject'],
        'date' => ['label' => 'Date', 'csv' => function ($row) { return date('Y-m-d H:i', strtotime($row['ticket_reply_created_at'])); }],
        'user_name' => ['label' => 'Agent'],
        'ticket_reply_type' => ['label' => 'Reply'],
        'billable' => ['label' => 'Billable ticket', 'csv' => function ($row) { return intval($row['ticket_billable']) ? 'Yes' : 'No'; }],
        'seconds' => ['label' => 'Hours', 'type' => 'hours'],
        'billed' => ['label' => "Hours rounded up to {$increment_label}", 'type' => 'hours'],
        'to_bill' => ['label' => 'Hours to bill', 'type' => 'hours'],
        'rate' => ['label' => 'Rate', 'type' => 'money'],
        'value' => ['label' => 'Value', 'type' => 'money'],
        'text' => ['label' => 'Work done'],
    ];
    reportCsv('Time Detail ' . $period['start'] . ' to ' . $period['end'], $columns, $replies, ['client_name' => 'Total', 'seconds' => $total_seconds, 'billed' => $total_billed, 'to_bill' => $total_to_bill, 'value' => $total_value]);
}

require_once "includes/inc_report_layout.php";

$controls = reportSelectControl('billing_increment', $increment_key, ['0.1' => 'Round up to 6 minutes', '0.25' => 'Round up to 15 minutes', '0.5' => 'Round up to 30 minutes'], 'fa-stopwatch', '', 'col-sm-6 col-lg-auto')
    . reportSwitchControl('billable_only', $billable_only, 'Billable tickets only');

$hours = function ($seconds) {
    return number_format($seconds / 3600, 2);
};

?>

<div class="itflow-report">

    <?php reportHeader('client_ticket_time_detail', [
        'period' => $period,
        'client' => $client_id,
        'agent' => $agent_id,
        'controls' => $controls,
    ]); ?>

    <?= reportStats([
        ['label' => 'Time logged', 'value' => reportHours($total_seconds, 2) . ' h', 'sub' => reportCount(count($replies)) . ' <span class="text-body-tertiary">replies on</span> ' . reportCount($ticket_count) . ' <span class="text-body-tertiary">tickets</span>'],
        ['label' => 'To bill', 'value' => reportHours($total_to_bill, 2) . ' h', 'sub' => '<span class="text-body-tertiary">On billable tickets, each reply rounded up to ' . escapeHtml($increment_label) . '</span>'],
        ['label' => 'Value', 'value' => reportMoney($total_value), 'sub' => '<span class="text-body-tertiary">The hours to bill at each client\'s rate</span>'],
        ['label' => 'Clients', 'value' => reportCount(count($clients)), 'sub' => '<span class="text-body-tertiary">With time in the period</span>'],
    ]) ?>

    <?php if (!$replies) { ?>
        <div class="card"><div class="card-body"><?= reportEmpty('No time was logged on ticket replies in this period.', 'fa-stopwatch', 'secondary') ?></div></div>
    <?php } ?>

    <?php foreach ($clients as $cid => $client) { ?>
        <div class="card itflow-report-section itflow-report-long">
            <div class="card-header d-flex flex-wrap align-items-center gap-2">
                <h3 class="card-title mb-0 me-auto"><?= reportClientCell($cid, $client['name'], $client['abbr']) ?></h3>
                <span class="small text-body-secondary">
                    <?= count($client['tickets']) ?> ticket<?= count($client['tickets']) === 1 ? '' : 's' ?> ·
                    <?= $hours($client['seconds']) ?> h logged ·
                    <span class="text-body fw-semibold"><?= $hours($client['to_bill']) ?> h to bill</span>
                    <?php if ($client['rate'] > 0) { ?>· <?= reportMoney($client['rate']) ?>/h<?= $client['default_rate'] ? ' (default)' : '' ?> · <span class="text-body fw-semibold"><?= reportMoney($client['value']) ?></span><?php } ?>
                </span>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0 itflow-report-table">
                    <thead class="table-light">
                    <tr>
                        <th>Ticket and work done</th>
                        <th class="text-nowrap">Agent</th>
                        <th class="text-end text-nowrap">Logged</th>
                        <th class="text-end text-nowrap" title="Rounded up to <?= escapeHtml($increment_label) ?>">Rounded</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($client['tickets'] as $ticket) {
                        $row = $ticket['row'];
                        $state = !empty($row['ticket_closed_at']) ? 'Closed' : (!empty($row['ticket_resolved_at']) ? 'Resolved' : 'Open'); ?>
                        <tr class="itflow-report-group-row">
                            <td colspan="2">
                                <?= reportTicketLink($row) ?>
                                <span class="small fw-normal text-body-secondary ms-2"><?= $state ?><?= intval($row['ticket_billable']) ? '' : ' · not billable' ?><?= intval($row['ticket_invoice_id']) ? ' · invoiced' : '' ?></span>
                            </td>
                            <td class="text-end"><?= $hours($ticket['seconds']) ?></td>
                            <td class="text-end"><?= $hours($ticket['billed']) ?></td>
                        </tr>
                        <?php foreach ($ticket['replies'] as $reply) { ?>
                            <tr>
                                <td class="itflow-report-sub">
                                    <div class="small text-body-secondary">
                                        <i class="far fa-clock me-1"></i><?= escapeHtml(date('D, M j, Y g:i A', strtotime($reply['ticket_reply_created_at']))) ?>
                                        <?php if ($reply['ticket_reply_type'] === 'Internal') { ?><span class="badge bg-secondary-subtle text-secondary-emphasis fw-normal ms-1">Internal note</span><?php } ?>
                                    </div>
                                    <?php if ($reply['text'] !== '') { ?>
                                        <div class="mt-1" style="white-space: pre-line"><?= escapeHtml($reply['text']) ?></div>
                                    <?php } ?>
                                </td>
                                <td class="text-nowrap align-top"><?= reportAgentCell($reply['ticket_reply_by'], $reply['user_name'] ?? '', $reply['user_avatar'] ?? '', 'Unknown') ?></td>
                                <td class="text-end align-top text-body-secondary"><?= $hours($reply['seconds']) ?></td>
                                <td class="text-end align-top"><?= $hours($reply['billed']) ?></td>
                            </tr>
                        <?php } ?>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php } ?>

    <?php if ($replies) { ?>
        <div class="card">
            <div class="card-body d-flex flex-wrap justify-content-between gap-2 fw-semibold">
                <span>All clients - <?= reportCount($ticket_count) ?> tickets</span>
                <span><?= $hours($total_seconds) ?> h logged · <?= $hours($total_to_bill) ?> h to bill · <?= reportMoney($total_value) ?></span>
            </div>
            <div class="card-footer small text-body-secondary">
                Only replies with time on them, by the date they were written. Each reply is rounded up to <?= escapeHtml($increment_label) ?> on its own, and the rounded figures on billable tickets are what is to bill.
            </div>
        </div>
    <?php } ?>

</div>

<?php require_once "../../includes/footer.php"; ?>
<script src="/js/reports.js"></script>
