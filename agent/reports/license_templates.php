<?php

/*
 * License Templates - for each template: how many licenses were made from it
 * in the period, against the period before; the current licenses from it and
 * the clients with one, their seats in use against bought - how many over,
 * how many spare - the ones renewing in the next 30 days and the ones past
 * their renewal date; for one that every client (or the clients with a tag)
 * should have, how many of them do; and, for someone who sees Sales, how many
 * of its licenses are billed on their clients' recurring invoices and what
 * that comes to a month. By client too - the templates each should have a
 * license from, the ones missing, its licenses over seats and the ones not
 * billed. One client when picked; everything but what was made is as it is
 * now. Use is counted from version 3.2.8 of the database on.
 */

require_once "includes/inc_report.php";
reportEnforce('license_templates');

$period = reportPeriod('last_90');
$client_id = reportClientId();
$admin = !empty($session_is_admin);
$sees_billing = lookupUserPermission('module_sales') >= 1;

// The licenses counted: from a template, for the clients this user may see (the client picked)
$clients_sql = reportClientSql('software.software_client_id', $client_id);
$made = "software.software_template_id > 0 $clients_sql";

// The current clients this user may see (the client picked)
$client_rows = [];
$sql = mysqli_query($mysqli, "SELECT client_id, client_name, client_abbreviation FROM clients WHERE client_archived_at IS NULL"
    . ($client_id ? " AND client_id = $client_id" : '') . clientScopeSql('clients.client_id') . " ORDER BY client_name ASC");
while ($row = mysqli_fetch_assoc($sql)) {
    $client_rows[intval($row['client_id'])] = $row;
}

