<?php

/*
 * Canned Responses - for each response in the period: how many times it went
 * into a reply, on how many tickets and by how many agents, against the period
 * before, and when it was last used; by agent too - how often each reached for
 * one, how many different ones, and how many of them their own. Every current
 * response is listed, so the ones not used show. An admin sees every agent's
 * own; anyone else sees the shared ones and their own. One client's tickets,
 * or one agent's use, when picked. Use is counted from version 3.2.2 of the
 * database on.
 */

require_once "includes/inc_report.php";
reportEnforce('canned_responses');

$period = reportPeriod('last_90');
$client_id = reportClientId();
$agent_id = reportAgentId();
$admin = !empty($session_is_admin);

// The uses counted: on tickets this user may see (the client picked), by the agent picked
$visible = $admin ? '1 = 1' : cannedResponseUsableSql($session_user_id);
$uses = "canned_response_uses JOIN tickets ON ticket_id = canned_response_use_ticket_id
    JOIN canned_responses ON canned_responses.canned_response_id = canned_response_use_canned_response_id";
$filter = reportTicketSql($client_id) . " AND $visible" . ($agent_id ? " AND canned_response_use_user_id = $agent_id" : '');
$in_period = reportRangeSql('canned_response_use_created_at', $period);

// Every current response this user may see, and archived ones used in the period
$responses = [];
$sql = mysqli_query($mysqli, "SELECT canned_responses.canned_response_id, canned_response_name, canned_response_user_id, canned_response_archived_at, user_name
    FROM canned_responses LEFT JOIN users ON user_id = canned_response_user_id AND canned_response_user_id > 0
    WHERE $visible AND (canned_response_archived_at IS NULL OR canned_responses.canned_response_id IN (SELECT canned_response_use_canned_response_id FROM $uses WHERE $filter AND $in_period))");
while ($row = mysqli_fetch_assoc($sql)) {
    $owner = intval($row['canned_response_user_id']);
    $responses[intval($row['canned_response_id'])] = ['id' => intval($row['canned_response_id']), 'response' => $row['canned_response_name'], 'owner' => $owner,
        'whose' => $owner ? ($row['user_name'] !== null ? $row['user_name'] : 'A removed agent') : 'Shared', 'archived' => !empty($row['canned_response_archived_at']),
        'offered' => 'Every ticket', 'used' => 0, 'tickets' => 0, 'agents' => 0, 'prev' => 0, 'last' => null];
}
foreach (cannedResponseCategoriesFor(array_keys($responses)) as $id => $categories) {
    $responses[$id]['offered'] = implode(', ', array_column($categories, 'category_name'));
}

// Used in the period, on how many tickets, by how many agents
$sql = mysqli_query($mysqli, "SELECT canned_response_use_canned_response_id AS id, COUNT(*) AS n, COUNT(DISTINCT canned_response_use_ticket_id) AS t, COUNT(DISTINCT canned_response_use_user_id) AS a
    FROM $uses WHERE $filter AND $in_period GROUP BY canned_response_use_canned_response_id");
while ($row = mysqli_fetch_assoc($sql)) {
    if (isset($responses[intval($row['id'])])) {
        $responses[intval($row['id'])]['used'] = intval($row['n']);
        $responses[intval($row['id'])]['tickets'] = intval($row['t']);
        $responses[intval($row['id'])]['agents'] = intval($row['a']);
    }
}
// The period before, and when each was last used
$sql = mysqli_query($mysqli, "SELECT canned_response_use_canned_response_id AS id, COUNT(*) AS n FROM $uses WHERE $filter AND " . reportRangeSql('canned_response_use_created_at', $period, true) . " GROUP BY canned_response_use_canned_response_id");
while ($row = mysqli_fetch_assoc($sql)) {
    if (isset($responses[intval($row['id'])])) {
        $responses[intval($row['id'])]['prev'] = intval($row['n']);
    }
}
$sql = mysqli_query($mysqli, "SELECT canned_response_use_canned_response_id AS id, MAX(canned_response_use_created_at) AS last FROM $uses WHERE $filter GROUP BY canned_response_use_canned_response_id");
while ($row = mysqli_fetch_assoc($sql)) {
    if (isset($responses[intval($row['id'])])) {
        $responses[intval($row['id'])]['last'] = $row['last'];
    }
}
foreach ($responses as &$row) {
    $row['change'] = reportChange($row['used'], $row['prev']);
}
unset($row);
uasort($responses, function ($a, $b) {
    return $b['used'] <=> $a['used'] ?: strcasecmp($a['response'], $b['response']);
});
$responses = array_values($responses);

// By agent: how often each used one, how many different ones, and their own
$agents = [];
$sql = mysqli_query($mysqli, "SELECT canned_response_use_user_id AS id, user_name, user_avatar, COUNT(*) AS n, COUNT(DISTINCT canned_response_use_canned_response_id) AS r,
        SUM(canned_response_user_id = canned_response_use_user_id) AS own
    FROM $uses LEFT JOIN users ON user_id = canned_response_use_user_id
    WHERE $filter AND $in_period GROUP BY canned_response_use_user_id ORDER BY n DESC, user_name ASC");
while ($row = mysqli_fetch_assoc($sql)) {
    $agents[intval($row['id'])] = ['id' => intval($row['id']), 'agent' => $row['user_name'] ?? 'A removed agent', 'avatar' => (string) $row['user_avatar'],
        'used' => intval($row['n']), 'responses' => intval($row['r']), 'own' => intval($row['own']), 'top' => ''];
}
if ($agents) {
    // Each agent's most used
    $sql = mysqli_query($mysqli, "SELECT canned_response_use_user_id AS id, canned_response_name AS name, COUNT(*) AS n FROM $uses
        WHERE $filter AND $in_period GROUP BY canned_response_use_user_id, canned_response_use_canned_response_id ORDER BY n DESC, canned_response_name ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        if (isset($agents[intval($row['id'])]) && $agents[intval($row['id'])]['top'] === '') {
            $agents[intval($row['id'])]['top'] = $row['name'];
        }
    }
}
$agents = array_values($agents);

$columns = [
    'response' => ['label' => 'Response', 'html' => function ($row) use ($admin, $session_user_id) {
        $name = escapeHtml($row['response']);
        if ($admin) {
            $link = '/admin/canned_responses.php?' . http_build_query(array_filter(['q' => $row['response'], 'show' => $row['archived'] ? 'archived' : ($row['owner'] ? 'personal' : '')]));
        } elseif ($row['owner'] === intval($session_user_id)) {
            $link = '/agent/user/user_canned_responses.php?' . http_build_query(['q' => $row['response']]);
        } else {
            $link = '';
        }
        return ($link !== '' ? '<a href="' . escapeHtml($link) . '">' . $name . '</a>' : $name)
            . ($row['archived'] ? ' <span class="badge text-bg-light border fw-normal">Archived</span>' : '');
    }],
    'whose' => ['label' => 'Whose', 'html' => function ($row) {
        return $row['owner'] ? '<span class="small"><i class="fas fa-user text-body-tertiary me-1"></i>' . escapeHtml($row['whose']) . '</span>' : '<span class="small text-body-secondary">Shared</span>';
    }],
    'offered' => ['label' => 'Offered on', 'html' => function ($row) {
        return '<span class="small">' . escapeHtml($row['offered']) . '</span>';
    }],
    'used' => ['label' => 'Used', 'type' => 'count', 'class' => 'fw-semibold'],
    'change' => ['label' => 'Change', 'type' => 'percent', 'csv' => false, 'html' => function ($row) {
        return $row['change'] === null ? '<span class="itflow-report-zero">–</span>' : '<span class="small">' . reportChangeHtml($row['used'], $row['prev'], 'up') . '</span>';
    }],
    'tickets' => ['label' => 'Tickets', 'type' => 'count'],
    'agents' => ['label' => 'Agents', 'type' => 'count'],
    'last' => ['label' => 'Last used', 'type' => 'date', 'html' => function ($row) {
        return $row['last'] ? '<span class="small" title="' . escapeHtml($row['last']) . '">' . timeAgo($row['last']) . '</span>' : '<span class="itflow-report-zero">Never</span>';
    }],
];
$agent_columns = [
    'agent' => ['label' => 'Agent', 'html' => function ($row) {
        return reportAgentCell($row['id'], $row['agent'], $row['avatar'], 'A removed agent');
    }],
    'used' => ['label' => 'Used', 'type' => 'count', 'class' => 'fw-semibold'],
    'responses' => ['label' => 'Different ones', 'type' => 'count'],
    'own' => ['label' => 'Their own', 'type' => 'count', 'title' => 'Times one of their own went in'],
    'top' => ['label' => 'Most used', 'html' => function ($row) {
        return '<span class="small">' . escapeHtml($row['top']) . '</span>';
    }],
];

// Across them all - tickets and agents counted once each
$overall = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) AS n, COUNT(DISTINCT canned_response_use_ticket_id) AS t, COUNT(DISTINCT canned_response_use_user_id) AS a FROM $uses WHERE $filter AND $in_period"));
$previous = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM $uses WHERE $filter AND " . reportRangeSql('canned_response_use_created_at', $period, true)))[0]);
$totals = ['response' => 'Total', 'used' => intval($overall['n']), 'tickets' => intval($overall['t']), 'agents' => intval($overall['a'])];
$agent_totals = ['agent' => 'Total', 'used' => intval($overall['n']), 'own' => array_sum(array_column($agents, 'own'))];

if (reportExporting('agents')) {
    reportCsv('Canned Responses by Agent ' . $period['start'] . ' to ' . $period['end'], $agent_columns, $agents, $agent_totals);
}
if (reportExporting()) {
    reportCsv('Canned Responses ' . $period['start'] . ' to ' . $period['end'], $columns, $responses, $totals);
}

require_once "includes/inc_report_layout.php";

// The tickets agents replied to in the period, for the share
$replied = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(DISTINCT ticket_reply_ticket_id) FROM ticket_replies JOIN tickets ON ticket_id = ticket_reply_ticket_id
    WHERE " . reportTicketSql($client_id) . " AND ticket_reply_type IN ('Public', 'Internal') AND ticket_reply_archived_at IS NULL"
    . ($agent_id ? " AND ticket_reply_by = $agent_id" : '') . " AND " . reportRangeSql('ticket_reply_created_at', $period)))[0]);
