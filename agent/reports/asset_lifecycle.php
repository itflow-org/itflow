<?php

/*
 * Asset Lifecycle - the hardware still in use (not archived, lost, stolen or
 * retired): how old it is, where its warranty stands and which operating
 * systems are past their end of support. Then what comes due for
 * replacement over the next two years, by type and by client, and the
 * replacement candidates one by one - for planning budgets and refresh
 * projects with clients. Age runs from the purchase date, else the install
 * date.
 */

require_once "includes/inc_report.php";
reportEnforce('asset_lifecycle');

$year_choices = [3 => 'Replace after 3 years', 4 => 'Replace after 4 years', 5 => 'Replace after 5 years', 6 => 'Replace after 6 years', 7 => 'Replace after 7 years'];
$years = isset($year_choices[intval($_GET['years'] ?? 0)]) ? intval($_GET['years']) : 5;
$limit_days = $years * 365.25;
$client_id = reportClientId();
$type = trim((string) ($_GET['type'] ?? ''));

$assets = reportLifecycleAssets($client_id, $type);

$empty = ['assets' => 0, 'dated' => 0, 'age_total' => 0, 'old' => 0, 'undated' => 0, 'expired' => 0, 'ending' => 0, 'os' => 0];
$totals = $empty;
$by_type = [];
$by_client = [];
$candidates = [];
$os_names = [];
foreach ($assets as &$asset) {
    $hardware = $asset['asset_type'] !== 'Virtual Machine';
    $asset['old'] = $asset['age_days'] !== null && $asset['age_days'] >= $limit_days;
    $asset['expired'] = $asset['warranty'] && $asset['warranty']['days'] < 0;
    $asset['ending'] = $asset['warranty'] && $asset['warranty']['days'] >= 0 && $asset['warranty']['days'] <= 90;
    $t = $asset['asset_type'] ?: 'Other';
    $c = intval($asset['asset_client_id']);
    $by_type[$t] = $by_type[$t] ?? ['type' => $t] + $empty;
    $by_client[$c] = $by_client[$c] ?? ['client_id' => $c, 'client' => $asset['client_name'], 'abbr' => $asset['client_abbreviation']] + $empty;
    foreach ([&$totals, &$by_type[$t], &$by_client[$c]] as &$group) {
        $group['assets']++;
        if ($asset['age_days'] !== null) {
            $group['dated']++;
            $group['age_total'] += $asset['age_days'];
        } elseif ($hardware) {
            $group['undated']++;
        }
        $group['old'] += $asset['old'] ? 1 : 0;
        $group['expired'] += $asset['expired'] ? 1 : 0;
        $group['ending'] += $asset['ending'] ? 1 : 0;
        $group['os'] += $asset['os_eol'] !== '' ? 1 : 0;
    }
    unset($group);
    if ($asset['os_eol'] !== '') {
        $os_names[$asset['os_eol']] = ($os_names[$asset['os_eol']] ?? 0) + 1;
    }
    if ($asset['old'] || $asset['os_eol'] !== '') {
        $candidates[] = $asset;
    }
}
unset($asset);
$average = function ($group) {
    return $group['dated'] ? $group['age_total'] / $group['dated'] : null;
};
foreach ([&$by_type, &$by_client] as &$groups) {
    foreach ($groups as &$group) {
        $group['average'] = $average($group);
    }
    unset($group);
}
unset($groups);
$totals['average'] = $average($totals);
uasort($by_type, function ($a, $b) { return $b['assets'] <=> $a['assets']; });
uasort($by_client, function ($a, $b) { return $b['old'] + $b['os'] <=> $a['old'] + $a['os'] ?: $b['assets'] <=> $a['assets']; });
usort($candidates, function ($a, $b) { return ($b['age_days'] ?? -1) <=> ($a['age_days'] ?? -1); });
arsort($os_names);