// Every current template, and archived ones used in the period
$templates = [];
$sql = mysqli_query($mysqli, "SELECT software_template_id, software_template_name, software_template_archived_at, software_template_required, software_template_product_id, product_name
    FROM software_templates LEFT JOIN products ON product_id = software_template_product_id AND software_template_product_id > 0
    WHERE software_template_archived_at IS NULL OR software_template_id IN (SELECT software.software_template_id FROM software WHERE $made AND " . reportRangeSql('software.software_created_at', $period) . ")
    ORDER BY software_template_name ASC");
while ($row = mysqli_fetch_assoc($sql)) {
    $templates[intval($row['software_template_id'])] = ['id' => intval($row['software_template_id']), 'template' => $row['software_template_name'], 'archived' => !empty($row['software_template_archived_at']),
        'required' => $row['software_template_archived_at'] ? 0 : intval($row['software_template_required']), 'product_id' => intval($row['software_template_product_id']), 'product' => $row['product_name'],
        'scope' => '', 'made' => 0, 'prev' => 0, 'licenses' => 0, 'clients' => 0, 'seats' => 0, 'used' => 0, 'over' => 0, 'spare' => 0, 'renewing' => 0, 'expired' => 0,
        'should' => null, 'have' => null, 'coverage' => null, 'billed' => null, 'unbilled' => null, 'monthly' => null];
}
$required_tags = softwareTemplateRequiredTags(array_keys($templates));

// Made in the period, and the period before
foreach (['made' => false, 'prev' => true] as $key => $previous) {
    $sql = mysqli_query($mysqli, "SELECT software.software_template_id, COUNT(*) FROM software WHERE $made AND " . reportRangeSql('software.software_created_at', $period, $previous) . " GROUP BY software.software_template_id");
    while ($row = mysqli_fetch_row($sql)) {
        if (isset($templates[intval($row[0])])) {
            $templates[intval($row[0])][$key] = intval($row[1]);
        }
    }
}

// Each one's current licenses, for the clients counted - their seats, renewals and how they are billed
$by_client = [];
foreach ($client_rows as $id => $row) {
    $by_client[$id] = ['client_id' => $id, 'client' => $row['client_name'], 'abbr' => (string) $row['client_abbreviation'],
        'licenses' => 0, 'should' => 0, 'have' => 0, 'missing' => [], 'over' => 0, 'unbilled' => 0, 'renewing' => 0, 'expired' => 0];
}
$billed_items = [];
$has_one = [];
foreach ($templates as $id => &$template) {
    $licenses = array_filter(softwareTemplateLicenses($id, $template['product_id']), function ($row) use ($client_rows) {
        return isset($client_rows[intval($row['software_client_id'])]);
    });
    $clients = [];
    foreach ($licenses as $software_id => $row) {
        $cid = intval($row['software_client_id']);
        $clients[$cid] = true;
        $has_one[$id][$cid] = true;
        $seats = $row['seats'];
        $template['licenses']++;
        $template['seats'] += $seats['total'];
        $template['used'] += $seats['used'];
        $template['over'] += $seats['over'];
        $template['spare'] += $seats['total'] > 0 ? $seats['free'] : 0;
        $renewing = $row['renewal'] && $row['renewal']['state'] === 'soon';
        $expired = $row['renewal'] && $row['renewal']['state'] === 'expired';
        $template['renewing'] += $renewing ? 1 : 0;
        $template['expired'] += $expired ? 1 : 0;
        $by_client[$cid]['licenses']++;
        $by_client[$cid]['over'] += $seats['state'] === 'over' ? 1 : 0;
        $by_client[$cid]['renewing'] += $renewing ? 1 : 0;
        $by_client[$cid]['expired'] += $expired ? 1 : 0;
        if ($template['product'] !== null) {
            if ($row['billing']) {
                $billed_items[$row['billing']['item_id']][] = $id;
            } else {
                $by_client[$cid]['unbilled']++;
            }
        }
    }
    $template['clients'] = count($clients);
    if ($template['product'] !== null) {
        $template['billed'] = count(array_filter($licenses, function ($row) {
            return !empty($row['billing']);
        }));
        $template['unbilled'] = $template['licenses'] - $template['billed'];
        $template['monthly'] = 0.0;
    }
}
unset($template);

// What the lines billing them come to a month - each line once, on a recurring invoice that is on
if ($billed_items) {
    $sql = mysqli_query($mysqli, "SELECT item_id, item_subtotal, recurring_invoice_frequency FROM recurring_invoice_items
        JOIN recurring_invoices ON recurring_invoice_id = item_recurring_invoice_id AND recurring_invoice_status = 1
        WHERE item_id IN (" . implode(',', array_map('intval', array_keys($billed_items))) . ")");
    while ($row = mysqli_fetch_assoc($sql)) {
        $template_id = $billed_items[intval($row['item_id'])][0];
        $templates[$template_id]['monthly'] += floatval($row['item_subtotal']) / recurringInvoiceMonths($row['recurring_invoice_frequency']);
    }
}

// Who should have each - and which have one
foreach ($templates as $id => &$template) {
    if (!$template['required']) {
        continue;
    }
    $template['scope'] = softwareTemplateScopeText($template['required'], $required_tags[$id]);
    $template['should'] = $template['have'] = 0;
    $sql = mysqli_query($mysqli, "SELECT client_id FROM clients WHERE " . softwareTemplateScopeSql($template['required'], array_keys($required_tags[$id]))
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
    if ($row['monthly'] !== null) {
        $row['monthly'] = round($row['monthly'], 2);
    }
}
unset($row);
uasort($templates, function ($a, $b) {
    return $b['licenses'] <=> $a['licenses'] ?: $b['made'] <=> $a['made'] ?: strcasecmp($a['template'], $b['template']);
});
$templates = array_values($templates);

// Only the clients something applies to - most missing first
$by_client = array_values(array_filter($by_client, function ($c) {
    return $c['should'] || $c['licenses'];
}));
usort($by_client, function ($a, $b) {
    return count($b['missing']) <=> count($a['missing']) ?: $b['unbilled'] <=> $a['unbilled'] ?: $b['over'] <=> $a['over'] ?: strcasecmp($a['client'], $b['client']);
});

$columns = [
    'template' => ['label' => 'Template', 'html' => function ($row) use ($admin) {
        $name = escapeHtml($row['template']);
        return ($admin ? '<a href="/admin/software_template.php?software_template_id=' . $row['id'] . '">' . $name . '</a>' : $name)
            . ($row['archived'] ? ' <span class="badge text-bg-light border fw-normal">Archived</span>' : '');
    }],
    'scope' => ['label' => 'Required of', 'html' => function ($row) {
        return $row['scope'] !== '' ? '<span class="small">' . escapeHtml($row['scope']) . '</span>' : '<span class="itflow-report-zero">–</span>';
    }],
    'made' => ['label' => 'Made', 'type' => 'count', 'title' => 'Licenses made from it in the period'],
    'change' => ['label' => 'Change', 'type' => 'percent', 'csv' => false, 'html' => function ($row) {
        return $row['change'] === null ? '<span class="itflow-report-zero">–</span>' : '<span class="small">' . reportChangeHtml($row['made'], $row['prev'], 'up') . '</span>';
    }],
    'licenses' => ['label' => 'Licenses', 'type' => 'count', 'class' => 'fw-semibold', 'title' => 'Current licenses from it'],
    'clients' => ['label' => 'Clients', 'type' => 'count', 'title' => 'Clients with one'],
    'seats' => ['label' => 'Seats bought', 'type' => 'count', 'screen' => false],
    'used' => ['label' => 'Seats in use', 'type' => 'count', 'title' => 'Devices and users on them, of the seats bought', 'html' => function ($row) {
        return $row['licenses'] ? '<span class="text-nowrap">' . reportCount($row['used']) . ($row['seats'] ? ' <span class="text-body-secondary">of ' . reportCount($row['seats']) . '</span>' : '') . '</span>' : '<span class="itflow-report-zero">0</span>';
    }],
    'over' => ['label' => 'Over', 'type' => 'count', 'title' => 'Seats in use beyond the ones bought - a true-up to bill', 'html' => function ($row) {
        return $row['over'] ? '<span class="fw-semibold text-danger-emphasis">' . reportCount($row['over']) . '</span>' : '<span class="itflow-report-zero">0</span>';
    }],
    'spare' => ['label' => 'Spare', 'type' => 'count', 'screen' => false],
    'renewing' => ['label' => 'Renewing', 'type' => 'count', 'title' => 'In the next 30 days', 'html' => function ($row) {
        return $row['renewing'] ? '<span class="fw-semibold text-warning-emphasis">' . $row['renewing'] . '</span>' : '<span class="itflow-report-zero">0</span>';
    }],
    'expired' => ['label' => 'Expired', 'type' => 'count', 'title' => 'Past their renewal date', 'html' => function ($row) {
        return $row['expired'] ? '<span class="fw-semibold text-danger-emphasis">' . $row['expired'] . '</span>' : '<span class="itflow-report-zero">0</span>';
    }],
    'should' => ['label' => 'Should have one', 'type' => 'count', 'screen' => false],
    'have' => ['label' => 'Have one', 'type' => 'count', 'screen' => false],
    'coverage' => ['label' => 'Coverage', 'type' => 'percent', 'title' => 'Of the clients that should have one, how many do', 'html' => function ($row) {
        if ($row['coverage'] === null) {
            return '<span class="itflow-report-zero">–</span>';
        }
        $tone = $row['coverage'] >= 100 ? 'success' : ($row['coverage'] >= 75 ? 'warning' : 'danger');
        return '<div class="d-flex align-items-center justify-content-end gap-2"><div class="progress itflow-report-meter" style="width: 4rem" role="presentation">'
            . '<div class="progress-bar bg-' . $tone . '" style="width: ' . round($row['coverage']) . '%"></div></div>'
            . '<span class="small text-nowrap" title="' . $row['have'] . ' of ' . $row['should'] . ' clients">' . $row['have'] . ' of ' . $row['should'] . '</span></div>';
    }],
];
if ($sees_billing) {
    $columns['billed'] = ['label' => 'Billed', 'type' => 'count', 'title' => "Licenses from it on their client's recurring invoice - when it is sold as a product", 'html' => function ($row) {
        if ($row['billed'] === null) {
            return '<span class="itflow-report-zero" title="Not sold as a product">–</span>';
        }
        return '<span class="text-nowrap">' . $row['billed'] . ' <span class="text-body-secondary">of ' . $row['licenses'] . '</span></span>'
            . ($row['unbilled'] ? '<div class="small text-warning-emphasis">' . $row['unbilled'] . ' not billed</div>' : '');
    }];
    $columns['unbilled'] = ['label' => 'Not billed', 'type' => 'count', 'screen' => false];
    $columns['monthly'] = ['label' => 'A month', 'type' => 'money', 'title' => "What the lines billing them come to a month, on recurring invoices that are on"];
}

$client_columns = [
    'client' => ['label' => 'Client', 'html' => function ($row) {
        return reportClientCell($row['client_id'], $row['client'], $row['abbr'], 'software.php');
    }],
    'licenses' => ['label' => 'Licenses', 'type' => 'count', 'title' => 'Current licenses from a template'],
    'should' => ['label' => 'Should have', 'type' => 'count'],
    'have' => ['label' => 'Have', 'type' => 'count'],
    'missing' => ['label' => 'Missing', 'sort' => false, 'class' => 'itflow-report-text', 'csv' => function ($row) {
        return implode(', ', $row['missing']);
    }, 'html' => function ($row) {
        return $row['missing'] ? '<span class="small text-warning-emphasis">' . escapeHtml(implode(', ', $row['missing'])) . '</span>' : ($row['should'] ? '<span class="small text-success"><i class="fas fa-check me-1"></i>None</span>' : '');
    }],
    'over' => ['label' => 'Over seats', 'type' => 'count', 'title' => 'Licenses with more on them than seats bought', 'html' => function ($row) {
        return $row['over'] ? '<a class="fw-semibold text-danger-emphasis" href="/agent/software.php?client_id=' . $row['client_id'] . '&amp;status=over">' . $row['over'] . '</a>' : '<span class="itflow-report-zero">0</span>';
    }],
    'renewing' => ['label' => 'Renewing', 'type' => 'count', 'title' => 'In the next 30 days'],
    'expired' => ['label' => 'Expired', 'type' => 'count'],
];
if ($sees_billing) {
    $client_columns['unbilled'] = ['label' => 'Not billed', 'type' => 'count', 'title' => 'Licenses from a template sold as a product, not on their recurring invoice', 'html' => function ($row) {
        return $row['unbilled'] ? '<span class="fw-semibold text-warning-emphasis">' . $row['unbilled'] . '</span>' : '<span class="itflow-report-zero">0</span>';
    }];
}

$sum = function ($key) use ($templates) {
    return array_sum(array_map('floatval', array_column($templates, $key)));
};
$made_total = intval($sum('made'));
$previous = intval($sum('prev'));
$should_total = intval($sum('should'));
$have_total = intval($sum('have'));
$totals = ['template' => 'Total', 'made' => $made_total, 'licenses' => intval($sum('licenses')), 'seats' => intval($sum('seats')), 'used' => intval($sum('used')), 'over' => intval($sum('over')),
    'spare' => intval($sum('spare')), 'renewing' => intval($sum('renewing')), 'expired' => intval($sum('expired')), 'should' => $should_total, 'have' => $have_total];
if ($sees_billing) {
    $totals += ['billed' => intval($sum('billed')), 'unbilled' => intval($sum('unbilled')), 'monthly' => round($sum('monthly'), 2)];
}
$client_totals = ['client' => 'Total', 'licenses' => array_sum(array_column($by_client, 'licenses')), 'should' => array_sum(array_column($by_client, 'should')), 'have' => array_sum(array_column($by_client, 'have')),
    'over' => array_sum(array_column($by_client, 'over')), 'renewing' => array_sum(array_column($by_client, 'renewing')), 'expired' => array_sum(array_column($by_client, 'expired')),
    'unbilled' => array_sum(array_column($by_client, 'unbilled'))];

if (reportExporting('clients')) {
    reportCsv('License Templates by Client', $client_columns, $by_client, $client_totals);
}
if (reportExporting()) {
    reportCsv('License Templates ' . $period['start'] . ' to ' . $period['end'], $columns, $templates, $totals);
}

require_once "includes/inc_report_layout.php";

$top = array_slice(array_filter($templates, function ($t) { return $t['licenses'] > 0; }), 0, 12);
$fully = count(array_filter($by_client, function ($c) { return $c['should'] && !$c['missing']; }));
$with_required = count(array_filter($by_client, function ($c) { return $c['should']; }));
$over_licenses = array_sum(array_column($by_client, 'over'));

$stats = [
    ['label' => 'Licenses made', 'value' => reportCount($made_total), 'sub' => $previous || $made_total ? reportChangeHtml($made_total, $previous, 'up', 'the period before') : ''],
    ['label' => 'Seats in use', 'value' => reportCount($totals['used']), 'tone' => $totals['over'] ? 'danger' : '',
        'sub' => ($totals['seats'] ? 'of ' . reportCount($totals['seats']) . ' <span class="text-body-tertiary">bought</span>' : '<span class="text-body-tertiary">No seats bought</span>')
            . ($totals['over'] ? ' · <span class="text-danger-emphasis">' . reportCount($totals['over']) . ' over</span> <span class="text-body-tertiary">on ' . $over_licenses . ' license' . ($over_licenses === 1 ? '' : 's') . '</span>' : '')],
    ['label' => 'Have what they should', 'value_html' => $should_total ? reportRateHtml(reportShare($have_total, $should_total, 1), 100, 75) : '-',
        'sub' => $should_total ? $fully . ' of ' . $with_required . ' <span class="text-body-tertiary">client' . ($with_required === 1 ? '' : 's') . ' have every one</span>' : '<span class="text-body-tertiary">No template is required of anyone</span>'],
];
if ($sees_billing) {
    $stats[] = ['label' => 'Billed a month', 'value' => reportMoney($totals['monthly']), 'tone' => $totals['unbilled'] ? 'warning' : '',
        'sub' => $totals['unbilled'] ? '<span class="text-warning-emphasis">' . $totals['unbilled'] . ' not billed</span> <span class="text-body-tertiary">of the licenses sold as a product</span>' : '<span class="text-body-tertiary">Every license sold as a product is billed</span>'];
} else {
    $stats[] = ['label' => 'Renewing soon', 'value' => reportCount($totals['renewing']), 'tone' => $totals['expired'] ? 'danger' : ($totals['renewing'] ? 'warning' : ''),
        'sub' => $totals['expired'] ? '<span class="text-danger-emphasis">' . $totals['expired'] . ' expired</span>' : '<span class="text-body-tertiary">in the next 30 days</span>'];
}

?>

<div class="itflow-report">

    <?php reportHeader('license_templates', [
        'period' => $period,
        'years' => reportPastYears("SELECT DISTINCT YEAR(software_created_at) AS y FROM software WHERE software_template_id > 0"),
        'client' => $client_id,
        'export' => ['' => 'By template', 'clients' => 'By client'],
        'print_meta' => $client_id ? reportClientName($client_id) : '',
    ]); ?>

    <?= reportStats($stats) ?>

    <?php if ($top) { ?>
        <?= reportSectionOpen('Most used', 'Current licenses from each') ?>
            <div class="card-body">
                <?= reportChart(['type' => 'bar', 'horizontal' => true, 'labels' => array_column($top, 'template'),
                    'series' => [['label' => 'Licenses', 'tone' => 'accent', 'data' => array_column($top, 'licenses')]], 'format' => 'count'], '', 'Current licenses from each') ?>
            </div>
        </div>
    <?php } ?>

    <?= reportSectionOpen('By template', 'Most used first', 'itflow-report-long') ?>
        <?= reportTable($columns, $templates, ['empty' => 'There are no license templates yet.', 'totals' => $totals]) ?>
        <div class="card-footer small text-body-secondary">
            Made counts the licenses made from each in the period; the rest count the current licenses from it, as they are now. Coverage is how many of the clients a template is required of
            have a current license from it.<?= $sees_billing ? ' Billed counts the licenses on a line of their client\'s recurring invoice - counting their seats, or for the product typed in by hand - and a month is what those lines come to, on recurring invoices that are on.' : '' ?>
            Use is counted from the update that brought license templates' record of use in.
        </div>
    </div>

    <?= reportSectionOpen('By client', 'Most missing first') ?>
        <?= reportTable($client_columns, $by_client, ['empty' => 'No client has a license from a template, or one it should have.', 'totals' => $client_totals]) ?>
    </div>

</div>

<?php require_once "../../includes/footer.php"; ?>
<script src="/js/reports.js"></script>
