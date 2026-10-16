<?php

/*
 * Client Review - one client's period on a page, made to print for a
 * quarterly business review (QBR): their tickets (how many, how fast, what
 * about, who raised them, which devices keep coming back), SLA and ratings,
 * projects, the hardware to plan for, renewals coming up, services that need
 * a look, documentation gaps and - for agents who see billing - what was
 * invoiced and paid. It closes the top with recommendations drawn from all
 * of that, ready to talk through.
 *
 * Each part shows only when that side of ITFlow is on (ticketing, IT
 * documentation, accounting) and the agent may see it.
 */

require_once "includes/inc_report.php";
reportEnforce('client_review');

$period = reportPeriod('last_quarter');
$client_id = reportClientId();
$ticketing = reportFeatureOn('ticketing');
$itdoc = reportFeatureOn('itdoc');
$billing = reportFeatureOn('accounting') && reportPermission('module_sales') >= 1;
$credentials_on = $itdoc && reportPermission('module_credential') >= 1;
const REVIEW_OLD_YEARS = 5;

$client = null;
if ($client_id) {
    $client = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT client_id, client_name, client_abbreviation, client_type, client_website, client_created_at FROM clients WHERE client_id = $client_id"));
    $primary = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT contact_name, contact_title, contact_email FROM contacts
        WHERE contact_client_id = $client_id AND contact_archived_at IS NULL ORDER BY contact_primary DESC, contact_important DESC, contact_name ASC LIMIT 1"));
}

/*
 * ---------- tickets ----------
 */
