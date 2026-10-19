<?php

/*
 * Audit Activity - what was done in ITFlow in the period, from the audit log:
 * changes made, records deleted, passwords looked at and data exported, and
 * for an admin the sign-ins and the ones that failed - against the period
 * before, over time, and by the hour of the week, so work at odd hours shows.
 * Then by person, by client and by kind of record, each linking to those
 * entries in the audit log, and for an admin the security events. Anyone
 * else sees only the kinds of entry their role covers, for the clients they
 * can open (auditLogRoleSql(), functions/audit_log.php) - as on a client's
 * Audit Log. One client, or one agent, when picked.
 */

require_once "includes/inc_report.php";
reportEnforce('audit_activity');

$period = reportPeriod('last_30');
$client_id = reportClientId();
$agent_id = reportAgentId();
$admin = !empty($session_is_admin);

// The entries counted: those this user may see, for the client and agent picked
$scope = auditLogRoleSql() . reportClientSql('log_client_id', $client_id) . ($agent_id ? " AND log_user_id = $agent_id" : '');
$in_period = reportRangeSql('log_created_at', $period);
$views = auditLogViews();
$signins = "log_type IN ('" . implode("', '", SIGN_IN_TYPES) . "')";
$parts = [
    'changes' => $views['changes']['where'],
    'deletions' => $views['deletions']['where'],
    'data' => $views['data']['where'],
    'passwords' => "log_type = 'Credential' AND log_action IN ('View', 'View TOTP')",
    'exports' => "log_action IN ('Export', 'Download')",
    'signins' => "$signins AND log_action = 'Success'",
    'failed' => "$signins AND log_action IN ('" . implode("', '", SIGN_IN_FAILED) . "')",
];
$sums = function ($alias = '') use ($parts) {
    $out = [];
    foreach ($parts as $key => $where) {
        $out[] = "COALESCE(SUM($where), 0) AS {$alias}$key";
    }
    return implode(', ', $out);
};

// Across the period, and the one before
$overall = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) AS entries, COUNT(DISTINCT NULLIF(log_user_id, 0)) AS people, " . $sums() . "
    FROM logs WHERE $scope AND $in_period"));
$before = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) AS entries, " . $sums() . "
    FROM logs WHERE $scope AND " . reportRangeSql('log_created_at', $period, true)));
$overall = array_map('intval', $overall);
$before = array_map('intval', $before);

