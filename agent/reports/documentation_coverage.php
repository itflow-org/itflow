<?php

/*
 * Documentation Coverage - for each active client, whether the basics an
 * agent reaches for are written down: who to call and who pays, where they
 * are, their hardware (placed and with an owner), networks, logins,
 * documents, domains, services and licenses. A check that cannot apply (no
 * user devices to give owners) is left out of that client's score. Worst
 * covered first, with what is missing linking to where to add it.
 */

require_once "includes/inc_report.php";
reportEnforce('documentation_coverage');

$gaps_only = !empty($_GET['gaps']);
$checks = reportCoverageChecks();
$clients = reportCoverage();

$all = array_values($clients);
usort($all, function ($a, $b) { return $a['score'] <=> $b['score'] ?: strcasecmp($a['client'], $b['client']); });
$rows = $gaps_only ? array_values(array_filter($all, function ($c) { return $c['missing']; })) : $all;

// How many clients miss each check
$missing_counts = array_fill_keys(array_keys($checks), 0);
foreach ($all as $client) {
    foreach ($client['missing'] as $key) {
        $missing_counts[$key]++;
    }
}
arsort($missing_counts);

$columns = [
    'client' => ['label' => 'Client', 'html' => function ($row) {
        return reportClientCell($row['client_id'], $row['client'], $row['abbr']);
    }],
    'score' => ['label' => 'Coverage', 'type' => 'percent', 'html' => function ($row) {
        $tone = $row['score'] >= 90 ? 'success' : ($row['score'] >= 70 ? 'warning' : 'danger');
        return '<div class="d-flex align-items-center justify-content-end gap-2"><div class="progress itflow-report-meter" style="width: 5rem" role="presentation">'
            . '<div class="progress-bar bg-' . $tone . '" style="width: ' . round($row['score']) . '%"></div></div>'
            . '<span class="fw-semibold text-' . $tone . '-emphasis" title="' . $row['passed'] . ' of ' . $row['applies'] . ' checks">' . reportPercent($row['score']) . '</span></div>';
    }],
    'missing' => ['label' => 'Missing', 'sort' => false, 'class' => 'itflow-report-text', 'csv' => function ($row) use ($checks) {
        return implode(', ', array_map(function ($key) use ($checks) { return $checks[$key]['label']; }, $row['missing']));
    }, 'html' => function ($row) {
        return reportCoverageMissingHtml($row);
    }],
    'contacts' => ['label' => 'Contacts', 'type' => 'count'],
    'locations' => ['label' => 'Locations', 'type' => 'count', 'screen' => false],
    'assets' => ['label' => 'Assets', 'type' => 'count'],
    'placed_share' => ['label' => 'Placed', 'type' => 'percent', 'title' => 'Assets with a location', 'html' => function ($row) {
        return $row['placed_share'] === null ? '<span class="itflow-report-zero">–</span>' : reportRateHtml($row['placed_share'], 95, REPORT_COVERAGE_SHARE);
    }],
    'owned_share' => ['label' => 'Owned', 'type' => 'percent', 'title' => 'Laptops, desktops, phones and tablets with a contact', 'html' => function ($row) {
        return $row['owned_share'] === null ? '<span class="itflow-report-zero">–</span>' : reportRateHtml($row['owned_share'], 95, REPORT_COVERAGE_SHARE);
    }],
    'documents' => ['label' => 'Documents', 'type' => 'count'],
];
// The export carries every check as Yes / No / blank (does not apply)
foreach ($checks as $key => $check) {
    $columns["check_$key"] = ['label' => $check['label'], 'csv_label' => 'Check: ' . $check['label'], 'screen' => false, 'csv' => function ($row) use ($key) {
        return $row['checks'][$key] === null ? '' : ($row['checks'][$key] ? 'Yes' : 'No');
    }];
}

if (reportExporting()) {
    reportCsv('Documentation Coverage', $columns, $rows);
}

require_once "includes/inc_report_layout.php";

$scores = array_column($all, 'score');
$average = $scores ? array_sum($scores) / count($scores) : null;
$complete = count(array_filter($all, function ($c) { return !$c['missing']; }));
$top_gap = array_key_first(array_filter($missing_counts));

?>

<div class="itflow-report">

    <?php reportHeader('documentation_coverage', [
        'subtitle' => 'As of ' . date('M j, Y'),
        'controls' => reportSwitchControl('gaps', $gaps_only, 'Only clients with gaps'),
    ]); ?>

    <?= reportStats([
        ['label' => 'Active clients', 'value' => reportCount(count($all)), 'sub' => reportCount(count($checks)) . ' <span class="text-body-tertiary">checks each</span>'],
        ['label' => 'Average coverage', 'value_html' => $average === null ? '-' : reportRateHtml($average, 90, 70)],
        ['label' => 'Fully documented', 'value' => reportCount($complete), 'tone' => $complete ? 'success' : '',
            'sub' => count($all) ? reportPercent(reportShare($complete, count($all), 0)) . ' <span class="text-body-tertiary">of clients</span>' : ''],
        ['label' => 'Most often missing', 'value' => $top_gap ? $checks[$top_gap]['label'] : 'Nothing', 'tone' => $top_gap ? 'warning' : 'success',
            'sub' => $top_gap ? reportCount($missing_counts[$top_gap]) . ' <span class="text-body-tertiary">' . ($missing_counts[$top_gap] === 1 ? 'client' : 'clients') . '</span>' : ''],
    ]) ?>

    <?php if (array_filter($missing_counts)) { ?>
        <?= reportSectionOpen('What is missing most', 'Clients without it') ?>
            <div class="card-body">
                <?php $gap_rows = array_filter($missing_counts); ?>
                <?= reportChart([
                    'type' => 'bar',
                    'horizontal' => true,
                    'labels' => array_map(function ($key) use ($checks) { return $checks[$key]['label']; }, array_keys($gap_rows)),
                    'series' => [['label' => 'Clients without it', 'data' => array_values($gap_rows), 'tone' => 'warning']],
                    'format' => 'count',
                    'legend' => false,
                ], '', 'How many clients are missing each item') ?>
            </div>
        </div>
    <?php } ?>

    <?= reportSectionOpen('By client', 'Least covered first') ?>
        <?= reportTable($columns, $rows, ['empty' => reportEmpty($gaps_only ? 'Every client has everything documented.' : 'No active clients.')]) ?>
        <div class="card-footer small text-body-secondary">
            Active clients, counting only what is not archived. Placed and owned pass at <?= REPORT_COVERAGE_SHARE ?>%: assets with a location, and laptops, desktops, phones and
            tablets with a contact; a client without any is not scored on them. Each missing item opens the page to add it on.
        </div>
    </div>

</div>

<?php require_once "../../includes/footer.php"; ?>
<script src="/js/reports.js"></script>
