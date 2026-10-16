<?php

/*
 * Credential Rotation - client logins whose password has not changed in a
 * while (90 days unless another span is picked), oldest first, with how old
 * every password is and how many each client has due. Archived credentials
 * and archived clients are left out; ones with no change date recorded count
 * as due. Each opens on its client's credentials page, where it can be
 * changed (credentials.php?open=ID). Nothing secret is shown or exported.
 */

require_once "includes/inc_report.php";
reportEnforce('credential_rotation');

$day_options = [30 => 'Not changed in 30 days', 60 => 'Not changed in 60 days', 90 => 'Not changed in 90 days', 180 => 'Not changed in 6 months', 365 => 'Not changed in a year'];
$days = intval($_GET['days'] ?? CREDENTIAL_ROTATE_DAYS);
if (!isset($day_options[$days])) {
    $days = CREDENTIAL_ROTATE_DAYS;
}
$client_id = reportClientId();

$credentials = [];
$sql = mysqli_query($mysqli, "SELECT credential_id, credential_name, credential_description, credential_uri, credential_category, credential_password_changed_at, credential_accessed_at,
        (credential_otp_secret IS NOT NULL AND credential_otp_secret != '') AS has_otp, credential_client_id, client_name, client_abbreviation
    FROM credentials
    JOIN clients ON credential_client_id = client_id
    WHERE credential_archived_at IS NULL AND client_archived_at IS NULL" . reportClientSql('credential_client_id', $client_id) . "
    ORDER BY credential_password_changed_at IS NOT NULL, credential_password_changed_at ASC, client_name ASC, credential_name ASC");
while ($row = mysqli_fetch_assoc($sql)) {
    $row['has_otp'] = intval($row['has_otp']) === 1;
    $row['age'] = credentialAge($row['credential_password_changed_at']);
    $row['age_days'] = $row['age'] ? $row['age']['days'] : null;
    $row['due'] = !$row['age'] || $row['age']['days'] >= $days;
    $row['overdue'] = !$row['age'] || $row['age']['state'] === 'overdue';
    $credentials[] = $row;
}
$due = array_values(array_filter($credentials, function ($c) { return $c['due']; }));

// Every password by age
$age_buckets = ['Under 90 days' => [0, 90, 'success'], '90 days to 6 months' => [90, 182, 'warning'], '6 to 12 months' => [182, 365, 'orange'],
    '1 to 2 years' => [365, 730, 'danger'], 'Over 2 years' => [730, PHP_INT_MAX, 'danger']];
$age_counts = array_fill_keys(array_keys($age_buckets), 0) + ['Never recorded' => 0];

$by_client = [];
foreach ($credentials as $row) {
    $c = intval($row['credential_client_id']);
    $by_client[$c] = $by_client[$c] ?? ['client_id' => $c, 'client' => $row['client_name'], 'abbr' => $row['client_abbreviation'], 'total' => 0, 'due' => 0, 'overdue' => 0, 'otp' => 0, 'oldest' => null];
    $by_client[$c]['total']++;
    $by_client[$c]['due'] += $row['due'] ? 1 : 0;
    $by_client[$c]['overdue'] += $row['overdue'] ? 1 : 0;
    $by_client[$c]['otp'] += $row['has_otp'] ? 1 : 0;
    if ($row['age']) {
        $by_client[$c]['oldest'] = max($by_client[$c]['oldest'] ?? 0, $row['age']['days']);
        foreach ($age_buckets as $label => [$from, $to]) {
            if ($row['age']['days'] >= $from && $row['age']['days'] < $to) {
                $age_counts[$label]++;
                break;
            }
        }
    } else {
        $age_counts['Never recorded']++;
    }
}
foreach ($by_client as &$client) {
    $client['otp_share'] = reportShare($client['otp'], $client['total'], 0);
}
unset($client);
$by_client = array_values(array_filter($by_client, function ($c) { return $c['due'] > 0; }));
usort($by_client, function ($a, $b) { return $b['overdue'] <=> $a['overdue'] ?: $b['due'] <=> $a['due']; });

$client_columns = [
    'client' => ['label' => 'Client', 'html' => function ($row) {
        return reportClientCell($row['client_id'], $row['client'], $row['abbr'], 'credentials.php');
    }],
    'total' => ['label' => 'Credentials', 'type' => 'count'],
    'due' => ['label' => 'Due', 'type' => 'count', 'html' => function ($row) {
        return '<a class="itflow-report-amount text-warning-emphasis fw-semibold" href="/agent/credentials.php?client_id=' . intval($row['client_id']) . '&show=due&sort=credential_password_changed_at&order=ASC">'
            . reportCount($row['due']) . '</a>';
    }],
    'overdue' => ['label' => 'Over a year or never', 'type' => 'count', 'html' => function ($row) {
        return $row['overdue'] ? '<span class="text-danger-emphasis fw-semibold">' . reportCount($row['overdue']) . '</span>' : '<span class="itflow-report-zero">–</span>';
    }],
    'oldest' => ['label' => 'Oldest change', 'type' => 'count', 'csv_label' => 'Oldest change (days ago)', 'html' => function ($row) {
        return $row['oldest'] === null ? '<span class="itflow-report-zero">–</span>' : '<span class="text-nowrap">' . escapeHtml(timeAgo(date('Y-m-d', strtotime('-' . $row['oldest'] . ' days')))) . '</span>';
    }],
    'otp_share' => ['label' => 'With 2FA', 'type' => 'percent', 'title' => 'Credentials holding a 2FA (TOTP) code', 'class' => 'text-body-secondary'],
];

$credential_columns = [
    'credential_name' => ['label' => 'Credential', 'class' => 'itflow-report-text', 'html' => function ($row) {
        return '<div class="d-flex align-items-center gap-2">' . credentialTile($row, 'sm') . '<div class="min-w-0">'
            . '<a class="fw-semibold text-body" href="/agent/credentials.php?client_id=' . intval($row['credential_client_id']) . '&open=' . intval($row['credential_id']) . '">' . escapeHtml($row['credential_name']) . '</a>'
            . ($row['credential_description'] ? '<div class="small text-body-secondary">' . escapeHtml($row['credential_description']) . '</div>' : '') . '</div></div>';
    }],
    'credential_description' => ['label' => 'Description', 'screen' => false],
    'client_name' => ['label' => 'Client', 'html' => function ($row) {
        return reportClientCell($row['credential_client_id'], $row['client_name'], $row['client_abbreviation'], 'credentials.php');
    }],
    'age_days' => ['label' => 'Password last changed', 'type' => 'count', 'csv_label' => 'Days since changed', 'html' => function ($row) {
        if (!$row['age']) {
            return '<span class="badge rounded-pill bg-danger-subtle text-danger-emphasis border border-danger-subtle fw-normal">Never recorded</span>';
        }
        return credentialAgePill($row['age']) . '<div class="small text-body-secondary mt-1">' . escapeHtml($row['age']['date']) . '</div>';
    }],
    'credential_password_changed_at' => ['label' => 'Changed on', 'type' => 'date', 'screen' => false],
    'has_otp' => ['label' => '2FA', 'sort' => false, 'csv' => function ($row) { return $row['has_otp'] ? 'Yes' : 'No'; }, 'html' => function ($row) {
        return $row['has_otp'] ? '<span class="text-success-emphasis small text-nowrap"><i class="fas fa-fw fa-shield-alt me-1"></i>Yes</span>' : '<span class="itflow-report-zero">–</span>';
    }],
    'credential_accessed_at' => ['label' => 'Last opened', 'type' => 'date', 'html' => function ($row) {
        return $row['credential_accessed_at'] ? '<span class="small text-body-secondary text-nowrap" title="' . escapeHtml($row['credential_accessed_at']) . '">' . escapeHtml(timeAgo($row['credential_accessed_at'])) . '</span>'
            : '<span class="itflow-report-zero">–</span>';
    }],
];

if (reportExporting('clients')) {
    reportCsv('Credential Rotation by Client', $client_columns, $by_client);
}
if (reportExporting()) {
    reportCsv('Credentials Due for Rotation', $credential_columns, $due);
}

require_once "includes/inc_report_layout.php";

$overdue = count(array_filter($credentials, function ($c) { return $c['overdue']; }));
$otp = count(array_filter($credentials, function ($c) { return $c['has_otp']; }));
$clients_due = count($by_client);
$span = ['30' => '30 days', '60' => '60 days', '90' => '90 days', '180' => '6 months', '365' => 'year'][(string) $days];

?>

<div class="itflow-report">

    <?php reportHeader('credential_rotation', [
        'subtitle' => $day_options[$days],
        'controls' => reportSelectControl('days', $days, $day_options, 'fa-history') . reportClientControl($client_id),
        'export' => ['credentials' => 'Credentials due', 'clients' => 'By client'],
    ]); ?>

    <?= reportStats([
        ['label' => 'Credentials', 'value' => reportCount(count($credentials)), 'url' => '/agent/credentials.php' . ($client_id ? "?client_id=$client_id" : ''),
            'sub' => reportCount($otp) . ' <span class="text-body-tertiary">with a 2FA code</span>'],
        ['label' => 'Due for rotation', 'value' => reportCount(count($due)), 'tone' => $due ? 'warning' : 'success',
            'sub' => $credentials ? reportPercent(reportShare(count($due), count($credentials), 0)) . ' <span class="text-body-tertiary">of them</span>' : ''],
        ['label' => 'Over a year or never', 'value' => reportCount($overdue), 'tone' => $overdue ? 'danger' : 'success',
            'sub' => reportCount($age_counts['Never recorded']) . ' <span class="text-body-tertiary">never recorded</span>'],
        ['label' => 'Clients with some due', 'value' => reportCount($clients_due), 'tone' => $clients_due ? 'warning' : ''],
    ]) ?>

    <?php if ($credentials) { ?>
        <?= reportSectionOpen('How old the passwords are', 'Every credential') ?>
            <div class="card-body">
                <?= reportChart([
                    'type' => 'bar',
                    'labels' => array_keys($age_counts),
                    'series' => [['label' => 'Credentials', 'data' => array_values($age_counts),
                        'tones' => array_merge(array_column(array_values($age_buckets), 2), ['muted'])]],
                    'format' => 'count',
                    'legend' => false,
                ], 'sm', 'Credentials by how long since the password changed') ?>
            </div>
        </div>
    <?php } ?>

    <?php if (!$due) { ?>
        <div class="card"><div class="card-body"><?= reportEmpty("Every password has changed in the last $span.") ?></div></div>
    <?php } else { ?>

        <?php if (!$client_id) { ?>
            <?= reportSectionOpen('By client', 'The most overdue first') ?>
                <?= reportTable($client_columns, $by_client, ['limit' => 50, 'export_table' => 'clients']) ?>
            </div>
        <?php } ?>

        <?= reportSectionOpen('Due for rotation', 'Never recorded, then the oldest', 'itflow-report-long') ?>
            <?= reportTable($credential_columns, $due, ['limit' => 300, 'export_table' => 'credentials']) ?>
            <div class="card-footer small text-body-secondary">
                Credentials not archived, at active clients. A password counts from when it was last changed in ITFlow; one never recorded counts as due.
                Open a credential to change it and the date updates on its own. Passwords and 2FA codes are never shown here or exported.
            </div>
        </div>

    <?php } ?>

</div>

<?php require_once "../../includes/footer.php"; ?>
<script src="/js/reports.js"></script>
