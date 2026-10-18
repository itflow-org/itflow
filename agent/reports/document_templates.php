<?php

/*
 * Document Templates - for each template: how many documents were made from
 * it in the period, against the period before, how many current documents and
 * clients have one, and when the last was made; for one that every client (or
 * the clients with a tag) should have, how many of them do; and how many of
 * its documents are due a review. By client too - the templates each should
 * have a document from, the ones missing, and the documents due. One client,
 * or the documents one agent made, when picked; who should have one and the
 * reviews are as they are now. Use is counted from version 3.2.5 of the
 * database on.
 */

require_once "includes/inc_report.php";
reportEnforce('document_templates');

$period = reportPeriod('last_90');
$client_id = reportClientId();
$agent_id = reportAgentId();
$admin = !empty($session_is_admin);

// The documents counted: from a template, for the clients this user may see (the client picked), made by the agent picked
$clients_sql = reportClientSql('documents.document_client_id', $client_id);
$made = "documents.document_template_id > 0 $clients_sql" . ($agent_id ? " AND documents.document_created_by = $agent_id" : '');
$current = "documents.document_template_id > 0 AND documents.document_archived_at IS NULL $clients_sql AND documents.document_client_id IN (SELECT client_id FROM clients WHERE client_archived_at IS NULL)";