// Over time - changes, data out and, for an admin, sign-ins and failures
$buckets = reportBuckets($period);
$series = array_fill_keys(['changes', 'data', 'signins', 'failed'], array_fill_keys($buckets['keys'], 0));
$sql = mysqli_query($mysqli, "SELECT " . reportBucketSql('log_created_at', $buckets['unit']) . " AS b, " . $sums() . "
    FROM logs WHERE $scope AND $in_period GROUP BY b");
while ($row = mysqli_fetch_assoc($sql)) {
    foreach (array_keys($series) as $key) {
        if (isset($series[$key][$row['b']])) {
            $series[$key][$row['b']] = intval($row[$key]);
        }
    }
}

// When people were at it - the hour of the week, ITFlow itself left out
$heat = [];
$sql = mysqli_query($mysqli, "SELECT WEEKDAY(log_created_at) + 1 AS d, HOUR(log_created_at) AS h, COUNT(*) AS n
    FROM logs WHERE $scope AND $in_period AND log_user_id > 0 GROUP BY d, h");
while ($row = mysqli_fetch_assoc($sql)) {
    $heat[intval($row['d'])][intval($row['h'])] = intval($row['n']);
}
// Out of hours - outside the business days and hours set for SLAs (Admin > SLA), else 9 to 5 on weekdays
$sla = getSlaSettings();
$work_days = $sla['business_days'] ?: [1, 2, 3, 4, 5];
$work_start = preg_match('/^\d{2}:\d{2}(:\d{2})?$/', (string) $sla['business_hours_start']) ? substr($sla['business_hours_start'] . ':00', 0, 8) : '09:00:00';
$work_end = preg_match('/^\d{2}:\d{2}(:\d{2})?$/', (string) $sla['business_hours_end']) ? substr($sla['business_hours_end'] . ':00', 0, 8) : '17:00:00';
if ($work_end <= $work_start) {
    [$work_start, $work_end] = ['09:00:00', '17:00:00'];
}
$out_of_hours = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM logs WHERE $scope AND $in_period AND log_user_id > 0
    AND (WEEKDAY(log_created_at) + 1 NOT IN (" . implode(',', array_map('intval', $work_days)) . ") OR TIME(log_created_at) < '$work_start' OR TIME(log_created_at) >= '$work_end')"))[0]);
$by_people = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM logs WHERE $scope AND $in_period AND log_user_id > 0"))[0]);

// The audit log, for these entries - an admin's, or the client's when one is picked
$period_query = ['canned_date' => 'custom', 'dtf' => $period['start'], 'dtt' => $period['end']];
$log_url = function (array $filters) use ($admin, $client_id, $period_query) {
    if ($admin) {
        return '/admin/audit_logs.php?' . http_build_query(array_merge($filters, $client_id && !isset($filters['client']) ? ['client' => $client_id] : [], $period_query));
    }
    $client = intval($filters['client'] ?? $client_id);
    if (!$client) {
        return '';
    }
    unset($filters['client']);
    return '/agent/audit_log.php?' . http_build_query(array_merge(['client_id' => $client], $filters, $period_query));
};

// By person - ITFlow itself last
$people = [];
$sql = mysqli_query($mysqli, "SELECT log_user_id AS id, user_name, user_avatar, user_type, COUNT(*) AS entries, " . $sums() . ",
        COUNT(DISTINCT NULLIF(log_ip, '')) AS ips, COUNT(DISTINCT NULLIF(log_client_id, 0)) AS clients, MAX(log_created_at) AS last
    FROM logs LEFT JOIN users ON user_id = log_user_id AND log_user_id > 0
    WHERE $scope AND $in_period GROUP BY log_user_id");
// People since deleted are one row, -1, before ITFlow itself
$gone_ids = [];
while ($row = mysqli_fetch_assoc($sql)) {
    $id = intval($row['id']);
    if ($id > 0 && $row['user_name'] === null) {
        $gone_ids[$id] = true;
        $id = -1;
    }
    if (!isset($people[$id])) {
        $people[$id] = ['id' => $id, 'person' => $id > 0 ? $row['user_name'] : ($id === 0 ? 'ITFlow itself' : 'People since deleted'), 'avatar' => (string) $row['user_avatar'],
            'kind' => $id === 0 ? 'system' : ($id < 0 ? 'gone' : (intval($row['user_type']) === 2 ? 'portal' : 'agent')), 'entries' => 0, 'prev' => 0,
            'ips' => 0, 'clients' => 0, 'last' => null, 'count' => 0] + array_fill_keys(array_keys($parts), 0);
    }
    $people[$id]['count']++;
    foreach (array_merge(['entries', 'ips', 'clients'], array_keys($parts)) as $key) {
        $people[$id][$key] += intval($row[$key]);
    }
    $people[$id]['last'] = max((string) $people[$id]['last'], (string) $row['last']);
}
if (isset($people[-1])) {
    $people[-1]['person'] = $people[-1]['count'] . ' ' . ($people[-1]['count'] === 1 ? 'person' : 'people') . ' since deleted';
}
if ($people) {
    $sql = mysqli_query($mysqli, "SELECT log_user_id AS id, COUNT(*) AS n FROM logs WHERE $scope AND " . reportRangeSql('log_created_at', $period, true) . " GROUP BY log_user_id");
    while ($row = mysqli_fetch_assoc($sql)) {
        $id = isset($gone_ids[intval($row['id'])]) ? -1 : intval($row['id']);
        if (isset($people[$id])) {
            $people[$id]['prev'] += intval($row['n']);
        }
    }
}
foreach ($people as &$row) {
    $row['change'] = reportChange($row['entries'], $row['prev']);
}
unset($row);
uasort($people, function ($a, $b) {
    $order = ['agent' => 0, 'portal' => 0, 'gone' => 1, 'system' => 2];
    return $order[$a['kind']] <=> $order[$b['kind']] ?: $b['entries'] <=> $a['entries'] ?: strcasecmp($a['person'], $b['person']);
});
$people = array_values($people);

// By client
$clients = [];
$sql = mysqli_query($mysqli, "SELECT log_client_id AS id, client_name, client_abbreviation, COUNT(*) AS entries, " . $sums() . ",
        COUNT(DISTINCT NULLIF(log_user_id, 0)) AS people, MAX(log_created_at) AS last
    FROM logs LEFT JOIN clients ON client_id = log_client_id
    WHERE $scope AND $in_period AND log_client_id > 0 GROUP BY log_client_id ORDER BY entries DESC, client_name ASC");
// Clients since deleted are one row, last
$gone_clients = null;
while ($row = mysqli_fetch_assoc($sql)) {
    $client = ['id' => intval($row['id']), 'client' => (string) $row['client_name'], 'abbr' => (string) $row['client_abbreviation'], 'gone' => $row['client_name'] === null,
        'entries' => intval($row['entries']), 'changes' => intval($row['changes']), 'deletions' => intval($row['deletions']), 'data' => intval($row['data']),
        'people' => intval($row['people']), 'last' => $row['last'], 'count' => 1];
    if (!$client['gone']) {
        $clients[] = $client;
    } elseif ($gone_clients === null) {
        $gone_clients = $client;
    } else {
        foreach (['entries', 'changes', 'deletions', 'data', 'people', 'count'] as $key) {
            $gone_clients[$key] += $client[$key];
        }
        $gone_clients['last'] = max($gone_clients['last'], $client['last']);
    }
}
if ($gone_clients) {
    $gone_clients['client'] = $gone_clients['count'] . ' client' . ($gone_clients['count'] === 1 ? '' : 's') . ' since deleted';
    $clients[] = $gone_clients;
}

// By kind of record
$kinds = [];
$sql = mysqli_query($mysqli, "SELECT log_type AS type, COUNT(*) AS entries, SUM(log_action IN ('Create', 'Bulk Create', 'Import', 'Upload')) AS created,
        SUM(log_action IN ('Edit', 'Bulk Edit', 'Update', 'Modify', 'Rename', 'Move', 'Merge')) AS edited, " . $sums() . ",
        COUNT(DISTINCT NULLIF(log_entity_id, 0)) AS records, MAX(log_created_at) AS last
    FROM logs WHERE $scope AND $in_period GROUP BY log_type ORDER BY entries DESC, log_type ASC");
while ($row = mysqli_fetch_assoc($sql)) {
    $kinds[] = ['type' => (string) $row['type'], 'entries' => intval($row['entries']), 'created' => intval($row['created']), 'edited' => intval($row['edited']),
        'deletions' => intval($row['deletions']), 'data' => intval($row['data']), 'records' => intval($row['records']), 'last' => $row['last']];
}

// The security events - for an admin
$security = [];
if ($admin) {
    $sql = mysqli_query($mysqli, "SELECT log_type AS type, log_action AS action, COUNT(*) AS n, COUNT(DISTINCT NULLIF(log_ip, '')) AS ips, MAX(log_created_at) AS last
        FROM logs WHERE $scope AND $in_period AND {$views['security']['where']} GROUP BY log_type, log_action ORDER BY n DESC, log_type ASC, log_action ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $security[] = ['type' => (string) $row['type'], 'action' => (string) $row['action'], 'n' => intval($row['n']), 'ips' => intval($row['ips']), 'last' => $row['last']];
    }
}

$zero = function ($value, $tone = '') {
    return $value ? '<span class="' . ($tone !== '' ? "fw-semibold text-$tone-emphasis" : '') . '">' . reportCount($value) . '</span>' : '<span class="itflow-report-zero">0</span>';
};
$last_cell = function ($row) {
    return $row['last'] ? '<span class="small text-nowrap" title="' . escapeHtml($row['last']) . '">' . escapeHtml(timeAgo($row['last'])) . '</span>' : '';
};

$people_columns = [
    'person' => ['label' => 'Who', 'html' => function ($row) use ($log_url) {
        if ($row['kind'] === 'system') {
            $cell = '<span class="small text-nowrap"><i class="fas fa-fw fa-cog text-body-tertiary me-1"></i>ITFlow itself</span><span class="d-block small text-body-secondary text-nowrap">cron, the API, the email parser</span>';
        } elseif ($row['kind'] === 'gone') {
            return '<span class="small text-body-secondary"><i class="fas fa-fw fa-user-slash text-body-tertiary me-1"></i>' . escapeHtml($row['person']) . '</span>';
        } else {
            $cell = reportAgentCell($row['id'], $row['person'], $row['avatar'], 'Someone since deleted')
                . ($row['kind'] === 'portal' ? ' <span class="badge text-bg-light border fw-normal">Portal</span>' : '');
        }
        $url = $log_url(['user' => $row['id'] ?: 'system']);
        return $url !== '' ? '<a class="text-decoration-none" href="' . escapeHtml($url) . '" title="Their entries in the audit log">' . $cell . '</a>' : $cell;
    }, 'csv' => function ($row) {
        return $row['person'] . ($row['kind'] === 'portal' ? ' (portal)' : '');
    }],
    'entries' => ['label' => 'Entries', 'type' => 'count', 'class' => 'fw-semibold'],
    'change' => ['label' => 'Change', 'type' => 'percent', 'csv' => false, 'html' => function ($row) {
        return $row['change'] === null ? '<span class="itflow-report-zero">–</span>' : '<span class="small">' . reportChangeHtml($row['entries'], $row['prev'], 'up') . '</span>';
    }],
    'changes' => ['label' => 'Changes', 'type' => 'count', 'title' => 'Records made, changed, archived, restored and deleted'],
    'deletions' => ['label' => 'Deleted', 'type' => 'count', 'title' => 'Records deleted for good', 'html' => function ($row) use ($zero) {
        return $zero($row['deletions'], 'danger');
    }],
    'passwords' => ['label' => 'Passwords seen', 'type' => 'count', 'title' => 'Passwords and 2FA codes looked at', 'html' => function ($row) use ($zero) {
        return $zero($row['passwords'], 'warning');
    }],
    'exports' => ['label' => 'Exports', 'type' => 'count', 'title' => 'Exports and downloads', 'html' => function ($row) use ($zero) {
        return $zero($row['exports'], 'warning');
    }],
    'clients' => ['label' => 'Clients', 'type' => 'count', 'title' => 'Clients they did something for'],
];
if ($admin) {
    $people_columns['signins'] = ['label' => 'Sign-ins', 'type' => 'count'];
    $people_columns['failed'] = ['label' => 'Failed', 'type' => 'count', 'title' => 'Failed sign-ins against the account - not always them', 'html' => function ($row) use ($zero) {
        return $zero($row['failed'], 'danger');
    }];
    $people_columns['ips'] = ['label' => 'IP addresses', 'type' => 'count', 'title' => 'Places it was done from'];
}
$people_columns['last'] = ['label' => 'Last', 'type' => 'date', 'html' => $last_cell];

$client_columns = [
    'client' => ['label' => 'Client', 'html' => function ($row) use ($log_url) {
        if ($row['gone']) {
            return '<span class="small text-body-secondary"><i class="fas fa-fw fa-ghost text-body-tertiary me-1"></i>' . escapeHtml($row['client']) . '</span>';
        }
        $url = $log_url(['client' => $row['id']]);
        return reportClientCell($row['id'], $row['client'], $row['abbr'], 'client_overview.php')
            . ($url !== '' ? ' <a class="small ms-1" href="' . escapeHtml($url) . '" title="Its audit log for the period"><i class="fas fa-history"></i></a>' : '');
    }],
    'entries' => ['label' => 'Entries', 'type' => 'count', 'class' => 'fw-semibold'],
    'changes' => ['label' => 'Changes', 'type' => 'count'],
    'deletions' => ['label' => 'Deleted', 'type' => 'count', 'html' => function ($row) use ($zero) {
        return $zero($row['deletions'], 'danger');
    }],
    'data' => ['label' => 'Data out', 'type' => 'count', 'title' => 'Passwords looked at, exports, downloads and shared links', 'html' => function ($row) use ($zero) {
        return $zero($row['data'], 'warning');
    }],
    'people' => ['label' => 'People', 'type' => 'count', 'title' => 'Agents and portal contacts'],
    'last' => ['label' => 'Last', 'type' => 'date', 'html' => $last_cell],
];

$kind_columns = [
    'type' => ['label' => 'What', 'html' => function ($row) use ($log_url) {
        $url = $log_url(['type' => $row['type']]);
        $label = '<i class="fas fa-fw ' . auditLogTypeIcon($row['type']) . ' text-body-tertiary me-1"></i>' . escapeHtml($row['type']);
        return $url !== '' ? '<a class="text-body" href="' . escapeHtml($url) . '">' . $label . '</a>' : $label;
    }],
    'entries' => ['label' => 'Entries', 'type' => 'count', 'class' => 'fw-semibold'],
    'records' => ['label' => 'Records', 'type' => 'count', 'title' => 'Different records'],
    'created' => ['label' => 'Made', 'type' => 'count'],
    'edited' => ['label' => 'Changed', 'type' => 'count'],
    'deletions' => ['label' => 'Deleted', 'type' => 'count', 'html' => function ($row) use ($zero) {
        return $zero($row['deletions'], 'danger');
    }],
    'data' => ['label' => 'Data out', 'type' => 'count', 'html' => function ($row) use ($zero) {
        return $zero($row['data'], 'warning');
    }],
    'last' => ['label' => 'Last', 'type' => 'date', 'html' => $last_cell],
];

$security_columns = [
    'what' => ['label' => 'What', 'html' => function ($row) use ($log_url) {
        $url = $log_url(['view' => 'security', 'type' => $row['type'], 'action' => $row['action']]);
        return '<a class="text-body text-decoration-none" href="' . escapeHtml($url) . '"><i class="fas fa-fw ' . auditLogTypeIcon($row['type']) . ' text-body-tertiary me-1"></i>' . escapeHtml($row['type']) . '</a> ' . auditLogActionPill($row['action']);
    }, 'csv' => function ($row) {
        return $row['type'] . ' ' . $row['action'];
    }],
    'n' => ['label' => 'Times', 'type' => 'count', 'class' => 'fw-semibold'],
    'ips' => ['label' => 'IP addresses', 'type' => 'count'],
    'last' => ['label' => 'Last', 'type' => 'date', 'html' => $last_cell],
];

$sum = function (array $rows, $key) {
    return array_sum(array_column($rows, $key));
};
$people_totals = ['person' => 'Total', 'entries' => $overall['entries']] + array_intersect_key($overall, $parts);
$client_totals = ['client' => 'Total', 'entries' => $sum($clients, 'entries'), 'changes' => $sum($clients, 'changes'), 'deletions' => $sum($clients, 'deletions'), 'data' => $sum($clients, 'data')];
$kind_totals = ['type' => 'Total', 'entries' => $overall['entries'], 'created' => $sum($kinds, 'created'), 'edited' => $sum($kinds, 'edited'), 'deletions' => $overall['deletions'], 'data' => $overall['data']];

$name = $period['start'] . ' to ' . $period['end'];
if (reportExporting('clients')) {
    reportCsv("Audit Activity by Client $name", $client_columns, $clients, $client_totals);
}
if (reportExporting('kinds')) {
    reportCsv("Audit Activity by Kind $name", $kind_columns, $kinds, $kind_totals);
}
if ($admin && reportExporting('security')) {
    reportCsv("Audit Activity Security $name", $security_columns, $security, ['what' => 'Total', 'n' => $sum($security, 'n')]);
}
if (reportExporting()) {
    reportCsv("Audit Activity by Person $name", $people_columns, $people, $people_totals);
}

require_once "includes/inc_report_layout.php";

// Years with entries, back to the oldest kept
$past_years = [];
$oldest = mysqli_fetch_row(mysqli_query($mysqli, "SELECT YEAR(MIN(log_created_at)) FROM logs"))[0] ?? null;
for ($y = intval(date('Y')) - 2; $oldest && $y >= intval($oldest); $y--) {
    $past_years[] = $y;
}
$retention = appLogRetention();

$exports = ['' => 'By person', 'clients' => 'By client', 'kinds' => 'By kind'] + ($admin ? ['security' => 'Security events'] : []);
$stats = [
    ['label' => 'Changes', 'value' => reportCount($overall['changes']),
        'sub' => ($before['changes'] || $overall['changes'] ? reportChangeHtml($overall['changes'], $before['changes'], 'up', 'the period before') . ' · ' : '')
            . $overall['people'] . ' <span class="text-body-tertiary">' . ($overall['people'] === 1 ? 'person' : 'people') . '</span>'],
    ['label' => 'Deleted', 'value' => reportCount($overall['deletions']), 'tone' => $overall['deletions'] ? 'danger' : '',
        'sub' => $before['deletions'] || $overall['deletions'] ? reportChangeHtml($overall['deletions'], $before['deletions'], 'down', 'the period before') : '<span class="text-body-tertiary">Nothing deleted</span>'],
    ['label' => 'Data out', 'value' => reportCount($overall['data']), 'tone' => $overall['data'] ? 'warning' : '',
        'sub' => reportCount($overall['passwords']) . ' <span class="text-body-tertiary">password' . ($overall['passwords'] === 1 ? '' : 's') . ' seen</span> · '
            . reportCount($overall['exports']) . ' <span class="text-body-tertiary">export' . ($overall['exports'] === 1 ? '' : 's') . '</span>'],
];
if ($admin) {
    $stats[] = ['label' => 'Sign-ins', 'value' => reportCount($overall['signins']), 'tone' => $overall['failed'] > $overall['signins'] ? 'danger' : '',
        'sub' => $overall['failed'] ? '<span class="text-danger-emphasis">' . reportCount($overall['failed']) . ' failed</span>' : '<span class="text-body-tertiary">None failed</span>'];
} else {
    $stats[] = ['label' => 'Out of hours', 'value' => $by_people ? reportPercent(reportShare($out_of_hours, $by_people)) : '-',
        'sub' => reportCount($out_of_hours) . ' <span class="text-body-tertiary">of ' . reportCount($by_people) . ' entries</span>'];
}

$chart_series = [
    ['label' => 'Changes', 'tone' => 'accent', 'data' => array_values($series['changes']), 'stack' => 'a'],
    ['label' => 'Data out', 'tone' => 'warning', 'data' => array_values($series['data']), 'stack' => 'a'],
];
if ($admin) {
    $chart_series[] = ['label' => 'Sign-ins', 'tone' => 'info', 'data' => array_values($series['signins']), 'stack' => 'a'];
    $chart_series[] = ['label' => 'Failed sign-ins', 'tone' => 'danger', 'data' => array_values($series['failed']), 'stack' => 'a'];
}

?>

<div class="itflow-report">

    <?php reportHeader('audit_activity', [
        'period' => $period,
        'years' => $past_years,
        'client' => $client_id,
        'agent' => $agent_id,
        'export' => $exports,
        'print_meta' => implode(' · ', array_filter([$client_id ? reportClientName($client_id) : '', $agent_id ? getFieldById('users', $agent_id, 'user_name') : ''])),
        'note' => escapeHtml(reportFind('audit_activity')['description']) . ($admin ? '' : ' You see the kinds of entry your role covers.')
            . ($retention['audit'] ? ' The audit log keeps ' . escapeHtml(strtolower(appLogRetentionText($retention['audit']))) . ', so a period before that is empty.' : ''),
    ]); ?>

    <?= reportStats($stats) ?>

    <?php if (!$overall['entries']) { ?>
        <div class="card"><div class="card-body"><?= reportEmpty('Nothing was done in this period that you can see.', 'fa-history', 'secondary') ?></div></div>
    <?php } else { ?>

        <?= reportSectionOpen('Over time', escapeHtml($period['range'])) ?>
            <div class="card-body">
                <?= reportChart(['type' => 'bar', 'labels' => $buckets['labels'], 'series' => $chart_series, 'stacked' => true, 'format' => 'count'], '', 'Entries by ' . $buckets['unit']) ?>
            </div>
        </div>

        <?= reportSectionOpen('When', reportCount($out_of_hours) . ' of ' . reportCount($by_people) . ' entries by people out of hours - before ' . escapeHtml(date('g:ia', strtotime($work_start))) . ', after ' . escapeHtml(date('g:ia', strtotime($work_end))) . ' or on a day off') ?>
            <div class="card-body">
                <?= reportHeatmap($heat, 'entry') ?>
            </div>
        </div>

        <?= reportSectionOpen('By person', 'Most first, then ITFlow itself') ?>
            <?= reportTable($people_columns, $people, ['empty' => 'Nobody did anything in this period.', 'totals' => $people_totals]) ?>
            <div class="card-footer small text-body-secondary">
                Changes are records made, changed, archived, restored and deleted. Failed sign-ins are against the account, so they may be someone else trying it. Each person opens their entries in the audit log<?= $admin ? '' : ' when a client is picked' ?>.
            </div>
        </div>

        <?php if ($security) { ?>
            <?= reportSectionOpen('Security events', 'Failed and turned away sign-ins, alerts, access denied, and changes to users, roles, keys and settings') ?>
                <?= reportTable($security_columns, $security, ['empty' => '']) ?>
            </div>
        <?php } ?>

        <?= reportSectionOpen('By client', 'Most first', 'itflow-report-long') ?>
            <?= reportTable($client_columns, $clients, ['empty' => 'Nothing was done for a client in this period.', 'totals' => $client_totals]) ?>
        </div>

        <?= reportSectionOpen('By kind', 'What it was done to', 'itflow-report-long') ?>
            <?= reportTable($kind_columns, $kinds, ['empty' => 'Nothing in this period.', 'totals' => $kind_totals]) ?>
        </div>

    <?php } ?>

</div>

<?php require_once "../../includes/footer.php"; ?>
<script src="/js/reports.js"></script>