$count_cell = function ($key, $tone) {
    return function ($row) use ($key, $tone) {
        return $row[$key] ? '<span class="text-' . $tone . '-emphasis fw-semibold">' . reportCount($row[$key]) . '</span>' : '<span class="itflow-report-zero">–</span>';
    };
};
$figure_columns = [
    'assets' => ['label' => 'In use', 'type' => 'count'],
    'average' => ['label' => 'Average age', 'type' => 'decimal', 'csv_label' => 'Average age (years)', 'csv' => function ($row) {
        return $row['average'] === null ? '' : number_format($row['average'] / 365.25, 1, '.', '');
    }, 'html' => function ($row) {
        return $row['average'] === null ? '<span class="itflow-report-zero">–</span>' : reportYears($row['average']);
    }],
    'old' => ['label' => "Over $years years", 'type' => 'count', 'html' => $count_cell('old', 'danger')],
    'expired' => ['label' => 'Out of warranty', 'type' => 'count', 'html' => $count_cell('expired', 'danger')],
    'ending' => ['label' => 'Ends in 90 days', 'type' => 'count', 'title' => 'The warranty ends in the next 90 days', 'html' => $count_cell('ending', 'warning')],
    'os' => ['label' => 'Unsupported OS', 'type' => 'count', 'html' => $count_cell('os', 'danger')],
    'undated' => ['label' => 'No date', 'type' => 'count', 'title' => 'Hardware with neither a purchase nor an install date', 'html' => function ($row) {
        return $row['undated'] ? '<span class="text-body-secondary">' . reportCount($row['undated']) . '</span>' : '<span class="itflow-report-zero">–</span>';
    }],
];
$type_columns = ['type' => ['label' => 'Type', 'html' => function ($row) {
    return '<span class="d-inline-flex align-items-center gap-2"><span class="itflow-asset-icon itflow-asset-icon-sm itflow-asset-tone-' . assetTypeTone($row['type']) . '" aria-hidden="true"><i class="fas fa-fw fa-' . getAssetIcon($row['type']) . '"></i></span>'
        . '<a class="text-body fw-semibold text-nowrap" href="' . escapeHtml(reportUrl(['type' => $row['type'], 'export' => null, 'table' => null])) . '">' . escapeHtml($row['type']) . '</a></span>';
}]] + $figure_columns;
$client_columns = ['client' => ['label' => 'Client', 'html' => function ($row) {
    return reportClientCell($row['client_id'], $row['client'], $row['abbr'], 'assets.php');
}]] + $figure_columns;

$warranty_pill = function ($warranty) {
    if (!$warranty) {
        return '<span class="text-body-tertiary small">No date</span>';
    }
    $tone = $warranty['tone'];
    return '<span class="badge rounded-pill bg-' . $tone . '-subtle text-' . $tone . '-emphasis border border-' . $tone . '-subtle fw-normal text-nowrap" title="' . escapeHtml($warranty['text']) . '">'
        . escapeHtml($warranty['short']) . '</span>';
};
$candidate_columns = [
    'asset_name' => ['label' => 'Asset', 'html' => function ($row) { return reportAssetCell($row); }],
    'client_name' => ['label' => 'Client', 'html' => function ($row) {
        return reportClientCell($row['asset_client_id'], $row['client_name'], $row['client_abbreviation'], 'assets.php');
    }],
    'asset_type' => ['label' => 'Type'],
    'model' => ['label' => 'Make and model', 'csv' => function ($row) { return trim($row['asset_make'] . ' ' . $row['asset_model']); }, 'html' => function ($row) {
        return '<span class="text-nowrap">' . escapeHtml(trim($row['asset_make'] . ' ' . $row['asset_model'])) . '</span>';
    }],
    'asset_os' => ['label' => 'Operating system', 'html' => function ($row) {
        if ($row['os_eol'] === '') {
            return '<span class="text-nowrap">' . escapeHtml($row['asset_os']) . '</span>';
        }
        return '<span class="text-danger-emphasis fw-semibold text-nowrap" title="Past its end of support">' . escapeHtml($row['asset_os']) . ' <i class="fas fa-fw fa-exclamation-circle small"></i></span>';
    }],
    'age_days' => ['label' => 'Age', 'type' => 'decimal', 'csv_label' => 'Age (years)', 'csv' => function ($row) {
        return $row['age_days'] === null ? '' : number_format($row['age_days'] / 365.25, 1, '.', '');
    }, 'html' => function ($row) {
        return $row['age_days'] === null ? '<span class="itflow-report-zero">–</span>'
            : '<span class="' . ($row['old'] ? 'text-danger-emphasis fw-semibold' : '') . '" title="Since ' . escapeHtml($row['age_date']) . '">' . reportYears($row['age_days']) . '</span>';
    }],
    'warranty' => ['label' => 'Warranty', 'sort' => false, 'csv' => function ($row) { return $row['warranty'] ? $row['warranty']['date'] : ''; }, 'csv_label' => 'Warranty ends',
        'html' => function ($row) use ($warranty_pill) { return $warranty_pill($row['warranty']); }],
    'contact_name' => ['label' => 'Used by', 'html' => function ($row) {
        return $row['contact_name'] ? '<span class="text-nowrap">' . escapeHtml($row['contact_name']) . '</span>' : '<span class="text-body-tertiary">-</span>';
    }],
    'location_name' => ['label' => 'Location', 'screen' => false],
    'asset_status' => ['label' => 'Status', 'screen' => false],
];