$current = array_filter($responses, function ($r) { return !$r['archived']; });
$used = count(array_filter($current, function ($r) { return $r['used'] > 0; }));
$unused = count($current) - $used;
$top = array_slice(array_filter($responses, function ($r) { return $r['used'] > 0; }), 0, 12);

?>

<div class="itflow-report">

    <?php reportHeader('canned_responses', [
        'period' => $period,
        'years' => reportPastYears("SELECT DISTINCT YEAR(canned_response_use_created_at) AS y FROM canned_response_uses"),
        'client' => $client_id,
        'agent' => $agent_id,
        'export' => ['' => 'By response', 'agents' => 'By agent'],
        'print_meta' => $client_id ? reportClientName($client_id) : '',
    ]); ?>

    <?= reportStats([
        ['label' => 'Times used', 'value' => reportCount($totals['used']), 'sub' => $previous || $totals['used'] ? reportChangeHtml($totals['used'], $previous, 'up', 'the period before') : ''],
        ['label' => 'Tickets they went into', 'value' => reportCount($totals['tickets']), 'sub' => $replied ? reportPercent(reportShare(min($totals['tickets'], $replied), $replied)) . ' <span class="text-body-tertiary">of the tickets agents replied to</span>' : ''],
        ['label' => 'Responses used', 'value' => $used . ' of ' . count($current), 'sub' => $unused ? $unused . ' <span class="text-body-tertiary">not used in this period</span>' : '<span class="text-body-tertiary">Every one</span>'],
        ['label' => 'Most used', 'value' => $top ? $top[0]['response'] : '-', 'sub' => $top ? $top[0]['used'] . ' <span class="text-body-tertiary">time' . ($top[0]['used'] === 1 ? '' : 's') . ' · ' . reportPercent(reportShare($top[0]['used'], $totals['used'])) . ' of them</span>' : ''],
    ]) ?>

    <?php if ($top) { ?>
        <?= reportSectionOpen('Most used', escapeHtml($period['range'])) ?>
            <div class="card-body">
                <?= reportChart(['type' => 'bar', 'horizontal' => true, 'labels' => array_column($top, 'response'),
                    'series' => [['label' => 'Used', 'tone' => 'accent', 'data' => array_column($top, 'used')]], 'format' => 'count'], '', 'Times each was used') ?>
            </div>
        </div>
    <?php } ?>

    <?= reportSectionOpen('By response', 'Most used first, then the ones not used', 'itflow-report-long') ?>
        <?= reportTable($columns, $responses, ['empty' => 'There are no canned responses yet.', 'totals' => $totals]) ?>
        <div class="card-footer small text-body-secondary">
            Used counts each time a response went into a reply in the period - two in one reply count once each. Tickets and agents count each once, and the totals count them once across every response.
            <?= $admin ? "Agents' own are listed with whose they are." : 'The shared ones are listed, and your own.' ?> Use is counted from the update that brought canned responses' record of use in.
        </div>
    </div>

    <?= reportSectionOpen('By agent', 'Who reached for them') ?>
        <?= reportTable($agent_columns, $agents, ['empty' => 'Nobody used a canned response in this period.', 'totals' => $agent_totals]) ?>
    </div>

</div>

<?php require_once "../../includes/footer.php"; ?>
<script src="/js/reports.js"></script>