$t = null;
if ($client && $ticketing) {
    $filter = reportTicketSql($client_id);
    $opened_in = reportRangeSql('ticket_created_at', $period);
    $t = [
        'opened' => dashboardCount("SELECT COUNT(ticket_id) FROM tickets WHERE $filter AND $opened_in"),
        'opened_prev' => dashboardCount("SELECT COUNT(ticket_id) FROM tickets WHERE $filter AND " . reportRangeSql('ticket_created_at', $period, true)),
        'resolved' => dashboardCount("SELECT COUNT(ticket_id) FROM tickets WHERE $filter AND " . reportRangeSql('ticket_resolved_at', $period)),
        'open_now' => dashboardCount("SELECT COUNT(ticket_id) FROM tickets WHERE $filter AND " . TICKET_OPEN_SQL),
        'open_old' => dashboardCount("SELECT COUNT(ticket_id) FROM tickets WHERE $filter AND " . TICKET_OPEN_SQL . " AND ticket_created_at < NOW() - INTERVAL 30 DAY"),
        'response' => reportMedian(reportColumn("SELECT TIMESTAMPDIFF(SECOND, ticket_created_at, ticket_first_response_at) FROM tickets WHERE $filter AND $opened_in AND ticket_first_response_at IS NOT NULL")),
        'resolve' => reportMedian(reportColumn("SELECT TIMESTAMPDIFF(SECOND, ticket_created_at, ticket_resolved_at) FROM tickets WHERE $filter AND " . reportRangeSql('ticket_resolved_at', $period))),
        'seconds' => dashboardSum("SELECT SUM(TIME_TO_SEC(ticket_reply_time_worked)) FROM ticket_replies JOIN tickets ON ticket_reply_ticket_id = ticket_id
            WHERE $filter AND ticket_reply_archived_at IS NULL AND " . reportRangeSql('ticket_reply_created_at', $period)),
        'sla' => reportSlaCompliance("$filter AND $opened_in"),
    ];
    $rated = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT SUM(ticket_feedback = 'Good') AS good, SUM(ticket_feedback = 'Bad') AS bad FROM tickets
        WHERE $filter AND " . reportRangeSql('ticket_closed_at', $period)));
    $t['good'] = intval($rated['good']);
    $t['bad'] = intval($rated['bad']);
    $t['satisfaction'] = reportShare($t['good'], $t['good'] + $t['bad'], 1);

    // Over the period
    $buckets = reportBuckets($period);
    $t['opened_series'] = reportSeries("SELECT " . reportBucketSql('ticket_created_at', $buckets['unit']) . " AS b, COUNT(ticket_id) AS v FROM tickets WHERE $filter AND $opened_in GROUP BY b", $buckets['keys']);
    $t['resolved_series'] = reportSeries("SELECT " . reportBucketSql('ticket_resolved_at', $buckets['unit']) . " AS b, COUNT(ticket_id) AS v FROM tickets
        WHERE $filter AND " . reportRangeSql('ticket_resolved_at', $period) . " GROUP BY b", $buckets['keys']);

    // What about
    $names = reportTicketCategories();
    $t['categories'] = [];
    $sql = mysqli_query($mysqli, "SELECT ticket_category AS cat, COUNT(ticket_id) AS n FROM tickets WHERE $filter AND $opened_in GROUP BY ticket_category ORDER BY n DESC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $t['categories'][] = ['label' => $names[intval($row['cat'])] ?? 'No category', 'value' => intval($row['n'])];
    }

    // Who raised them, and the devices that keep coming back
    $t['contacts'] = [];
    $sql = mysqli_query($mysqli, "SELECT contact_id, contact_name, contact_title, COUNT(ticket_id) AS n FROM tickets JOIN contacts ON ticket_contact_id = contact_id
        WHERE $filter AND $opened_in GROUP BY contact_id, contact_name, contact_title ORDER BY n DESC, contact_name ASC LIMIT 6");
    while ($row = mysqli_fetch_assoc($sql)) {
        $t['contacts'][] = $row;
    }
    $t['assets'] = [];
    $sql = mysqli_query($mysqli, "SELECT assets.asset_id, asset_name, asset_type, asset_make, asset_model, asset_client_id, COUNT(DISTINCT tickets.ticket_id) AS n
        FROM tickets JOIN ticket_assets ON ticket_assets.ticket_id = tickets.ticket_id JOIN assets ON assets.asset_id = ticket_assets.asset_id
        WHERE $filter AND $opened_in GROUP BY assets.asset_id, asset_name, asset_type, asset_make, asset_model, asset_client_id ORDER BY n DESC, asset_name ASC LIMIT 6");
    while ($row = mysqli_fetch_assoc($sql)) {
        $t['assets'][] = $row;
    }

    // The urgent and high ones
    $t['urgent'] = [];
    $sql = mysqli_query($mysqli, "SELECT tickets.ticket_id, ticket_prefix, ticket_number, ticket_subject, ticket_priority, ticket_client_id, ticket_created_at, ticket_resolved_at,
            ticket_status_name, ticket_status_color, TIMESTAMPDIFF(SECOND, ticket_created_at, COALESCE(ticket_resolved_at, NOW())) AS took
        FROM tickets LEFT JOIN ticket_statuses ON ticket_status = ticket_status_id
        WHERE $filter AND $opened_in AND ticket_priority IN ('Urgent', 'High')
        ORDER BY FIELD(ticket_priority, 'Urgent', 'High'), ticket_created_at DESC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $t['urgent'][] = $row;
    }
}

/*
 * ---------- projects ----------
 */
$projects = [];
if ($client && $ticketing) {
    $sql = mysqli_query($mysqli, "SELECT project_id, project_prefix, project_number, project_name, project_start, project_due, project_budget_hours, project_created_at,
            project_completed_at, project_archived_at, project_client_id
        FROM projects WHERE project_client_id = $client_id AND project_archived_at IS NULL
        AND (project_completed_at IS NULL OR project_completed_at >= '" . $period['start'] . "')
        ORDER BY project_completed_at IS NOT NULL, project_due IS NULL, project_due ASC, project_name ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $projects[intval($row['project_id'])] = $row;
    }
    $project_stats = projectStats(array_keys($projects));
}

/*
 * ---------- hardware, renewals, services, documentation ----------
 */
$hw = null;
$renewals = [];
$services = [];
$coverage = null;
$credentials = null;
if ($client && $itdoc) {
    $assets = reportLifecycleAssets($client_id);
    $limit_days = REVIEW_OLD_YEARS * 365.25;
    $hw = ['assets' => count($assets), 'old' => 0, 'expired' => 0, 'ending' => 0, 'os' => 0, 'age_total' => 0, 'dated' => 0, 'os_names' => [], 'types' => [], 'plan' => []];
    foreach ($assets as $asset) {
        $old = $asset['age_days'] !== null && $asset['age_days'] >= $limit_days;
        $expired = $asset['warranty'] && $asset['warranty']['days'] < 0;
        $ending = $asset['warranty'] && $asset['warranty']['days'] >= 0 && $asset['warranty']['days'] <= 90;
        $hw['old'] += $old ? 1 : 0;
        $hw['expired'] += $expired ? 1 : 0;
        $hw['ending'] += $ending ? 1 : 0;
        $hw['types'][$asset['asset_type'] ?: 'Other'] = ($hw['types'][$asset['asset_type'] ?: 'Other'] ?? 0) + 1;
        if ($asset['age_days'] !== null) {
            $hw['dated']++;
            $hw['age_total'] += $asset['age_days'];
        }
        if ($asset['os_eol'] !== '') {
            $hw['os']++;
            $hw['os_names'][$asset['os_eol']] = ($hw['os_names'][$asset['os_eol']] ?? 0) + 1;
        }
        // What to plan for: unsupported, old, or a warranty about to end
        $reasons = [];
        if ($asset['os_eol'] !== '') {
            $reasons[] = ['danger', $asset['os_eol'] . ' - no longer supported'];
        }
        if ($old) {
            $reasons[] = ['danger', reportYears($asset['age_days']) . ' old'];
        }
        if ($ending) {
            $reasons[] = ['warning', 'Warranty ends ' . ($asset['warranty']['days'] === 0 ? 'today' : 'in ' . $asset['warranty']['days'] . ' days')];
        }
        if ($reasons) {
            $asset['reasons'] = $reasons;
            $asset['rank'] = ($asset['os_eol'] !== '' ? 2 : 0) + ($old ? 1 : 0);
            $hw['plan'][] = $asset;
        }
    }
    arsort($hw['types']);
    arsort($hw['os_names']);
    usort($hw['plan'], function ($a, $b) { return $b['rank'] <=> $a['rank'] ?: ($b['age_days'] ?? 0) <=> ($a['age_days'] ?? 0); });
    $hw['average'] = $hw['dated'] ? $hw['age_total'] / $hw['dated'] : null;

    $renewals = reportRenewals($client_id, 90);

    $sql = mysqli_query($mysqli, "SELECT service_id, service_name, service_category, service_importance, service_review_due, service_reviewed_at, service_client_id,
            " . serviceAttentionSql() . " AS attention
        FROM services WHERE service_client_id = $client_id AND service_archived_at IS NULL ORDER BY service_name");
    $service_count = 0;
    while ($row = mysqli_fetch_assoc($sql)) {
        $service_count++;
        $row['review'] = serviceReview($row);
        if (intval($row['attention']) || in_array($row['review']['state'], ['overdue', 'soon'], true)) {
            $services[] = $row;
        }
    }

    $coverage = reportCoverage($client_id)[$client_id] ?? null;

    if ($credentials_on) {
        $credentials = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) AS total,
                SUM(credential_password_changed_at IS NULL OR credential_password_changed_at < NOW() - INTERVAL " . CREDENTIAL_OVERDUE_DAYS . " DAY) AS stale
            FROM credentials WHERE credential_client_id = $client_id AND credential_archived_at IS NULL"));
    }
}

/*
 * ---------- billing ----------
 */
$money = null;
if ($client && $billing) {
    $paid_in = reportRangeSql('payment_date', $period);
    $open = dashboardOpenInvoicesSql("AND invoice_client_id = $client_id");
    $money = [
        'invoiced' => dashboardSum("SELECT SUM(invoice_amount) FROM invoices WHERE invoice_client_id = $client_id AND invoice_status NOT IN ('Draft', 'Cancelled', 'Non-Billable')
            AND " . reportRangeSql('invoice_date', $period)),
        'paid' => dashboardSum("SELECT SUM(payment_amount) FROM payments JOIN invoices ON payment_invoice_id = invoice_id WHERE invoice_client_id = $client_id AND $paid_in"),
        'balance' => dashboardSum("SELECT SUM(balance) FROM ($open) AS o WHERE balance > 0.004"),
        'overdue' => dashboardSum("SELECT SUM(balance) FROM ($open) AS o WHERE balance > 0.004 AND invoice_due < CURDATE()"),
        'mrr' => dashboardSum("SELECT SUM(" . recurringInvoiceMonthlySql() . ") FROM recurring_invoices
            WHERE recurring_invoice_client_id = $client_id AND recurring_invoice_status = 1 AND recurring_invoice_archived_at IS NULL"),
        'unbilled' => $ticketing ? dashboardCount("SELECT COUNT(ticket_id) FROM tickets WHERE ticket_client_id = $client_id AND ticket_billable = 1 AND ticket_invoice_id = 0
            AND ticket_archived_at IS NULL AND (ticket_resolved_at IS NOT NULL OR ticket_closed_at IS NOT NULL)") : 0,
    ];
}

/*
 * ---------- recommendations ----------
 *
 * Each [tone, icon, text (HTML), link] - what stands out, worst first.
 */
$advice = [];
if ($client) {
    $plural = function ($n, $one, $many = null) {
        return reportCount($n) . ' ' . ($n === 1 ? $one : ($many ?? $one . 's'));
    };
    if ($hw && $hw['os']) {
        $names = implode(', ', array_keys($hw['os_names']));
        $advice[] = ['danger', 'fa-shield-alt', '<strong>' . $plural($hw['os'], 'device runs', 'devices run') . ' an operating system past its end of support</strong> (' . escapeHtml($names) . ').
            They no longer get security fixes - plan the upgrades or replacements.', '/agent/reports/asset_lifecycle.php?client_id=' . $client_id];
    }
    if ($money && $money['overdue'] > 0.004) {
        $advice[] = ['danger', 'fa-file-invoice-dollar', '<strong>' . reportMoney($money['overdue']) . ' is past due</strong> of a ' . reportMoney($money['balance']) . ' balance.', '/agent/reports/outstanding_balances.php?client_id=' . $client_id];
    }
    if ($t && $t['sla']['response_percent'] !== null && $t['sla']['response_percent'] < 90) {
        $advice[] = ['warning', 'fa-stopwatch', '<strong>First replies met the SLA ' . reportPercent($t['sla']['response_percent']) . ' of the time.</strong> Look at when the late ones came in and who picked them up.',
            '/agent/reports/sla_summary.php?client_id=' . $client_id . '&' . http_build_query(reportPeriodQuery($period))];
    }
    if ($t && $t['bad']) {
        $advice[] = ['warning', 'fa-frown', '<strong>' . $plural($t['bad'], 'ticket was', 'tickets were') . ' rated bad.</strong> Go through ' . ($t['bad'] === 1 ? 'it' : 'them') . ' together and agree what changes.',
            '/agent/reports/satisfaction.php?client_id=' . $client_id . '&' . http_build_query(reportPeriodQuery($period))];
    }
    if ($t && $t['opened'] >= 5 && $t['opened_prev'] && $t['opened'] > $t['opened_prev'] * 1.25) {
        $advice[] = ['warning', 'fa-chart-line', '<strong>Tickets were up ' . reportChange($t['opened'], $t['opened_prev']) . '%</strong> on ' . escapeHtml($period['compare']) . ' (' . reportCount($t['opened']) . ' against ' . reportCount($t['opened_prev']) . ').', ''];
    }
    if ($t && $t['opened'] >= 5 && $t['categories'] && $t['categories'][0]['label'] !== 'No category' && $t['categories'][0]['value'] / $t['opened'] >= 0.3) {
        $top = $t['categories'][0];
        $advice[] = ['info', 'fa-tags', '<strong>' . escapeHtml($top['label']) . ' was ' . reportPercent(reportShare($top['value'], $t['opened'], 0)) . ' of tickets</strong> (' . reportCount($top['value']) . ').
            Fixing the cause, or some training, could cut that down.', ''];
    }
    if ($t && $t['assets'] && intval($t['assets'][0]['n']) >= 3) {
        $asset = $t['assets'][0];
        $advice[] = ['info', 'fa-redo', '<strong>' . escapeHtml($asset['asset_name']) . ' was on ' . reportCount(intval($asset['n'])) . ' tickets.</strong> Worth repairing properly or replacing.',
            '/agent/asset.php?client_id=' . $client_id . '&asset_id=' . intval($asset['asset_id'])];
    }
    if ($hw && $hw['old']) {
        $advice[] = ['warning', 'fa-recycle', '<strong>' . $plural($hw['old'], 'device is', 'devices are') . ' over ' . REVIEW_OLD_YEARS . ' years old.</strong> Budget for replacing ' . ($hw['old'] === 1 ? 'it' : 'them') . ' over the coming year.',
            '/agent/reports/asset_lifecycle.php?client_id=' . $client_id];
    }
    $manual_due = array_filter($renewals, function ($r) { return !$r['auto'] && $r['kind'] !== 'warranty'; });
    if ($manual_due) {
        $lapsed = count(array_filter($manual_due, function ($r) { return $r['days'] < 0; }));
        $advice[] = [$lapsed ? 'danger' : 'warning', 'fa-calendar-alt', '<strong>' . $plural(count($manual_due), 'domain, certificate or license needs', 'domains, certificates or licenses need') . ' renewing</strong>'
            . ($lapsed ? ', ' . reportCount($lapsed) . ' already lapsed' : ' in the next 90 days') . '.', '/agent/reports/renewals.php?client_id=' . $client_id . '&manual=1'];
    }
    if ($hw && $hw['ending']) {
        $advice[] = ['info', 'fa-shield-alt', '<strong>' . $plural($hw['ending'], 'warranty ends', 'warranties end') . ' in the next 90 days.</strong> Extend the ones on kit that matters.',
            '/agent/reports/renewals.php?client_id=' . $client_id . '&kind=warranty'];
    }
    if ($t && $t['open_old']) {
        $advice[] = ['warning', 'fa-hourglass-half', '<strong>' . $plural($t['open_old'], 'ticket has', 'tickets have') . ' been open over 30 days.</strong> Close them out or agree a plan.',
            '/agent/reports/ticket_aging.php?client_id=' . $client_id];
    }
    if ($services) {
        $advice[] = ['info', 'fa-stream', '<strong>' . $plural(count($services), 'service needs', 'services need') . ' a look</strong> - a review due, or something it depends on expiring or out of action.',
            '/agent/services.php?client_id=' . $client_id];
    }
    if ($credentials && intval($credentials['stale'])) {
        $advice[] = ['info', 'fa-key', '<strong>' . $plural(intval($credentials['stale']), 'password has', 'passwords have') . ' not changed in over a year.</strong>',
            '/agent/reports/credential_rotation.php?client_id=' . $client_id . '&days=365'];
    }
    if ($coverage && $coverage['missing']) {
        $checks = reportCoverageChecks();
        $advice[] = ['info', 'fa-tasks', '<strong>Documentation is missing:</strong> ' . escapeHtml(implode(', ', array_map(function ($k) use ($checks) { return strtolower($checks[$k]['label']); }, $coverage['missing']))) . '.',
            '/agent/reports/documentation_coverage.php?gaps=1'];
    }
    if ($money && $money['unbilled']) {
        $advice[] = ['info', 'fa-file-invoice', '<strong>' . $plural($money['unbilled'], 'billable ticket is', 'billable tickets are') . ' done but not invoiced yet.</strong>', '/agent/reports/tickets_unbilled.php?client_id=' . $client_id];
    }
}

/*
 * ---------- export: the tickets in the period ----------
 */
if ($t && reportExporting()) {
    $rows = [];
    $sql = mysqli_query($mysqli, "SELECT ticket_id, ticket_prefix, ticket_number, ticket_subject, ticket_category, ticket_priority, ticket_status_name, ticket_created_at,
            ticket_first_response_at, ticket_resolved_at, contact_name, user_name,
            (SELECT SUM(TIME_TO_SEC(ticket_reply_time_worked)) FROM ticket_replies WHERE ticket_reply_ticket_id = ticket_id AND ticket_reply_archived_at IS NULL) AS seconds
        FROM tickets LEFT JOIN contacts ON ticket_contact_id = contact_id LEFT JOIN users ON ticket_assigned_to = user_id LEFT JOIN ticket_statuses ON ticket_status = ticket_status_id
        WHERE " . reportTicketSql($client_id) . " AND " . reportRangeSql('ticket_created_at', $period) . " ORDER BY ticket_created_at ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $row['category'] = $names[intval($row['ticket_category'])] ?? '';
        $row['response'] = $row['ticket_first_response_at'] ? strtotime($row['ticket_first_response_at']) - strtotime($row['ticket_created_at']) : null;
        $rows[] = $row;
    }
    reportCsv($client['client_name'] . ' Tickets ' . $period['start'] . ' to ' . $period['end'], [
        'ticket' => ['label' => 'Ticket', 'csv' => function ($row) { return ticketReference($row); }],
        'ticket_subject' => ['label' => 'Subject'],
        'category' => ['label' => 'Category'],
        'ticket_priority' => ['label' => 'Priority'],
        'ticket_status_name' => ['label' => 'Status'],
        'contact_name' => ['label' => 'Contact'],
        'user_name' => ['label' => 'Assigned to'],
        'ticket_created_at' => ['label' => 'Opened'],
        'response' => ['label' => 'Hours to first reply', 'type' => 'hours'],
        'ticket_resolved_at' => ['label' => 'Resolved'],
        'seconds' => ['label' => 'Hours logged', 'type' => 'hours'],
    ], $rows);
}

require_once "includes/inc_report_layout.php";

$header = [
    'period' => $period,
    'client' => $client_id,
    'client_required' => true,
    'export' => $t ? ['tickets' => 'Tickets in the period'] : false,
    'print_meta' => $client ? $client['client_name'] : '',
];

?>

<div class="itflow-report itflow-report-review">

    <?php reportHeader('client_review', $header); ?>

    <?php if (!$client) { ?>

        <div class="card"><div class="card-body">
            <?= reportEmpty('Pick a client above to build their review.', 'fa-clipboard-list', 'secondary') ?>
        </div></div>

    <?php } else { ?>

        <!-- Who it is for -->
        <div class="card">
            <div class="card-body d-flex flex-wrap align-items-center gap-3">
                <?php $tone = clientInitialsTone($client_id); ?>
                <span class="itflow-client-avatar bg-<?= $tone ?>-subtle text-<?= $tone ?>-emphasis"><?= escapeHtml(clientInitials((string) $client['client_abbreviation'], $client['client_name'])) ?></span>
                <div class="me-auto">
                    <h2 class="h4 mb-0"><a class="text-body text-decoration-none" href="/agent/client_overview.php?client_id=<?= $client_id ?>"><?= escapeHtml($client['client_name']) ?></a></h2>
                    <div class="text-body-secondary">Business review · <?= escapeHtml($period['range']) ?></div>
                </div>
                <dl class="row small mb-0 g-2 text-body-secondary">
                    <?php if ($primary) { ?>
                        <dt class="col-auto fw-normal">Contact</dt>
                        <dd class="col-auto mb-0 text-body"><?= escapeHtml($primary['contact_name']) ?><?= $primary['contact_title'] ? ', ' . escapeHtml($primary['contact_title']) : '' ?></dd>
                    <?php } ?>
                    <dt class="col-auto fw-normal">Client since</dt>
                    <dd class="col-auto mb-0 text-body"><?= escapeHtml(date('M Y', strtotime($client['client_created_at']))) ?></dd>
                </dl>
            </div>
        </div>

        <?= reportSectionOpen('Recommendations', $advice ? reportCount(count($advice)) . ' to talk through' : '') ?>
            <div class="card-body">
                <?php if (!$advice) { ?>
                    <p class="mb-0 text-success-emphasis"><i class="fas fa-check-circle me-2"></i>Nothing stands out - a quiet, healthy period.</p>
                <?php } else { ?>
                    <ul class="itflow-report-advice">
                        <?php foreach ($advice as [$tone, $icon, $text, $link]) { ?>
                            <li>
                                <i class="fas fa-fw <?= $icon ?> text-<?= $tone ?>"></i>
                                <div class="flex-grow-1"><?= $text ?></div>
                                <?php if ($link) { ?><a class="small text-nowrap d-print-none" href="<?= escapeHtml($link) ?>">Details<i class="fas fa-angle-right ms-1"></i></a><?php } ?>
                            </li>
                        <?php } ?>
                    </ul>
                <?php } ?>
            </div>
        </div>

        <?php if ($t) { ?>
            <h2 class="itflow-report-group-title mt-4 mb-2">Service desk</h2>
            <?= reportStats([
                ['label' => 'Tickets raised', 'value' => reportCount($t['opened']), 'url' => '/agent/tickets.php?client_id=' . $client_id . '&show=all',
                    'sub' => reportChangeHtml($t['opened'], $t['opened_prev'], 'down', $period['compare'])],
                ['label' => 'Resolved', 'value' => reportCount($t['resolved']), 'sub' => reportCount($t['open_now']) . ' <span class="text-body-tertiary">open now</span>'],
                ['label' => 'Typical first reply', 'value' => $t['response'] === null ? '-' : reportDuration($t['response']),
                    'sub' => $t['resolve'] === null ? '' : 'Resolved in <span class="text-body">' . reportDuration($t['resolve']) . '</span>'],
                ['label' => 'SLA met', 'value_html' => $t['sla']['response_percent'] === null ? '<span class="text-body-tertiary">-</span>' : reportRateHtml($t['sla']['response_percent']),
                    'sub' => $t['sla']['resolution_percent'] === null ? '<span class="text-body-tertiary">No SLA tickets</span>' : 'Resolution <span class="text-body">' . reportPercent($t['sla']['resolution_percent']) . '</span>'],
                ['label' => 'Satisfaction', 'value_html' => $t['satisfaction'] === null ? '<span class="text-body-tertiary">-</span>' : reportRateHtml($t['satisfaction']),
                    'sub' => ($t['good'] + $t['bad']) ? reportCount($t['good'] + $t['bad']) . ' <span class="text-body-tertiary">ratings</span>' : '<span class="text-body-tertiary">No ratings</span>'],
                ['label' => 'Time spent', 'value' => reportHours($t['seconds']) . ' h', 'sub' => '<span class="text-body-tertiary">Logged on their tickets</span>'],
            ]) ?>
        <?php } ?>

        <?php if ($t && $t['opened'] + $t['resolved'] > 0) { ?>
            <div class="row g-3">
                <div class="col-lg-7">
                    <?= reportSectionOpen('Tickets over the period', '', 'h-100') ?>
                        <div class="card-body">
                            <?= reportChart(['type' => 'bar', 'labels' => $buckets['labels'], 'series' => [
                                ['label' => 'Raised', 'data' => $t['opened_series'], 'tone' => 'accent'],
                                ['label' => 'Resolved', 'data' => $t['resolved_series'], 'tone' => 'success'],
                            ], 'format' => 'count'], '', 'Tickets raised and resolved over the period') ?>
                        </div>
                    </div>
                </div>
                <div class="col-lg-5">
                    <?= reportSectionOpen('What they were about', '', 'h-100') ?>
                        <div class="card-body">
                            <?php $cats = reportTopN($t['categories'], 6); ?>
                            <?php if ($cats) { ?>
                                <?= reportChart(['type' => 'bar', 'horizontal' => true, 'labels' => array_column($cats, 'label'),
                                    'series' => [['label' => 'Tickets', 'data' => array_column($cats, 'value'), 'tones' => array_map(function ($i, $c) { return $c['color'] ?? reportPalette($i); }, array_keys($cats), $cats)]],
                                    'format' => 'count', 'legend' => false], '', 'Tickets raised by category') ?>
                            <?php } else { ?>
                                <?= reportEmpty('No tickets raised.', 'fa-tags', 'secondary') ?>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-lg-6">
                    <?= reportSectionOpen('Who raised the most', '', 'h-100') ?>
                        <?= reportTable([
                            'contact_name' => ['label' => 'Contact', 'html' => function ($row) use ($client_id) {
                                return '<a class="fw-semibold text-body" href="/agent/contact.php?client_id=' . $client_id . '&contact_id=' . intval($row['contact_id']) . '">' . escapeHtml($row['contact_name']) . '</a>'
                                    . ($row['contact_title'] ? '<div class="small text-body-secondary">' . escapeHtml($row['contact_title']) . '</div>' : '');
                            }],
                            'n' => ['label' => 'Tickets', 'type' => 'count'],
                        ], $t['contacts'], ['sortable' => false, 'empty' => 'No tickets had a contact.']) ?>
                    </div>
                </div>
                <div class="col-lg-6">
                    <?= reportSectionOpen('Devices with the most tickets', '', 'h-100') ?>
                        <?= reportTable([
                            'asset_name' => ['label' => 'Device', 'html' => function ($row) { return reportAssetCell($row); }],
                            'model' => ['label' => 'Make and model', 'html' => function ($row) {
                                return '<span class="small text-body-secondary">' . escapeHtml(trim($row['asset_make'] . ' ' . $row['asset_model'])) . '</span>';
                            }],
                            'n' => ['label' => 'Tickets', 'type' => 'count'],
                        ], $t['assets'], ['sortable' => false, 'empty' => 'No tickets were linked to a device.']) ?>
                    </div>
                </div>
            </div>

            <?php if ($t['urgent']) { ?>
                <?= reportSectionOpen('Urgent and high priority', reportCount(count($t['urgent'])) . ' raised in the period') ?>
                    <?= reportTable([
                        'ticket' => ['label' => 'Ticket', 'class' => 'itflow-report-text', 'html' => function ($row) {
                            return reportPriorityDot($row['ticket_priority']) . reportTicketLink($row);
                        }],
                        'ticket_status_name' => ['label' => 'Status', 'html' => function ($row) {
                            $color = preg_match('/^#[0-9a-f]{3,8}$/i', (string) $row['ticket_status_color']) ? $row['ticket_status_color'] : '#6c757d';
                            return '<span class="badge rounded-pill text-white" style="background-color: ' . $color . '">' . escapeHtml($row['ticket_status_name']) . '</span>';
                        }],
                        'ticket_created_at' => ['label' => 'Raised', 'type' => 'date'],
                        'took' => ['label' => 'Resolved in', 'type' => 'duration', 'html' => function ($row) {
                            return $row['ticket_resolved_at'] ? reportDuration($row['took']) : '<span class="text-body-secondary small">Open ' . reportDuration($row['took']) . '</span>';
                        }],
                    ], $t['urgent'], ['sortable' => false, 'limit' => 15]) ?>
                </div>
            <?php } ?>
        <?php } ?>

        <?php if ($projects) { ?>
            <?= reportSectionOpen('Projects', 'Open, and completed since the period began') ?>
                <?= reportTable([
                    'project_name' => ['label' => 'Project', 'class' => 'itflow-report-text', 'html' => function ($row) {
                        return '<a class="fw-semibold text-body" href="/agent/' . projectUrl($row['project_id'], $row['project_client_id']) . '">' . escapeHtml($row['project_name']) . '</a>'
                            . '<div class="small text-body-secondary">' . escapeHtml(projectReference($row)) . '</div>';
                    }],
                    'state' => ['label' => 'Status', 'html' => function ($row) {
                        return '<span class="d-inline-flex flex-wrap gap-1">' . projectStatePill($row) . (projectIsOpen($row) ? projectDuePill($row) : '') . '</span>';
                    }],
                    'progress' => ['label' => 'Progress', 'html' => function ($row) use ($project_stats) {
                        return projectProgressHtml($project_stats[intval($row['project_id'])], true);
                    }],
                    'time' => ['label' => 'Time', 'html' => function ($row) use ($project_stats) {
                        return projectTimeHtml($row, $project_stats[intval($row['project_id'])]);
                    }],
                ], array_values($projects), ['sortable' => false]) ?>
            </div>
        <?php } ?>

        <?php if ($hw) { ?>
            <h2 class="itflow-report-group-title mt-4 mb-2">Hardware and documentation</h2>
            <?= reportStats([
                ['label' => 'Devices in use', 'value' => reportCount($hw['assets']), 'url' => '/agent/assets.php?client_id=' . $client_id,
                    'sub' => implode(' · ', array_map(function ($type, $n) { return escapeHtml($type) . ' <span class="text-body">' . reportCount($n) . '</span>'; },
                        array_slice(array_keys($hw['types']), 0, 3), array_slice(array_values($hw['types']), 0, 3)))],
                ['label' => 'Average age', 'value' => $hw['average'] === null ? '-' : reportYears($hw['average'])],
                ['label' => 'Over ' . REVIEW_OLD_YEARS . ' years old', 'value' => reportCount($hw['old']), 'tone' => $hw['old'] ? 'danger' : 'success'],
                ['label' => 'Out of warranty', 'value' => reportCount($hw['expired']), 'tone' => $hw['expired'] ? 'warning' : '',
                    'sub' => reportCount($hw['ending']) . ' <span class="text-body-tertiary">more within 90 days</span>'],
                ['label' => 'Unsupported OS', 'value' => reportCount($hw['os']), 'tone' => $hw['os'] ? 'danger' : 'success'],
            ]) ?>

            <?php if ($hw['plan']) { ?>
                <?= reportSectionOpen('Hardware to plan for', 'Unsupported, over ' . REVIEW_OLD_YEARS . ' years old, or the warranty ending soon') ?>
                    <?= reportTable([
                        'asset_name' => ['label' => 'Device', 'html' => function ($row) { return reportAssetCell($row); }],
                        'model' => ['label' => 'Make and model', 'html' => function ($row) {
                            return '<span class="text-nowrap">' . escapeHtml(trim($row['asset_make'] . ' ' . $row['asset_model'])) . '</span>';
                        }],
                        'contact_name' => ['label' => 'Used by', 'html' => function ($row) {
                            return $row['contact_name'] ? escapeHtml($row['contact_name']) : '<span class="text-body-tertiary">-</span>';
                        }],
                        'reasons' => ['label' => 'Why', 'html' => function ($row) {
                            $html = '<span class="d-inline-flex flex-wrap gap-1">';
                            foreach ($row['reasons'] as [$tone, $text]) {
                                $html .= '<span class="badge rounded-pill bg-' . $tone . '-subtle text-' . $tone . '-emphasis border border-' . $tone . '-subtle fw-normal">' . escapeHtml($text) . '</span>';
                            }
                            return $html . '</span>';
                        }],
                    ], $hw['plan'], ['sortable' => false, 'limit' => 20]) ?>
                </div>
            <?php } ?>
        <?php } ?>

        <?php if ($itdoc && $renewals) { ?>
            <?= reportSectionOpen('Renewals', 'Due in the next 90 days, or lapsed lately') ?>
                <?= reportTable([
                    'days' => ['label' => 'Due', 'html' => function ($row) { return reportDuePill($row['days'], $row['auto']); }],
                    'name' => ['label' => 'What', 'class' => 'itflow-report-text', 'html' => function ($row) {
                        return '<div class="d-flex align-items-center gap-2">' . $row['tile'] . '<div class="min-w-0"><a class="fw-semibold text-body" href="' . escapeHtml($row['url']) . '">' . escapeHtml($row['name']) . '</a>'
                            . ($row['detail'] !== '' ? '<div class="small text-body-secondary">' . escapeHtml($row['detail']) . '</div>' : '') . '</div></div>';
                    }],
                    'kind' => ['label' => 'Type', 'html' => function ($row) { return REPORT_RENEWAL_KINDS[$row['kind']]['one']; }],
                    'date' => ['label' => 'Date', 'type' => 'date'],
                ], $renewals, ['sortable' => false, 'limit' => 20]) ?>
            </div>
        <?php } ?>

        <?php if ($services) { ?>
            <?= reportSectionOpen('Services needing a look', reportCount(count($services)) . ' of ' . reportCount($service_count)) ?>
                <?= reportTable([
                    'service_name' => ['label' => 'Service', 'html' => function ($row) {
                        return '<span class="d-inline-flex align-items-center gap-2">' . serviceTile($row['service_category'], 'sm')
                            . '<a class="fw-semibold text-body" href="/agent/service.php?client_id=' . intval($row['service_client_id']) . '&service_id=' . intval($row['service_id']) . '">' . escapeHtml($row['service_name']) . '</a></span>';
                    }],
                    'service_importance' => ['label' => 'Importance'],
                    'review' => ['label' => 'Review', 'html' => function ($row) { return serviceReviewPill($row['review']); }],
                    'attention' => ['label' => 'Depends on', 'html' => function ($row) {
                        return intval($row['attention']) ? '<span class="text-danger-emphasis small"><i class="fas fa-exclamation-triangle me-1"></i>Something expiring or out of action</span>' : '<span class="text-body-tertiary small">All fine</span>';
                    }],
                ], $services, ['sortable' => false]) ?>
            </div>
        <?php } ?>

        <?php if ($coverage) { ?>
            <?= reportSectionOpen('Documentation', reportPercent($coverage['score']) . ' covered - ' . $coverage['passed'] . ' of ' . $coverage['applies'] . ' checks') ?>
                <div class="card-body d-flex flex-wrap align-items-center gap-3">
                    <?php $tone = $coverage['score'] >= 90 ? 'success' : ($coverage['score'] >= 70 ? 'warning' : 'danger'); ?>
                    <div class="progress itflow-report-meter" style="width: 10rem" role="presentation"><div class="progress-bar bg-<?= $tone ?>" style="width: <?= round($coverage['score']) ?>%"></div></div>
                    <?php if ($coverage['missing']) { ?>
                        <span class="small text-body-secondary">Missing</span>
                        <?= reportCoverageMissingHtml($coverage) ?>
                    <?php } else { ?>
                        <span class="small text-success-emphasis"><i class="fas fa-check-circle me-1"></i>Everything we check is documented</span>
                    <?php } ?>
                </div>
            </div>
        <?php } ?>

        <?php if ($money) { ?>
            <h2 class="itflow-report-group-title mt-4 mb-2">Billing</h2>
            <?= reportStats([
                ['label' => 'Invoiced', 'value' => reportMoney($money['invoiced']), 'sub' => '<span class="text-body-tertiary">In the period</span>', 'url' => '/agent/invoices.php?client_id=' . $client_id],
                ['label' => 'Paid', 'value' => reportMoney($money['paid']), 'sub' => '<span class="text-body-tertiary">In the period</span>'],
                ['label' => 'Balance owing', 'value' => reportMoney($money['balance']), 'tone' => $money['overdue'] > 0.004 ? 'danger' : '',
                    'sub' => $money['overdue'] > 0.004 ? reportMoney($money['overdue']) . ' <span class="text-body-tertiary">past due</span>' : '<span class="text-body-tertiary">Nothing past due</span>'],
                ['label' => 'Monthly recurring', 'value' => reportMoney($money['mrr']), 'sub' => reportMoney($money['mrr'] * 12) . ' <span class="text-body-tertiary">a year</span>'],
            ]) ?>
        <?php } ?>

        <p class="small text-body-secondary">
            Times run on the clock from when a ticket was raised; "typical" is the middle ticket, so a few long ones do not skew it. The SLA counts business hours.
            Hardware age runs from the purchase date, else the install date.
        </p>

    <?php } ?>

</div>

<?php require_once "../../includes/footer.php"; ?>
<script src="/js/reports.js"></script>