if (reportExporting('types')) {
    reportCsv('Assets by Type', $type_columns, array_values($by_type), ['type' => 'Total'] + $totals);
}
if (reportExporting('clients')) {
    reportCsv('Assets by Client', $client_columns, array_values($by_client), ['client' => 'Total'] + $totals);
}
if (reportExporting()) {
    reportCsv('Replacement Candidates', $candidate_columns, $candidates);
}

require_once "includes/inc_report_layout.php";

// The age profile - a bar a year, red from the replacement age
$age_labels = [];
$age_counts = [];
$age_tones = [];
$top = max($years + 2, 8);
for ($y = 0; $y < $top; $y++) {
    $age_labels[] = $y === $top - 1 ? "$y+ yrs" : "$y-" . ($y + 1) . ' yrs';
    $age_counts[$y] = 0;
    $age_tones[] = $y >= $years ? 'danger' : ($y === $years - 1 ? 'warning' : 'accent');
}
foreach ($assets as $asset) {
    if ($asset['age_days'] !== null) {
        $age_counts[min($top - 1, intdiv($asset['age_days'], 365))]++;
    }
}
if ($totals['undated']) {
    $age_labels[] = 'No date';
    $age_counts[] = $totals['undated'];
    $age_tones[] = 'muted';
}

// Coming due - hardware reaching the replacement age, by quarter for the next two years
$quarters = [];
$cursor = new DateTime('first day of this month');
$cursor->setDate(intval($cursor->format('Y')), intval((ceil($cursor->format('n') / 3) - 1) * 3 + 1), 1);
for ($q = 0; $q < 8; $q++) {
    $key = $cursor->format('Y') . '-Q' . ceil($cursor->format('n') / 3);
    $quarters[$key] = ['label' => 'Q' . ceil($cursor->format('n') / 3) . ' ' . $cursor->format('y'), 'count' => 0];
    $cursor->modify('+3 months');
}
foreach ($assets as $asset) {
    if ($asset['age_date'] !== null && !$asset['old']) {
        $due = new DateTime($asset['age_date']);
        $due->modify("+$years years");
        $key = $due->format('Y') . '-Q' . ceil($due->format('n') / 3);
        if (isset($quarters[$key])) {
            $quarters[$key]['count']++;
        }
    }
}
$coming = array_sum(array_column($quarters, 'count'));

$warranty_counts = ['Covered' => 0, 'Ends within 90 days' => 0, 'Expired' => 0, 'No date' => 0];
foreach ($assets as $asset) {
    if ($asset['asset_type'] === 'Virtual Machine') {
        continue;
    }
    $warranty_counts[!$asset['warranty'] ? 'No date' : ($asset['expired'] ? 'Expired' : ($asset['ending'] ? 'Ends within 90 days' : 'Covered'))]++;
}

$type_choices = ['' => 'Every type'];
$sql = mysqli_query($mysqli, "SELECT DISTINCT asset_type FROM assets WHERE asset_archived_at IS NULL AND asset_type != '' " . reportClientSql('asset_client_id', 0) . " ORDER BY asset_type");
while ($row = mysqli_fetch_assoc($sql)) {
    $type_choices[$row['asset_type']] = $row['asset_type'];
}
if ($type !== '' && !isset($type_choices[$type])) {
    $type_choices[$type] = $type;
}
$dated_share = $totals['dated'] ? reportShare($totals['old'], $totals['dated'], 0) : null;

?>

