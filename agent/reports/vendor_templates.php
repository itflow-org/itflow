<?php

/*
 * Vendor Templates - for each template: how many client vendors were made
 * from it in the period, against the period before; the clients with a
 * current vendor from it, how many of those are out of step with it and how
 * many have no account number yet; for one that every client (or the clients
 * with a tag) should have, how many of them do; and what hangs off its
 * vendors - for someone who sees Support, the current licenses and assets
 * that name them and the cases logged with them in the period (and the ones
 * still open), and for someone who sees the books, what was spent with our
 * own vendor from it in the period. By client too - the templates each
 * should have a vendor from, the ones missing, its vendors out of step or
 * without an account number, and its open cases with them. One client when
 * picked; everything but what was made, the cases and the spend is as it is
 * now.
 */

require_once "includes/inc_report.php";
reportEnforce('vendor_templates');

$period = reportPeriod('last_90');
$client_id = reportClientId();
$admin = !empty($session_is_admin);
$sees_docs = reportFeatureOn('itdoc') && lookupUserPermission('module_support') >= 1;
$sees_cases = reportFeatureOn('ticketing') && lookupUserPermission('module_support') >= 1;
// What we spend with a vendor is the company's, not a client's - left out when one client is picked
$sees_spend = reportFeatureOn('accounting') && lookupUserPermission('module_financial') >= 1 && !$client_id;

// The current clients this user may see (the client picked)
$client_rows = [];
$sql = mysqli_query($mysqli, "SELECT client_id, client_name, client_abbreviation FROM clients WHERE client_archived_at IS NULL"
    . ($client_id ? " AND client_id = $client_id" : '') . clientScopeSql('clients.client_id') . " ORDER BY client_name ASC");
while ($row = mysqli_fetch_assoc($sql)) {
    $client_rows[intval($row['client_id'])] = $row;
}
$client_ids = array_keys($client_rows) ?: [0];
$in_clients = function ($column) use ($client_ids) {
    return "$column IN (" . implode(',', $client_ids) . ")";
};

