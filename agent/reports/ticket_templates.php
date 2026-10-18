<?php

/*
 * Ticket Templates - for each template in the period: tickets raised from it
 * and how (by agents, from the client portal's request forms, by recurring
 * tickets or project templates), resolved, still open now, how long they
 * typically take to resolve and the time logged on them; against the period
 * before. Every current template is listed, so the ones not used show too.
 * One client's or one agent's tickets only when picked. Tickets remember
 * their template from version 3.1.9 of the database on.
 */

require_once "includes/inc_report.php";
reportEnforce('ticket_templates');

$period = reportPeriod('last_90');
$client_id = reportClientId();
$agent_id = reportAgentId();
$filter = reportTicketSql($client_id, $agent_id) . " AND tickets.ticket_template_id > 0";
$opened_in = reportRangeSql('ticket_created_at', $period);

// Every current template, and archived ones with tickets in the period
$templates = [];
$sql = mysqli_query($mysqli, "SELECT ticket_template_id, ticket_template_name, ticket_template_archived_at, ticket_template_portal FROM ticket_templates
    WHERE ticket_template_archived_at IS NULL OR ticket_template_id IN (SELECT tickets.ticket_template_id FROM tickets WHERE $filter AND $opened_in)");
while ($row = mysqli_fetch_assoc($sql)) {
    $templates[intval($row['ticket_template_id'])] = ['id' => intval($row['ticket_template_id']), 'template' => $row['ticket_template_name'], 'archived' => !empty($row['ticket_template_archived_at']),
        'portal_form' => intval($row['ticket_template_portal']) === 1, 'raised' => 0, 'agent' => 0, 'portal' => 0, 'recurring' => 0, 'project' => 0,
        'resolved' => 0, 'open_now' => 0, 'seconds' => 0, 'resolve' => null, 'prev' => 0, 'last' => null];
}

// Raised in the period, by how
$sql = mysqli_query($mysqli, "SELECT ticket_template_id AS id, ticket_source AS source, COUNT(ticket_id) AS n FROM tickets WHERE $filter AND $opened_in GROUP BY ticket_template_id, ticket_source");
while ($row = mysqli_fetch_assoc($sql)) {
    $id = intval($row['id']);
    if (!isset($templates[$id])) {
        continue;
    }
    $how = ['Portal' => 'portal', 'Recurring' => 'recurring', 'Project' => 'project'][$row['source']] ?? 'agent';
    $templates[$id][$how] += intval($row['n']);
    $templates[$id]['raised'] += intval($row['n']);
}
$counts = function ($sql_where, $key) use ($mysqli, $filter, &$templates) {
    $sql = mysqli_query($mysqli, "SELECT ticket_template_id AS id, COUNT(ticket_id) AS n FROM tickets WHERE $filter AND $sql_where GROUP BY ticket_template_id");
    while ($row = mysqli_fetch_assoc($sql)) {
        if (isset($templates[intval($row['id'])])) {
            $templates[intval($row['id'])][$key] = intval($row['n']);
        }
    }
};
$counts(reportRangeSql('ticket_resolved_at', $period), 'resolved');
$counts(TICKET_OPEN_SQL, 'open_now');
$counts(reportRangeSql('ticket_created_at', $period, true), 'prev');

// Time logged on them in the period, and when each was last used
$sql = mysqli_query($mysqli, "SELECT tickets.ticket_template_id AS id, SUM(TIME_TO_SEC(ticket_reply_time_worked)) AS s
    FROM ticket_replies JOIN tickets ON ticket_reply_ticket_id = ticket_id
    WHERE $filter AND ticket_reply_archived_at IS NULL AND " . reportRangeSql('ticket_reply_created_at', $period) . " GROUP BY tickets.ticket_template_id");
while ($row = mysqli_fetch_assoc($sql)) {
    if (isset($templates[intval($row['id'])])) {
        $templates[intval($row['id'])]['seconds'] = intval($row['s']);
    }
}
$sql = mysqli_query($mysqli, "SELECT ticket_template_id AS id, MAX(ticket_created_at) AS last FROM tickets WHERE $filter GROUP BY ticket_template_id");
while ($row = mysqli_fetch_assoc($sql)) {
    if (isset($templates[intval($row['id'])])) {
        $templates[intval($row['id'])]['last'] = $row['last'];
    }
}

// The typical (middle) time to resolve
$times = [];
$sql = mysqli_query($mysqli, "SELECT ticket_template_id AS id, TIMESTAMPDIFF(SECOND, ticket_created_at, ticket_resolved_at) AS s FROM tickets
    WHERE $filter AND " . reportRangeSql('ticket_resolved_at', $period));
while ($row = mysqli_fetch_assoc($sql)) {
    $times[intval($row['id'])][] = floatval($row['s']);
}
foreach ($templates as $id => &$row) {
    $row['resolve'] = reportMedian($times[$id] ?? []);
    $row['change'] = reportChange($row['raised'], $row['prev']);
}
unset($row);
uasort($templates, function ($a, $b) {
    return $b['raised'] <=> $a['raised'] ?: $b['open_now'] <=> $a['open_now'] ?: strcasecmp($a['template'], $b['template']);
});
$templates = array_values($templates);

$admin = !empty($session_is_admin);
$columns = [
    'template' => ['label' => 'Template', 'html' => function ($row) use ($admin) {
        $name = escapeHtml($row['template']);
        return ($admin ? '<a href="/admin/ticket_template.php?ticket_template_id=' . $row['id'] . '">' . $name . '</a>' : $name)
            . ($row['archived'] ? ' <span class="badge text-bg-light border fw-normal">Archived</span>' : '')
            . ($row['portal_form'] ? ' <i class="fas fa-door-open text-body-tertiary ms-1" title="Offered in the client portal"></i>' : '');
    }],
    'raised' => ['label' => 'Raised', 'type' => 'count', 'class' => 'fw-semibold'],
    'change' => ['label' => 'Change', 'type' => 'percent', 'csv' => false, 'html' => function ($row) {
        return $row['change'] === null ? '<span class="itflow-report-zero">–</span>' : '<span class="small">' . reportChangeHtml($row['raised'], $row['prev'], 'up') . '</span>';
    }],
    'agent' => ['label' => 'By agents', 'type' => 'count'],
    'portal' => ['label' => 'From the portal', 'type' => 'count'],
    'recurring' => ['label' => 'Recurring', 'type' => 'count'],
    'project' => ['label' => 'Projects', 'type' => 'count'],
    'resolved' => ['label' => 'Resolved', 'type' => 'count'],
    'open_now' => ['label' => 'Open now', 'type' => 'count'],
    'resolve' => ['label' => 'To resolve', 'type' => 'duration', 'title' => 'Typical (middle) time to resolve'],
    'seconds' => ['label' => 'Time logged', 'type' => 'hours'],
    'last' => ['label' => 'Last used', 'type' => 'date', 'html' => function ($row) {
        return $row['last'] ? '<span class="small" title="' . escapeHtml($row['last']) . '">' . timeAgo($row['last']) . '</span>' : '<span class="itflow-report-zero">Never</span>';
    }],
];

$totals = ['template' => 'Total'];
foreach (['raised', 'agent', 'portal', 'recurring', 'project', 'resolved', 'open_now', 'seconds'] as $key) {
    $totals[$key] = array_sum(array_column($templates, $key));
}
$totals['resolve'] = reportMedian($times ? array_merge(...array_values($times)) : []);

if (reportExporting()) {
    reportCsv('Ticket Templates ' . $period['start'] . ' to ' . $period['end'], $columns, $templates, $totals);
}

require_once "includes/inc_report_layout.php";

// All the tickets raised in the period - from templates or not - for the share
$all_raised = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(ticket_id) FROM tickets WHERE " . reportTicketSql($client_id, $agent_id) . " AND $opened_in"))[0]);
$current = array_filter($templates, function ($t) { return !$t['archived']; });
$used = count(array_filter($current, function ($t) { return $t['raised'] > 0; }));
$unused = count($current) - $used;
$top = array_slice(array_filter($templates, function ($t) { return $t['raised'] > 0; }), 0, 12);

?>

<div class="itflow-report">

    <?php reportHeader('ticket_templates', [
        'period' => $period,
        'years' => reportPastYears("SELECT DISTINCT YEAR(ticket_created_at) AS y FROM tickets WHERE ticket_template_id > 0"),
        'client' => $client_id,
        'agent' => $agent_id,
        'print_meta' => $client_id ? reportClientName($client_id) : '',
    ]); ?>

    <?= reportStats([
        ['label' => 'Raised from templates', 'value' => reportCount($totals['raised']), 'sub' => $all_raised ? reportPercent(reportShare($totals['raised'], $all_raised)) . ' <span class="text-body-tertiary">of all tickets raised</span>' : ''],
        ['label' => 'From the client portal', 'value' => reportCount($totals['portal']), 'sub' => '<span class="text-body-tertiary">Asked for on its request forms</span>'],
        ['label' => 'Templates used', 'value' => $used . ' of ' . count($current), 'sub' => $unused ? $unused . ' <span class="text-body-tertiary">not used in this period</span>' : '<span class="text-body-tertiary">Every one</span>'],
        ['label' => 'Most used', 'value' => $top ? $top[0]['template'] : '-', 'sub' => $top ? $top[0]['raised'] . ' <span class="text-body-tertiary">tickets · ' . reportPercent(reportShare($top[0]['raised'], $totals['raised'])) . ' of them</span>' : ''],
    ]) ?>

    <?php if ($top) { ?>
        <?= reportSectionOpen('Most used', 'How they were raised') ?>
            <div class="card-body">
                <?php
                $series = [];
                foreach (['agent' => ['By agents', 'accent'], 'portal' => ['From the portal', 'success'], 'recurring' => ['Recurring', 'info'], 'project' => ['Projects', 'purple']] as $key => [$label, $tone]) {
                    $series[] = ['label' => $label, 'tone' => $tone, 'stack' => 'how', 'data' => array_column($top, $key)];
                }
                echo reportChart(['type' => 'bar', 'horizontal' => true, 'stacked' => true, 'labels' => array_column($top, 'template'), 'series' => $series, 'format' => 'count'], '', 'Tickets raised per template, by how');
                ?>
            </div>
        </div>
    <?php } ?>

    <?= reportSectionOpen('By template', 'Most used first, then the ones not used', 'itflow-report-long') ?>
        <?= reportTable($columns, $templates, ['empty' => 'There are no ticket templates yet.', 'totals' => $totals]) ?>
        <div class="card-footer small text-body-secondary">
            Raised counts tickets raised in the period from each template; resolved counts those resolved in it, whenever raised. To resolve is the middle ticket's time, so a few long ones do not skew it.
            Time logged is the time on their replies in the period. Tickets remember their template from the update that brought ticket templates' setup in - older ones are not counted.
        </div>
    </div>

</div>

<?php require_once "../../includes/footer.php"; ?>
<script src="/js/reports.js"></script>