<div class="itflow-report">

    <?php reportHeader('asset_lifecycle', [
        'subtitle' => 'As of ' . date('M j, Y'),
        'client' => $client_id,
        'controls' => reportSelectControl('type', $type, $type_choices, 'fa-desktop') . reportSelectControl('years', $years, $year_choices, 'fa-recycle'),
        'export' => ['candidates' => 'Replacement candidates', 'types' => 'By type', 'clients' => 'By client'],
    ]); ?>

    <?= reportStats([
        ['label' => 'Assets in use', 'value' => reportCount($totals['assets']), 'url' => '/agent/assets.php' . ($client_id ? "?client_id=$client_id" : ''),
            'sub' => $totals['average'] !== null ? 'Average age <span class="text-body">' . reportYears($totals['average']) . '</span>' : ''],
        ['label' => "Over $years years old", 'value' => reportCount($totals['old']), 'tone' => $totals['old'] ? 'danger' : 'success',
            'sub' => $dated_share !== null ? reportPercent($dated_share) . ' <span class="text-body-tertiary">of dated hardware</span>' : ''],
        ['label' => 'Out of warranty', 'value' => reportCount($totals['expired']), 'tone' => $totals['expired'] ? 'warning' : '',
            'sub' => reportCount($totals['ending']) . ' <span class="text-body-tertiary">more within 90 days</span>'],
        ['label' => 'Unsupported OS', 'value' => reportCount($totals['os']), 'tone' => $totals['os'] ? 'danger' : 'success',
            'sub' => $os_names ? escapeHtml(array_key_first($os_names)) . ' <span class="text-body-tertiary">the most</span>' : '<span class="text-body-tertiary">All supported or unknown</span>'],
    ]) ?>

    <?php if (!$assets) { ?>
        <div class="card"><div class="card-body"><?= reportEmpty('No assets in use match these filters.', 'fa-desktop', 'secondary') ?></div></div>
    <?php } else { ?>

        <div class="row g-3">
            <div class="col-lg-7">
                <?= reportSectionOpen('Age', 'From purchase, else install date', 'h-100') ?>
                    <div class="card-body">
                        <?= reportChart(['type' => 'bar', 'labels' => $age_labels, 'series' => [['label' => 'Assets', 'data' => array_values($age_counts), 'tones' => $age_tones]],
                            'format' => 'count', 'legend' => false], '', 'Assets in use by age') ?>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <?= reportSectionOpen('Warranty', 'Hardware', 'h-100') ?>
                    <div class="card-body">
                        <?= reportChart(['type' => 'doughnut', 'labels' => array_keys($warranty_counts), 'series' => [['label' => 'Assets', 'data' => array_values($warranty_counts)]],
                            'colors' => ['success', 'warning', 'danger', 'muted'], 'format' => 'count'], 'donut', 'Hardware by warranty') ?>
                        <div class="mt-3"><?= reportLegendHtml(array_keys($warranty_counts), array_values($warranty_counts), ['success', 'warning', 'danger', 'muted'], 'count') ?></div>
                    </div>
                </div>
            </div>
        </div>

        <?= reportSectionOpen("Reaching $years years", reportCount($coming) . ' over the next two years - for budgeting') ?>
            <div class="card-body">
                <?= reportChart(['type' => 'bar', 'labels' => array_column($quarters, 'label'), 'series' => [['label' => 'Reaching ' . $years . ' years', 'data' => array_column($quarters, 'count'), 'tone' => 'warning']],
                    'format' => 'count', 'legend' => false], 'sm', "Hardware reaching $years years old, by quarter") ?>
            </div>
        </div>

        <?php if (!$type) { ?>
            <?= reportSectionOpen('By type', 'Pick one to see only that type') ?>
                <?= reportTable($type_columns, array_values($by_type), ['totals' => ['type' => 'Total'] + $totals, 'totals_html' => ['average' => $totals['average'] === null ? '' : reportYears($totals['average'])]]) ?>
            </div>
        <?php } ?>

        <?php if (!$client_id) { ?>
            <?= reportSectionOpen('By client', 'The most to replace first') ?>
                <?= reportTable($client_columns, array_values($by_client), ['limit' => 50, 'export_table' => 'clients', 'totals' => ['client' => 'Total'] + $totals,
                    'totals_html' => ['average' => $totals['average'] === null ? '' : reportYears($totals['average'])]]) ?>
            </div>
        <?php } ?>

        <?= reportSectionOpen('Replacement candidates', "Over $years years old, or on an unsupported OS - oldest first", 'itflow-report-long') ?>
            <?= reportTable($candidate_columns, $candidates, ['limit' => 300, 'export_table' => 'candidates',
                'empty' => reportEmpty("Nothing is over $years years old or on an unsupported operating system.")]) ?>
            <div class="card-footer small text-body-secondary">
                Assets not archived and not lost, stolen or retired. Age runs from the purchase date, else the install date; virtual machines have no age or warranty.
                Unsupported means the operating system is past its maker's end of support (such as Windows 10, Windows Server 2012 or older, macOS 13 or older).
            </div>
        </div>

    <?php } ?>

</div>

<?php require_once "../../includes/footer.php"; ?>
<script src="/js/reports.js"></script>