// Every current template, and archived ones a vendor was made from in the period
$made = "vendors.vendor_template_id > 0 AND " . $in_clients('vendors.vendor_client_id');
$templates = [];
$template_rows = [];
$sql = mysqli_query($mysqli, "SELECT * FROM vendor_templates
    WHERE vendor_template_archived_at IS NULL OR vendor_template_id IN (SELECT vendors.vendor_template_id FROM vendors WHERE $made AND " . reportRangeSql('vendors.vendor_created_at', $period) . ")
    ORDER BY vendor_template_name ASC");
while ($row = mysqli_fetch_assoc($sql)) {
    $id = intval($row['vendor_template_id']);
    $template_rows[$id] = $row;
    $templates[$id] = ['id' => $id, 'template' => $row['vendor_template_name'], 'archived' => !empty($row['vendor_template_archived_at']),
        'required' => $row['vendor_template_archived_at'] ? 0 : intval($row['vendor_template_required']), 'scope' => '',
        'made' => 0, 'prev' => 0, 'clients' => 0, 'vendors' => 0, 'in_step' => 0, 'out' => 0, 'no_account' => 0, 'ours' => false,
        'should' => null, 'have' => null, 'coverage' => null, 'licenses' => 0, 'assets' => 0, 'cases' => 0, 'open' => 0, 'spend' => null];
}
$required_tags = vendorTemplateRequiredTags(array_keys($templates));

// Made in the period, and the period before
foreach (['made' => false, 'prev' => true] as $key => $previous) {
    $sql = mysqli_query($mysqli, "SELECT vendors.vendor_template_id, COUNT(*) FROM vendors WHERE $made AND " . reportRangeSql('vendors.vendor_created_at', $period, $previous) . " GROUP BY vendors.vendor_template_id");
    while ($row = mysqli_fetch_row($sql)) {
        if (isset($templates[intval($row[0])])) {
            $templates[intval($row[0])][$key] = intval($row[1]);
        }
    }
}

// Each one's current vendors, for the clients counted - in step with it or not, with an account number or not
$by_client = [];
foreach ($client_rows as $id => $row) {
    $by_client[$id] = ['client_id' => $id, 'client' => $row['client_name'], 'abbr' => (string) $row['client_abbreviation'],
        'vendors' => 0, 'should' => 0, 'have' => 0, 'missing' => [], 'out' => 0, 'no_account' => 0, 'open' => 0];
}
$has_one = [];
$clients_of = [];
$sql = mysqli_query($mysqli, "SELECT vendors.* FROM vendors WHERE vendors.vendor_template_id > 0 AND vendor_archived_at IS NULL AND " . $in_clients('vendor_client_id'));
while ($vendor = mysqli_fetch_assoc($sql)) {
    $id = intval($vendor['vendor_template_id']);
    $cid = intval($vendor['vendor_client_id']);
    if (!isset($templates[$id])) {
        continue;
    }
    $out = (bool) vendorTemplateDifferences($template_rows[$id], $vendor);
    $no_account = trim((string) $vendor['vendor_account_number']) === '';
    $templates[$id]['vendors']++;
    $templates[$id][$out ? 'out' : 'in_step']++;
    $templates[$id]['no_account'] += $no_account ? 1 : 0;
    $clients_of[$id][$cid] = true;
    $has_one[$id][$cid] = true;
    $by_client[$cid]['vendors']++;
    $by_client[$cid]['out'] += $out ? 1 : 0;
    $by_client[$cid]['no_account'] += $no_account ? 1 : 0;
}
foreach ($templates as $id => &$template) {
    $template['clients'] = count($clients_of[$id] ?? []);
}
unset($template);

// Our own vendor from each, when every client is counted
if (!$client_id) {
    $sql = mysqli_query($mysqli, "SELECT DISTINCT vendor_template_id FROM vendors WHERE vendor_template_id > 0 AND vendor_client_id = 0 AND vendor_archived_at IS NULL");
    while ($row = mysqli_fetch_row($sql)) {
        if (isset($templates[intval($row[0])])) {
            $templates[intval($row[0])]['ours'] = true;
        }
    }
}

// What names their vendors - current licenses and assets, cases logged in the period and the ones open now
$from_template = function ($table, $vendor_column, $client_column, $where) use ($in_clients) {
    global $mysqli;
    $counts = [];
    $sql = mysqli_query($mysqli, "SELECT vendors.vendor_template_id, COUNT(*) FROM $table JOIN vendors ON vendors.vendor_id = $table.$vendor_column
        WHERE vendors.vendor_template_id > 0 AND $where AND " . $in_clients("$table.$client_column") . " GROUP BY vendors.vendor_template_id");
    while ($row = mysqli_fetch_row($sql)) {
        $counts[intval($row[0])] = intval($row[1]);
    }
    return $counts;
};
if ($sees_docs) {
    foreach ($from_template('software', 'software_vendor_id', 'software_client_id', 'software_archived_at IS NULL') as $id => $count) {
        if (isset($templates[$id])) {
            $templates[$id]['licenses'] = $count;
        }
    }
    foreach ($from_template('assets', 'asset_vendor_id', 'asset_client_id', 'asset_archived_at IS NULL') as $id => $count) {
        if (isset($templates[$id])) {
            $templates[$id]['assets'] = $count;
        }
    }
}
if ($sees_cases) {
    foreach (['cases' => reportRangeSql('tickets.ticket_created_at', $period), 'open' => 'tickets.ticket_resolved_at IS NULL AND tickets.ticket_closed_at IS NULL'] as $key => $where) {
        foreach ($from_template('tickets', 'ticket_vendor_id', 'ticket_client_id', "tickets.ticket_archived_at IS NULL AND $where") as $id => $count) {
            if (isset($templates[$id])) {
                $templates[$id][$key] = $count;
            }
        }
    }
    $sql = mysqli_query($mysqli, "SELECT tickets.ticket_client_id, COUNT(*) FROM tickets JOIN vendors ON vendors.vendor_id = tickets.ticket_vendor_id
        WHERE vendors.vendor_template_id > 0 AND tickets.ticket_archived_at IS NULL AND tickets.ticket_resolved_at IS NULL AND tickets.ticket_closed_at IS NULL AND " . $in_clients('tickets.ticket_client_id') . "
        GROUP BY tickets.ticket_client_id");
    while ($row = mysqli_fetch_row($sql)) {
        if (isset($by_client[intval($row[0])])) {
            $by_client[intval($row[0])]['open'] = intval($row[1]);
        }
    }
}

// What we spent with our own vendor from each in the period - as Spend by Vendor counts it
if ($sees_spend) {
    foreach ($templates as $id => &$template) {
        $template['spend'] = $template['ours'] ? 0.0 : null;
    }
    unset($template);
    $sql = mysqli_query($mysqli, "SELECT vendors.vendor_template_id, SUM(expense_amount) FROM expenses JOIN vendors ON vendors.vendor_id = expense_vendor_id
        WHERE vendors.vendor_template_id > 0 AND vendors.vendor_client_id = 0 AND " . reportRangeSql('expense_date', $period) . " GROUP BY vendors.vendor_template_id");
    while ($row = mysqli_fetch_row($sql)) {
        if (isset($templates[intval($row[0])])) {
            $templates[intval($row[0])]['spend'] = round(floatval($row[1]), 2);
        }
    }
}

// Who should have each - and which have one
foreach ($templates as $id => &$template) {
    if (!$template['required']) {
        continue;
    }
    $template['scope'] = vendorTemplateScopeText($template['required'], $required_tags[$id]);
    $template['should'] = $template['have'] = 0;
    $sql = mysqli_query($mysqli, "SELECT client_id FROM clients WHERE " . vendorTemplateScopeSql($template['required'], array_keys($required_tags[$id]))
        . ($client_id ? " AND client_id = $client_id" : '') . clientScopeSql('clients.client_id'));
    while ($row = mysqli_fetch_row($sql)) {
        $cid = intval($row[0]);
        $template['should']++;
        if (isset($has_one[$id][$cid])) {
            $template['have']++;
            $by_client[$cid]['have']++;
        } else {
            $by_client[$cid]['missing'][] = $template['template'];
        }
        $by_client[$cid]['should']++;
    }
    $template['coverage'] = $template['should'] ? reportShare($template['have'], $template['should'], 1) : null;
}
unset($template);

foreach ($templates as &$row) {
    $row['change'] = reportChange($row['made'], $row['prev']);
}
unset($row);
uasort($templates, function ($a, $b) {
    return $b['clients'] <=> $a['clients'] ?: $b['made'] <=> $a['made'] ?: strcasecmp($a['template'], $b['template']);
});
$templates = array_values($templates);

// Only the clients something applies to - most missing first
$by_client = array_values(array_filter($by_client, function ($c) {
    return $c['should'] || $c['vendors'];
}));
usort($by_client, function ($a, $b) {
    return count($b['missing']) <=> count($a['missing']) ?: $b['out'] <=> $a['out'] ?: $b['no_account'] <=> $a['no_account'] ?: strcasecmp($a['client'], $b['client']);
});

$zero = function ($value, $tone = '') {
    return $value ? '<span class="fw-semibold' . ($tone ? " text-$tone-emphasis" : '') . '">' . reportCount($value) . '</span>' : '<span class="itflow-report-zero">0</span>';
};
$columns = [
    'template' => ['label' => 'Template', 'html' => function ($row) use ($admin) {
        $name = escapeHtml($row['template']);
        return ($admin ? '<a href="/admin/vendor_template.php?vendor_template_id=' . $row['id'] . '">' . $name . '</a>' : $name)
            . ($row['archived'] ? ' <span class="badge text-bg-light border fw-normal">Archived</span>' : '');
    }],
    'scope' => ['label' => 'Required of', 'html' => function ($row) {
        return $row['scope'] !== '' ? '<span class="small">' . escapeHtml($row['scope']) . '</span>' : '<span class="itflow-report-zero">–</span>';
    }],
    'made' => ['label' => 'Added', 'type' => 'count', 'title' => 'Client vendors made from it in the period'],
    'change' => ['label' => 'Change', 'type' => 'percent', 'csv' => false, 'html' => function ($row) {
        return $row['change'] === null ? '<span class="itflow-report-zero">–</span>' : '<span class="small">' . reportChangeHtml($row['made'], $row['prev'], 'up') . '</span>';
    }],
    'clients' => ['label' => 'Clients', 'type' => 'count', 'class' => 'fw-semibold', 'title' => 'Clients with a current vendor from it'],
    'vendors' => ['label' => 'Vendors', 'type' => 'count', 'screen' => false],
    'out' => ['label' => 'Out of step', 'type' => 'count', 'title' => 'Their vendors that say something other than the template does', 'html' => function ($row) use ($admin, $zero) {
        if ($row['out'] && $admin) {
            return '<a class="fw-semibold text-warning-emphasis" href="/admin/vendor_template.php?vendor_template_id=' . $row['id'] . '&amp;view=out#vendorTemplateVendors">' . reportCount($row['out']) . '</a>';
        }
        return $zero($row['out'], 'warning');
    }],
    'no_account' => ['label' => 'No account number', 'type' => 'count', 'title' => "Their vendors without the client's account number", 'html' => function ($row) use ($zero) {
        return $zero($row['no_account'], 'warning');
    }],
    'should' => ['label' => 'Should have one', 'type' => 'count', 'screen' => false],
    'have' => ['label' => 'Have one', 'type' => 'count', 'screen' => false],
    'coverage' => ['label' => 'Coverage', 'type' => 'percent', 'title' => 'Of the clients that should have it, how many do', 'html' => function ($row) {
        if ($row['coverage'] === null) {
            return '<span class="itflow-report-zero">–</span>';
        }
        $tone = $row['coverage'] >= 100 ? 'success' : ($row['coverage'] >= 75 ? 'warning' : 'danger');
        return '<div class="d-flex align-items-center justify-content-end gap-2"><div class="progress itflow-report-meter" style="width: 4rem" role="presentation">'
            . '<div class="progress-bar bg-' . $tone . '" style="width: ' . round($row['coverage']) . '%"></div></div>'
            . '<span class="small text-nowrap" title="' . $row['have'] . ' of ' . $row['should'] . ' clients">' . $row['have'] . ' of ' . $row['should'] . '</span></div>';
    }],
];
if ($sees_docs) {
    $columns['licenses'] = ['label' => 'Licenses', 'type' => 'count', 'title' => 'Current licenses from their vendors'];
    $columns['assets'] = ['label' => 'Assets', 'type' => 'count', 'title' => 'Current assets bought from their vendors'];
}
if ($sees_cases) {
    $columns['cases'] = ['label' => 'Cases', 'type' => 'count', 'title' => 'Tickets with their vendors on them, opened in the period'];
    $columns['open'] = ['label' => 'Open cases', 'type' => 'count', 'title' => 'Tickets with their vendors on them, open now', 'html' => function ($row) use ($zero) {
        return $zero($row['open'], 'warning');
    }];
}
if ($sees_spend) {
    $columns['spend'] = ['label' => 'Spent', 'type' => 'money', 'title' => 'Expenses with our own vendor from it in the period', 'html' => function ($row) {
        return $row['spend'] === null ? '<span class="itflow-report-zero" title="Not one of our own vendors">–</span>' : ($row['spend'] ? reportMoney($row['spend']) : '<span class="itflow-report-zero">' . reportMoney(0) . '</span>');
    }];
}

$client_columns = [
    'client' => ['label' => 'Client', 'html' => function ($row) {
        return reportClientCell($row['client_id'], $row['client'], $row['abbr'], 'vendors.php');
    }],
    'vendors' => ['label' => 'Vendors', 'type' => 'count', 'title' => 'Current vendors made from a template'],
    'should' => ['label' => 'Should have', 'type' => 'count'],
    'have' => ['label' => 'Have', 'type' => 'count'],
    'missing' => ['label' => 'Missing', 'sort' => false, 'class' => 'itflow-report-text', 'csv' => function ($row) {
        return implode(', ', $row['missing']);
    }, 'html' => function ($row) {
        return $row['missing'] ? '<span class="small text-warning-emphasis">' . escapeHtml(implode(', ', $row['missing'])) . '</span>' : ($row['should'] ? '<span class="small text-success"><i class="fas fa-check me-1"></i>None</span>' : '');
    }],
    'out' => ['label' => 'Out of step', 'type' => 'count', 'title' => 'Its vendors that say something other than their template', 'html' => function ($row) use ($zero) {
        return $zero($row['out'], 'warning');
    }],
    'no_account' => ['label' => 'No account number', 'type' => 'count', 'html' => function ($row) use ($zero) {
        return $zero($row['no_account'], 'warning');
    }],
];
if ($sees_cases) {
    $client_columns['open'] = ['label' => 'Open cases', 'type' => 'count', 'title' => 'Its tickets open now with a vendor from a template on them'];
}

$sum = function ($key) use ($templates) {
    return array_sum(array_map('floatval', array_column($templates, $key)));
};
$made_total = intval($sum('made'));
$previous = intval($sum('prev'));
$vendor_total = intval($sum('vendors'));
$out_total = intval($sum('out'));
$should_total = intval($sum('should'));
$have_total = intval($sum('have'));
$totals = ['template' => 'Total', 'made' => $made_total, 'vendors' => $vendor_total, 'out' => $out_total, 'no_account' => intval($sum('no_account')), 'should' => $should_total, 'have' => $have_total,
    'clients' => count(array_filter($by_client, function ($c) { return $c['vendors'] > 0; })), 'licenses' => intval($sum('licenses')), 'assets' => intval($sum('assets')),
    'cases' => intval($sum('cases')), 'open' => intval($sum('open'))];
if ($sees_spend) {
    $totals['spend'] = round($sum('spend'), 2);
}
$client_totals = ['client' => 'Total', 'vendors' => array_sum(array_column($by_client, 'vendors')), 'should' => array_sum(array_column($by_client, 'should')), 'have' => array_sum(array_column($by_client, 'have')),
    'out' => array_sum(array_column($by_client, 'out')), 'no_account' => array_sum(array_column($by_client, 'no_account')), 'open' => array_sum(array_column($by_client, 'open'))];

if (reportExporting('clients')) {
    reportCsv('Vendor Templates by Client', $client_columns, $by_client, $client_totals);
}
if (reportExporting()) {
    reportCsv('Vendor Templates ' . $period['start'] . ' to ' . $period['end'], $columns, $templates, $totals);
}

require_once "includes/inc_report_layout.php";

$top = array_slice(array_filter($templates, function ($t) { return $t['clients'] > 0; }), 0, 12);
$fully = count(array_filter($by_client, function ($c) { return $c['should'] && !$c['missing']; }));
$with_required = count(array_filter($by_client, function ($c) { return $c['should']; }));

$stats = [
    ['label' => 'Vendors added', 'value' => reportCount($made_total), 'sub' => $previous || $made_total ? reportChangeHtml($made_total, $previous, 'up', 'the period before') : '<span class="text-body-tertiary">from a template</span>'],
    ['label' => 'In step', 'value_html' => $vendor_total ? reportRateHtml(reportShare($vendor_total - $out_total, $vendor_total, 1), 95, 80) : '-',
        'sub' => $vendor_total ? ($out_total ? '<span class="text-warning-emphasis">' . reportCount($out_total) . ' out of step</span> <span class="text-body-tertiary">of ' . reportCount($vendor_total) . ' vendors</span>'
            : '<span class="text-body-tertiary">Every one of the ' . reportCount($vendor_total) . ' vendors</span>') : '<span class="text-body-tertiary">No vendors from a template yet</span>'],
    ['label' => 'Have what they should', 'value_html' => $should_total ? reportRateHtml(reportShare($have_total, $should_total, 1), 100, 75) : '-',
        'sub' => $should_total ? $fully . ' of ' . $with_required . ' <span class="text-body-tertiary">client' . ($with_required === 1 ? '' : 's') . ' have every one</span>' : '<span class="text-body-tertiary">No template is required of anyone</span>'],
];
if ($sees_spend) {
    $stats[] = ['label' => 'Spent with them', 'value' => reportMoney($totals['spend']), 'sub' => '<span class="text-body-tertiary">our own vendors from a template</span>'];
} elseif ($sees_cases) {
    $stats[] = ['label' => 'Open cases', 'value' => reportCount($totals['open']), 'tone' => $totals['open'] ? 'warning' : '', 'sub' => reportCount($totals['cases']) . ' <span class="text-body-tertiary">opened in the period</span>'];
}

?>

<div class="itflow-report">

    <?php reportHeader('vendor_templates', [
        'period' => $period,
        'years' => reportPastYears("SELECT DISTINCT YEAR(vendor_created_at) AS y FROM vendors WHERE vendor_template_id > 0"),
        'client' => $client_id,
        'export' => ['' => 'By template', 'clients' => 'By client'],
        'print_meta' => $client_id ? reportClientName($client_id) : '',
    ]); ?>

    <?= reportStats($stats) ?>

    <?php if ($top) { ?>
        <?= reportSectionOpen('Most used', 'Clients with a vendor from each') ?>
            <div class="card-body">
                <?= reportChart(['type' => 'bar', 'horizontal' => true, 'labels' => array_column($top, 'template'),
                    'series' => [['label' => 'In step', 'tone' => 'accent', 'data' => array_column($top, 'in_step')], ['label' => 'Out of step', 'tone' => 'warning', 'data' => array_column($top, 'out')]],
                    'stacked' => true, 'format' => 'count'], '', 'Vendors from each, in step and out of step') ?>
            </div>
        </div>
    <?php } ?>

    <?= reportSectionOpen('By template', 'Most clients first', 'itflow-report-long') ?>
        <?= reportTable($columns, $templates, ['empty' => 'There are no vendor templates yet.', 'totals' => $totals]) ?>
        <div class="card-footer small text-body-secondary">
            Added counts the client vendors made from each in the period; the rest count the current vendors from it, as they are now. Out of step is a vendor that says something other than its template
            in the fields every client shares - not its account number, PIN or account manager. Coverage is how many of the clients a template is required of have a current vendor from it.
            <?= $sees_docs ? 'Licenses and assets are the current ones naming a vendor from it.' : '' ?>
            <?= $sees_cases ? 'Cases are the tickets with a vendor from it on them, opened in the period.' : '' ?>
            <?= $sees_spend ? 'Spent is the expenses with our own vendor from it in the period, as Spend by Vendor counts them.' : '' ?>
        </div>
    </div>

    <?= reportSectionOpen('By client', 'Most missing first') ?>
        <?= reportTable($client_columns, $by_client, ['empty' => 'No client has a vendor from a template, or one it should have.', 'totals' => $client_totals]) ?>
    </div>

</div>

<?php require_once "../../includes/footer.php"; ?>
<script src="/js/reports.js"></script>
