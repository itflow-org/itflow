<?php

/*
 * Admin > Debug - how ITFlow and the server under it are doing
 * (functions/system_health.php). What needs putting right comes first, each
 * with what to do and where; then the figures at a glance, every check by
 * section, the database (its size, biggest tables and how its structure
 * compares with db.sql) and the uploads folder. Copy for support puts it all
 * on the clipboard as plain text for a forum post or a support email,
 * without host names, paths or keys.
 */

require_once "includes/inc_all_admin.php";
require_once "../includes/database_version.php";
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/cron_jobs.php'; // the jobs' names

$sections = healthChecks();
$summary = healthSummary($sections);
$exact = !empty($_GET['exact']); // Count exactly - every table counted, rather than the database's estimate
$database_stats = healthDatabaseTables($exact);
$differences = healthStructureDifferences();
$uploads = healthUploads();
$report = healthReport($sections, $database_stats, $differences, $uploads);
$levels = healthLevels();

// The figures along the top
$cron = healthRow("SELECT config_cron_last_dispatch_at FROM settings WHERE company_id = 1");
$cron_heard = $cron['config_cron_last_dispatch_at'] ?? null;
$cron_stale = !$cron_heard || time() - strtotime($cron_heard) >= 300;
$last_backup = healthRow("SELECT backup_completed_at, backup_size FROM backups WHERE backup_status = 'Complete' ORDER BY backup_completed_at DESC LIMIT 1");
$errors_day = healthRow("SELECT COUNT(*) AS n FROM app_logs WHERE app_log_type = 'error' AND app_log_created_at > NOW() - INTERVAL 1 DAY");
try {
    $mail = mailQueueSummary();
} catch (Throwable $e) {
    $mail = null;
}
$db_behind = version_compare((string) LATEST_DATABASE_VERSION, (string) CURRENT_DATABASE_VERSION, '>');
$branch = gitCurrentBranch();
$commit = gitCurrentCommit();
$stats = [
    ['label' => 'ITFlow', 'value' => APP_VERSION, 'sub' => escapeHtml($branch !== '' ? $branch . ($commit !== '' ? ' @ ' . substr($commit, 0, 7) : '') : 'Not a git checkout')],
    ['label' => 'Database', 'value' => CURRENT_DATABASE_VERSION, 'tone' => $db_behind ? 'danger' : '', 'url' => $db_behind ? 'update.php' : '',
        'sub' => $db_behind ? '<span class="text-danger-emphasis">Update to ' . escapeHtml(LATEST_DATABASE_VERSION) . ' waiting</span>' : 'Up to date'],
    ['label' => 'PHP', 'value' => PHP_VERSION, 'sub' => escapeHtml(PHP_SAPI)],
    ['label' => 'Cron', 'value' => healthShortAgo($cron_heard), 'title' => $cron_heard ? 'Last ran ' . healthAgo($cron_heard) : '', 'tone' => $cron_stale ? 'danger' : '', 'url' => 'cron.php',
        'sub' => $cron_stale ? '<span class="text-danger-emphasis">Not running</span>' : 'Running'],
    ['label' => 'Failed email', 'value' => $mail ? number_format($mail['failed_week']) : '-', 'tone' => $mail && $mail['failed_week'] ? 'warning' : '',
        'url' => $mail && $mail['failed_week'] ? 'mail_queue.php?show=failed' : 'mail_queue.php', 'sub' => $mail ? 'This week · ' . number_format($mail['waiting']) . ' waiting' : 'Database update needed'],
    ['label' => 'Last backup', 'value' => $last_backup ? healthShortAgo($last_backup['backup_completed_at']) : 'None yet', 'url' => 'backup.php',
        'tone' => !$last_backup || strtotime($last_backup['backup_completed_at']) < time() - 7 * 86400 ? 'warning' : '',
        'sub' => $last_backup ? escapeHtml(formatBytes(intval($last_backup['backup_size']), 1)) : 'Take one now'],
    ['label' => 'Errors', 'value' => $errors_day ? number_format(intval($errors_day['n'])) : '-', 'tone' => !empty($errors_day['n']) ? 'warning' : '',
        'url' => !empty($errors_day['n']) ? 'app_logs.php?view=error' : 'app_logs.php', 'sub' => 'In the last day'],
];

$problems = $summary['counts']['danger'];
$warnings = $summary['counts']['warning'];
$checked = array_sum($summary['counts']);
$plural = function ($n, $one, $many) {
    return number_format($n) . ' ' . ($n === 1 ? $one : $many);
};