// Every current template, and archived ones used in the period
$templates = [];
$sql = mysqli_query($mysqli, "SELECT document_template_id, document_template_name, document_template_archived_at, document_template_required, document_template_review_months FROM document_templates
    WHERE document_template_archived_at IS NULL OR document_template_id IN (SELECT documents.document_template_id FROM documents WHERE $made AND " . reportRangeSql('documents.document_created_at', $period) . ")
    ORDER BY document_template_name ASC");
while ($row = mysqli_fetch_assoc($sql)) {
    $templates[intval($row['document_template_id'])] = ['id' => intval($row['document_template_id']), 'template' => $row['document_template_name'], 'archived' => !empty($row['document_template_archived_at']),
        'required' => $row['document_template_archived_at'] ? 0 : intval($row['document_template_required']), 'review_months' => intval($row['document_template_review_months']),
        'scope' => '', 'made' => 0, 'prev' => 0, 'documents' => 0, 'clients' => 0, 'last' => null, 'should' => null, 'have' => null, 'coverage' => null, 'due' => 0];
}
$required_tags = documentTemplateRequiredTags(array_keys($templates));

// Made in the period, and the period before
foreach (['made' => false, 'prev' => true] as $key => $previous) {
    $sql = mysqli_query($mysqli, "SELECT documents.document_template_id, COUNT(*) FROM documents WHERE $made AND " . reportRangeSql('documents.document_created_at', $period, $previous) . " GROUP BY documents.document_template_id");
    while ($row = mysqli_fetch_row($sql)) {
        if (isset($templates[intval($row[0])])) {
            $templates[intval($row[0])][$key] = intval($row[1]);
        }
    }
}
// The current documents from each, the clients with one, and when the last was made
$sql = mysqli_query($mysqli, "SELECT documents.document_template_id, COUNT(*), COUNT(DISTINCT documents.document_client_id), MAX(documents.document_created_at) FROM documents WHERE $current GROUP BY documents.document_template_id");
while ($row = mysqli_fetch_row($sql)) {
    if (isset($templates[intval($row[0])])) {
        $templates[intval($row[0])]['documents'] = intval($row[1]);
        $templates[intval($row[0])]['clients'] = intval($row[2]);
        $templates[intval($row[0])]['last'] = $row[3];
    }
}

// Who should have each - the clients this user may see (the client picked) - and which have one
$has_one = [];
$sql = mysqli_query($mysqli, "SELECT DISTINCT documents.document_template_id, documents.document_client_id FROM documents WHERE $current");
while ($row = mysqli_fetch_row($sql)) {
    $has_one[intval($row[0])][intval($row[1])] = true;
}
$should_have = []; // client id => [template id => name]
foreach ($templates as $id => &$template) {
    if (!$template['required']) {
        continue;
    }
    $template['scope'] = documentTemplateScopeText($template['required'], $required_tags[$id]);
    $template['should'] = $template['have'] = 0;
    $sql = mysqli_query($mysqli, "SELECT client_id FROM clients WHERE " . documentTemplateScopeSql($template['required'], array_keys($required_tags[$id]))
        . ($client_id ? " AND client_id = $client_id" : '') . clientScopeSql('clients.client_id'));
    while ($row = mysqli_fetch_row($sql)) {
        $template['should']++;
        $template['have'] += isset($has_one[$id][intval($row[0])]) ? 1 : 0;
        $should_have[intval($row[0])][$id] = $template['template'];
    }
    $template['coverage'] = $template['should'] ? reportShare($template['have'], $template['should'], 1) : null;
}
unset($template);

// The documents due a review, as things are now
$due = documentsReviewDue("1 = 1 $clients_sql");
foreach ($due as $row) {
    if (isset($templates[intval($row['document_template_id'])])) {
        $templates[intval($row['document_template_id'])]['due']++;
    }
}

foreach ($templates as &$row) {
    $row['change'] = reportChange($row['made'], $row['prev']);
}
unset($row);
uasort($templates, function ($a, $b) {
    return $b['made'] <=> $a['made'] ?: $b['documents'] <=> $a['documents'] ?: strcasecmp($a['template'], $b['template']);
});
$templates = array_values($templates);

// By client: the ones each should have, has and is missing, its documents from templates and the ones due
$by_client = [];
$sql = mysqli_query($mysqli, "SELECT client_id, client_name, client_abbreviation FROM clients WHERE client_archived_at IS NULL"
    . ($client_id ? " AND client_id = $client_id" : '') . clientScopeSql('clients.client_id') . " ORDER BY client_name ASC");
while ($row = mysqli_fetch_assoc($sql)) {
    $by_client[intval($row['client_id'])] = ['client_id' => intval($row['client_id']), 'client' => $row['client_name'], 'abbr' => (string) $row['client_abbreviation'],
        'should' => 0, 'have' => 0, 'missing' => [], 'documents' => 0, 'due' => 0, 'last' => null];
}
foreach ($should_have as $id => $should) {
    if (!isset($by_client[$id])) {
        continue;
    }
    $by_client[$id]['should'] = count($should);
    foreach ($should as $template_id => $name) {
        if (isset($has_one[$template_id][$id])) {
            $by_client[$id]['have']++;
        } else {
            $by_client[$id]['missing'][] = $name;
        }
    }
}
$sql = mysqli_query($mysqli, "SELECT documents.document_client_id, COUNT(*), MAX(documents.document_created_at) FROM documents WHERE $current GROUP BY documents.document_client_id");
while ($row = mysqli_fetch_row($sql)) {
    if (isset($by_client[intval($row[0])])) {
        $by_client[intval($row[0])]['documents'] = intval($row[1]);
        $by_client[intval($row[0])]['last'] = $row[2];
    }
}
foreach ($due as $row) {
    if (isset($by_client[intval($row['document_client_id'])])) {
        $by_client[intval($row['document_client_id'])]['due']++;
    }
}
// Only the clients something applies to - most missing first
$by_client = array_values(array_filter($by_client, function ($c) {
    return $c['should'] || $c['documents'] || $c['due'];
}));
usort($by_client, function ($a, $b) {
    return count($b['missing']) <=> count($a['missing']) ?: $b['due'] <=> $a['due'] ?: strcasecmp($a['client'], $b['client']);
});

$columns = [
    'template' => ['label' => 'Template', 'html' => function ($row) use ($admin) {
        $name = escapeHtml($row['template']);
        return ($admin ? '<a href="/admin/document_template.php?document_template_id=' . $row['id'] . '">' . $name . '</a>' : $name)
            . ($row['archived'] ? ' <span class="badge text-bg-light border fw-normal">Archived</span>' : '');
    }],
    'scope' => ['label' => 'Required of', 'html' => function ($row) {
        return $row['scope'] !== '' ? '<span class="small">' . escapeHtml($row['scope']) . '</span>' : '<span class="itflow-report-zero">–</span>';
    }],
    'made' => ['label' => 'Made', 'type' => 'count', 'class' => 'fw-semibold', 'title' => 'Documents made from it in the period'],
    'change' => ['label' => 'Change', 'type' => 'percent', 'csv' => false, 'html' => function ($row) {
        return $row['change'] === null ? '<span class="itflow-report-zero">–</span>' : '<span class="small">' . reportChangeHtml($row['made'], $row['prev'], 'up') . '</span>';
    }],
    'documents' => ['label' => 'Documents', 'type' => 'count', 'title' => 'Current documents from it'],
    'clients' => ['label' => 'Clients', 'type' => 'count', 'title' => 'Clients with one'],
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
    'due' => ['label' => 'Due a review', 'type' => 'count', 'html' => function ($row) {
        return $row['due'] ? '<span class="fw-semibold text-warning-emphasis">' . $row['due'] . '</span>' : ($row['review_months'] ? '<span class="itflow-report-zero">0</span>' : '<span class="itflow-report-zero">–</span>');
    }],
    'last' => ['label' => 'Last made', 'type' => 'date', 'html' => function ($row) {
        return $row['last'] ? '<span class="small" title="' . escapeHtml($row['last']) . '">' . timeAgo($row['last']) . '</span>' : '<span class="itflow-report-zero">Never</span>';
    }],
];
$client_columns = [
    'client' => ['label' => 'Client', 'html' => function ($row) {
        return reportClientCell($row['client_id'], $row['client'], $row['abbr'], 'files.php');
    }],
    'should' => ['label' => 'Should have', 'type' => 'count'],
    'have' => ['label' => 'Have', 'type' => 'count'],
    'missing' => ['label' => 'Missing', 'sort' => false, 'class' => 'itflow-report-text', 'csv' => function ($row) {
        return implode(', ', $row['missing']);
    }, 'html' => function ($row) {
        return $row['missing'] ? '<span class="small text-warning-emphasis">' . escapeHtml(implode(', ', $row['missing'])) . '</span>' : ($row['should'] ? '<span class="small text-success"><i class="fas fa-check me-1"></i>None</span>' : '');
    }],
    'documents' => ['label' => 'Documents', 'type' => 'count', 'title' => 'Current documents from a template'],
    'due' => ['label' => 'Due a review', 'type' => 'count', 'html' => function ($row) {
        return $row['due'] ? '<a class="fw-semibold text-warning-emphasis" href="/agent/files.php?client_id=' . $row['client_id'] . '&amp;type=due">' . $row['due'] . '</a>' : '<span class="itflow-report-zero">0</span>';
    }],
    'last' => ['label' => 'Last made', 'type' => 'date', 'html' => function ($row) {
        return $row['last'] ? '<span class="small" title="' . escapeHtml($row['last']) . '">' . timeAgo($row['last']) . '</span>' : '<span class="itflow-report-zero">Never</span>';
    }],
];

$made_total = array_sum(array_column($templates, 'made'));
$previous = array_sum(array_column($templates, 'prev'));
$should_total = array_sum(array_map('intval', array_column($templates, 'should')));
$have_total = array_sum(array_map('intval', array_column($templates, 'have')));
$totals = ['template' => 'Total', 'made' => $made_total, 'documents' => array_sum(array_column($templates, 'documents')), 'should' => $should_total, 'have' => $have_total, 'due' => count($due)];
$client_totals = ['client' => 'Total', 'should' => array_sum(array_column($by_client, 'should')), 'have' => array_sum(array_column($by_client, 'have')),
    'documents' => array_sum(array_column($by_client, 'documents')), 'due' => array_sum(array_column($by_client, 'due'))];

if (reportExporting('clients')) {
    reportCsv('Document Templates by Client', $client_columns, $by_client, $client_totals);
}
if (reportExporting()) {
    reportCsv('Document Templates ' . $period['start'] . ' to ' . $period['end'], $columns, $templates, $totals);
}

require_once "includes/inc_report_layout.php";

$live = array_filter($templates, function ($t) { return !$t['archived']; });
$used = count(array_filter($live, function ($t) { return $t['made'] > 0; }));
$top = array_slice(array_filter($templates, function ($t) { return $t['made'] > 0; }), 0, 12);
$fully = count(array_filter($by_client, function ($c) { return $c['should'] && !$c['missing']; }));
$with_required = count(array_filter($by_client, function ($c) { return $c['should']; }));
$oldest = $due ? reset($due) : null;

?>

<div class="itflow-report">

    <?php reportHeader('document_templates', [
        'period' => $period,
        'years' => reportPastYears("SELECT DISTINCT YEAR(document_created_at) AS y FROM documents WHERE document_template_id > 0"),
        'client' => $client_id,
        'agent' => $agent_id,
        'export' => ['' => 'By template', 'clients' => 'By client'],
        'print_meta' => $client_id ? reportClientName($client_id) : '',
    ]); ?>

    <?= reportStats([
        ['label' => 'Documents made', 'value' => reportCount($made_total), 'sub' => $previous || $made_total ? reportChangeHtml($made_total, $previous, 'up', 'the period before') : ''],
        ['label' => 'Templates used', 'value' => $used . ' of ' . count($live), 'sub' => count($live) - $used ? (count($live) - $used) . ' <span class="text-body-tertiary">not used in this period</span>' : '<span class="text-body-tertiary">Every one</span>'],
        ['label' => 'Have what they should', 'value_html' => $should_total ? reportRateHtml(reportShare($have_total, $should_total, 1), 100, 75) : '-',
            'sub' => $should_total ? $fully . ' of ' . $with_required . ' <span class="text-body-tertiary">client' . ($with_required === 1 ? '' : 's') . ' have every one</span>' : '<span class="text-body-tertiary">No template is required of anyone</span>'],
        ['label' => 'Due a review', 'value' => reportCount(count($due)), 'tone' => $due ? 'warning' : '',
            'sub' => $oldest ? '<span class="text-body-tertiary">the oldest last looked at</span> ' . timeAgo($oldest['reviewed_at']) : ''],
    ]) ?>

    <?php if ($top) { ?>
        <?= reportSectionOpen('Most used', escapeHtml($period['range'])) ?>
            <div class="card-body">
                <?= reportChart(['type' => 'bar', 'horizontal' => true, 'labels' => array_column($top, 'template'),
                    'series' => [['label' => 'Documents made', 'tone' => 'accent', 'data' => array_column($top, 'made')]], 'format' => 'count'], '', 'Documents made from each') ?>
            </div>
        </div>
    <?php } ?>

    <?= reportSectionOpen('By template', 'Most used first, then the ones not used', 'itflow-report-long') ?>
        <?= reportTable($columns, $templates, ['empty' => 'There are no document templates yet.', 'totals' => $totals]) ?>
        <div class="card-footer small text-body-secondary">
            Made counts the documents made from each in the period<?= $agent_id ? ' by the agent picked' : '' ?>; documents and clients count the current ones. Coverage is how many of the clients a template is required of
            have a current document from it, and due a review is the documents its review period has passed for - both as they are now. Use is counted from the update that brought document templates' record of use in.
        </div>
    </div>

    <?= reportSectionOpen('By client', 'Most missing first') ?>
        <?= reportTable($client_columns, $by_client, ['empty' => 'No client has a document from a template, or one it should have.', 'totals' => $client_totals]) ?>
    </div>

</div>

<?php require_once "../../includes/footer.php"; ?>
<script src="/js/reports.js"></script>