// A link from a check - ITFlow's own pages here, the docs in a new tab
$action_link = function (array $item, $class) {
    if ($item['url'] === '') {
        return '';
    }
    $outside = preg_match('#^https?://#i', $item['url']);
    return '<a href="' . escapeHtml($item['url']) . '" class="' . $class . '"' . ($outside ? ' target="_blank" rel="noopener noreferrer"' : '') . '>'
        . escapeHtml($item['action'] !== '' ? $item['action'] : 'Open') . '<i class="fas fa-' . ($outside ? 'external-link-alt' : 'arrow-right') . ' ms-1 small"></i></a>';
};

?>

<div class="card card-dark">
    <div class="card-header py-3">
        <h3 class="card-title mt-1"><i class="fas fa-fw fa-bug me-2"></i>Debug</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-outline-light clipboardjs" data-clipboard-target="#debugReport" title="Everything here as plain text, for a forum post or a support email - without host names, paths or keys" data-debug-copy>
                <i class="fas fa-fw fa-copy"></i><span class="d-none d-md-inline ms-2">Copy for support</span>
            </button>
            <a href="debug.php<?= $exact ? '?exact=1' : '' ?>" class="btn btn-outline-light ms-1" title="Check everything again">
                <i class="fas fa-fw fa-redo-alt"></i><span class="d-none d-md-inline ms-2">Check again</span>
            </a>
        </div>
    </div>

    <div class="card-body">

        <!-- What needs putting right, worst first -->
        <?php if (!$summary['attention']) { ?>
            <div class="alert alert-success d-flex align-items-center gap-3 mb-4" data-debug-summary="ok">
                <i class="fas fa-fw fa-check-circle fa-lg"></i>
                <div><strong>Everything looks good.</strong> <?= $plural($checked, 'check', 'checks') ?>, nothing to put right.</div>
            </div>
        <?php } else {
            $tone = $problems ? 'danger' : 'warning'; ?>
            <div class="alert alert-<?= $tone ?> mb-4 itflow-debug-attention" data-debug-summary="<?= $tone ?>">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <i class="fas fa-fw <?= $levels[$tone]['icon'] ?> fa-lg"></i>
                    <strong><?= implode(' and ', array_filter([$problems ? $plural($problems, 'problem', 'problems') : '', $warnings ? $plural($warnings, 'warning', 'warnings') : ''])) ?></strong>
                    <span class="d-none d-sm-inline">- <?= $problems ? 'parts of ITFlow won\'t work until they are put right.' : 'worth a look.' ?></span>
                </div>
                <ul class="list-unstyled mb-0">
                    <?php foreach ($summary['attention'] as $item) { ?>
                        <li class="d-flex align-items-baseline gap-2 py-1" data-debug-attention-item>
                            <i class="fas fa-fw <?= $levels[$item['level']]['icon'] ?> text-<?= $levels[$item['level']]['tone'] ?>" title="<?= $levels[$item['level']]['word'] ?>"></i>
                            <div class="d-flex flex-wrap align-items-baseline column-gap-3 flex-grow-1 min-w-0">
                                <span class="min-w-0 text-break"><span class="text-body-secondary"><?= escapeHtml($item['section']) ?> ·</span> <strong><?= escapeHtml($item['label']) ?></strong> - <?= escapeHtml($item['value']) ?></span>
                                <?= $action_link($item, 'ms-auto small text-nowrap alert-link') ?>
                            </div>
                        </li>
                    <?php } ?>
                </ul>
            </div>
        <?php } ?>

        <!-- At a glance -->
        <div class="mb-4 itflow-debug-stats"><?= reportStats($stats) ?></div>

        <!-- Every check, by section -->
        <div class="row row-cols-1 row-cols-lg-2 row-cols-xxl-3 g-3 mb-4">
            <?php foreach ($sections as $section) {
                if (!$section['items']) {
                    continue;
                }
                $bad = 0;
                $worst = 'ok';
                foreach ($section['items'] as $item) {
                    if ($item['level'] === 'danger' || $item['level'] === 'warning') {
                        $bad++;
                        $worst = $worst === 'danger' ? 'danger' : $item['level'];
                    }
                } ?>
                <div class="col">
                    <section class="card h-100 mb-0 itflow-debug-section" data-debug-section="<?= $section['key'] ?>">
                        <div class="card-header d-flex align-items-center gap-2 py-2">
                            <span class="itflow-debug-section-icon"><i class="fas fa-fw <?= $section['icon'] ?>"></i></span>
                            <h2 class="h6 mb-0 me-auto"><?= escapeHtml($section['title']) ?></h2>
                            <?php if ($bad) { ?>
                                <span class="badge text-bg-<?= $worst ?>"><?= $bad ?> to look at</span>
                            <?php } elseif ($section['key'] !== 'server') { ?>
                                <i class="fas fa-check-circle text-success" title="All OK"></i>
                            <?php } ?>
                        </div>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($section['items'] as $item) {
                                $level = $levels[$item['level']]; ?>
                                <li class="list-group-item itflow-debug-item" data-debug-item="<?= $item['level'] ?>">
                                    <i class="fas fa-fw <?= $level['icon'] ?> text-<?= $level['tone'] ?> itflow-debug-item-icon" title="<?= $level['word'] ?>"></i>
                                    <div class="min-w-0 flex-grow-1">
                                        <div class="d-flex flex-wrap column-gap-2">
                                            <span class="fw-semibold"><?= escapeHtml($item['label']) ?></span>
                                            <span class="text-body-secondary text-break"><?= escapeHtml($item['value']) ?></span>
                                        </div>
                                        <?php if ($item['fix'] !== '' && $item['level'] !== 'ok') { ?>
                                            <div class="small mt-1 itflow-debug-fix"><?= $item['fix'] ?></div>
                                        <?php } ?>
                                        <?php if ($item['url'] !== '' && $item['level'] !== 'ok') { ?>
                                            <div class="small mt-1"><?= $action_link($item, '') ?></div>
                                        <?php } ?>
                                    </div>
                                </li>
                            <?php } ?>
                        </ul>
                    </section>
                </div>
            <?php } ?>
        </div>

        <div class="row g-3">

            <!-- The database -->
            <div class="col-xl-7">
                <section class="card h-100 mb-0" id="debug-database" data-debug-database>
                    <div class="card-header d-flex align-items-center gap-2 py-2">
                        <span class="itflow-debug-section-icon"><i class="fas fa-fw fa-database"></i></span>
                        <h2 class="h6 mb-0 me-auto">Database</h2>
                        <span class="small text-body-secondary"><?= $plural(count($database_stats['tables']), 'table', 'tables') ?> · <?= $exact ? '' : 'about ' ?><?= number_format($database_stats['rows']) ?> rows · <?= escapeHtml(formatBytes($database_stats['size'], 1)) ?></span>
                    </div>
                    <div class="card-body">

                        <h3 class="h6 text-uppercase text-body-secondary fw-semibold small mb-2">Structure</h3>
                        <?php if ($differences === null) { ?>
                            <p class="text-body-secondary" data-debug-structure="none"><i class="fas fa-fw fa-info-circle me-1"></i>db.sql isn't here to compare with.</p>
                        <?php } elseif (!$differences) { ?>
                            <p data-debug-structure="ok"><i class="fas fa-fw fa-check-circle text-success me-1"></i>Every table and column matches db.sql - what a fresh install makes.</p>
                        <?php } else {
                            $missing = count(array_filter($differences, function ($d) {
                                return strpos($d['what'], 'Missing') === 0;
                            })); ?>
                            <p class="mb-2" data-debug-structure="<?= count($differences) ?>">
                                <i class="fas fa-fw <?= $missing ? 'fa-times-circle text-danger' : 'fa-info-circle text-secondary' ?> me-1"></i>
                                <?= $plural(count($differences), 'difference', 'differences') ?> from db.sql.
                                <?php if ($missing) { ?>
                                    Something missing usually means a database update didn't finish - <a href="update.php">Maintenance &gt; Update</a>.
                                <?php } else { ?>
                                    Extra tables and columns are usually left by older versions or a plugin, and do no harm.
                                <?php } ?>
                            </p>
                            <details <?= count($differences) <= 10 ? 'open' : '' ?> class="mb-3">
                                <summary class="small text-body-secondary mb-2">Show them</summary>
                                <div class="table-responsive">
                                    <table class="table table-sm table-borderless table-hover small mb-0">
                                        <thead class="text-body-secondary">
                                            <tr><th>What</th><th>Where</th><th>db.sql</th><th>Here</th></tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($differences as $difference) { ?>
                                                <tr>
                                                    <td class="text-nowrap"><?= escapeHtml($difference['what']) ?></td>
                                                    <td class="text-break"><code><?= escapeHtml($difference['table'] . ($difference['column'] !== '' ? '.' . $difference['column'] : '')) ?></code></td>
                                                    <td><?= escapeHtml($difference['expected']) ?></td>
                                                    <td><?= escapeHtml($difference['found']) ?></td>
                                                </tr>
                                            <?php } ?>
                                        </tbody>
                                    </table>
                                </div>
                            </details>
                        <?php } ?>

                        <h3 class="h6 text-uppercase text-body-secondary fw-semibold small mb-2 mt-3">Biggest tables</h3>
                        <div class="table-responsive">
                            <table class="table table-sm table-borderless table-hover mb-0" data-debug-tables>
                                <thead class="text-body-secondary small">
                                    <tr><th>Table</th><th class="text-end"><?= $exact ? 'Rows' : 'Rows (about)' ?></th><th class="text-end">Size</th><th class="d-none d-sm-table-cell w-25"></th></tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $biggest = max(1, max(array_column($database_stats['tables'], 'bytes') ?: [1]));
                                    $i = 0;
                                    foreach ($database_stats['tables'] as $table => $table_stats) {
                                        $i++; ?>
                                        <tr <?= $i > 10 ? 'class="d-none" data-debug-more-table' : '' ?>>
                                            <td class="text-break"><?= escapeHtml($table) ?></td>
                                            <td class="text-end text-nowrap"><?= number_format($table_stats['rows']) ?></td>
                                            <td class="text-end text-nowrap"><?= escapeHtml(formatBytes($table_stats['bytes'], 1)) ?></td>
                                            <td class="d-none d-sm-table-cell align-middle">
                                                <div class="progress itflow-debug-bar" role="presentation"><div class="progress-bar bg-secondary" style="width: <?= round($table_stats['bytes'] / $biggest * 100, 1) ?>%"></div></div>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if (count($database_stats['tables']) > 10) { ?>
                            <button type="button" class="btn btn-link btn-sm px-0" data-debug-show-tables>Show all <?= count($database_stats['tables']) ?> tables</button>
                        <?php } ?>
                        <p class="small text-body-secondary mt-2 mb-0" data-debug-rows="<?= $exact ? 'exact' : 'estimate' ?>">
                            <?php if ($exact) { ?>
                                Every table counted. <a href="debug.php">Back to estimates</a>
                            <?php } else { ?>
                                Row counts are the database's own estimate, so a big install opens this page quickly. <a href="debug.php?exact=1#debug-database">Count exactly</a> - slower on a big install.
                            <?php } ?>
                        </p>
                    </div>
                </section>
            </div>

            <!-- The uploads folder -->
            <div class="col-xl-5">
                <section class="card h-100 mb-0" data-debug-uploads>
                    <div class="card-header d-flex align-items-center gap-2 py-2">
                        <span class="itflow-debug-section-icon"><i class="fas fa-fw fa-folder-open"></i></span>
                        <h2 class="h6 mb-0 me-auto">Uploads</h2>
                        <span class="small text-body-secondary"><?= $plural($uploads['files'], 'file', 'files') ?> · <?= escapeHtml(formatBytes($uploads['bytes'], 1)) ?><?= $uploads['partial'] ? ' at least' : '' ?></span>
                    </div>
                    <div class="card-body">
                        <?php if (!$uploads['folders']) { ?>
                            <p class="text-body-secondary mb-0">Nothing uploaded yet.</p>
                        <?php } else { ?>
                            <div class="table-responsive">
                                <table class="table table-sm table-borderless table-hover mb-0">
                                    <thead class="text-body-secondary small">
                                        <tr><th>Folder</th><th class="text-end">Files</th><th class="text-end">Size</th></tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($uploads['folders'] as $folder => $folder_stats) { ?>
                                            <tr>
                                                <td class="text-break"><i class="far fa-fw fa-folder text-body-secondary me-1"></i><?= escapeHtml($folder) ?></td>
                                                <td class="text-end text-nowrap"><?= number_format($folder_stats['files']) ?></td>
                                                <td class="text-end text-nowrap"><?= escapeHtml(formatBytes($folder_stats['bytes'], 1)) ?></td>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php } ?>
                        <?php if ($uploads['partial']) { ?>
                            <p class="small text-body-secondary mt-2 mb-0"><i class="fas fa-fw fa-hourglass-half me-1"></i>There are a lot of files - counting stopped after a few seconds, so these are at least what is there.</p>
                        <?php } ?>
                    </div>
                </section>
            </div>
        </div>

        <!-- The report Copy for support copies - shown here too, for a browser that won't copy -->
        <details class="mt-4" data-debug-report>
            <summary class="text-body-secondary">The report Copy for support copies</summary>
            <p class="small text-body-secondary mt-2 mb-2">Versions, what needs looking at, and the size of things - without host names, paths, addresses or keys. Read it before you post it.</p>
            <textarea id="debugReport" class="form-control font-monospace small" rows="14" readonly><?= escapeHtml($report) ?></textarea>
        </details>

        <p class="small text-body-secondary mt-4 mb-0">
            <i class="fas fa-fw fa-life-ring me-1"></i>For a problem this page doesn't show, the web server's and PHP's own error logs say more -
            <a href="https://docs.itflow.org/gathering_logs#error_logs" target="_blank" rel="noopener noreferrer">how to find them</a>.
        </p>
    </div>
</div>

<script>
    // Show every table, not just the ten biggest
    document.querySelectorAll('[data-debug-show-tables]').forEach(function (button) {
        button.addEventListener('click', function () {
            document.querySelectorAll('[data-debug-more-table]').forEach(function (row) {
                row.classList.remove('d-none');
            });
            button.remove();
        });
    });
</script>

<?php
require_once "../includes/footer.php";
