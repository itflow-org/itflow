<?php

/*
 * Maintenance > Cron. One answer at the top - cron is running, it has stopped or never
 * started, it is switched off, a job failed - then every job: what it does, its schedule,
 * how its last run went and when the next is. Beside them: cron at a glance, the one
 * crontab line all of it runs from, and the switch that stops every job at once.
 *
 * Nothing runs here. Run now sets a flag the dispatcher (cron/cron.php) takes on its next
 * pass, so while a job is queued or running the page reloads itself (js/auto_reload.js), as
 * Maintenance > Update and Backup do - and while cron hasn't been heard from, so the page
 * says so by itself once the crontab line is in. The handlers are admin/post/cron.php.
 *
 * The jobs are the registry's (includes/cron_jobs.php), each with its row from cron_jobs. A
 * job the dispatcher has not met yet has no row: it shows the schedule it ships with, and
 * there is nothing to change about it until cron has run once.
 */

// The list: by schedule until a heading is clicked - the every-minute jobs, then the day's in the order they run
$sort = "schedule";
$order = "ASC";

require_once "includes/inc_all_admin.php";
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/cron_jobs.php';

$csrf = $_SESSION['csrf_token'];

$row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT config_enable_cron, config_cron_last_dispatch_at FROM settings WHERE company_id = 1"));

$cron_on = intval($row['config_enable_cron']) === 1;
$cron_last_dispatch_at = $row['config_cron_last_dispatch_at'];

// The dispatcher writes its heartbeat before it runs anything, so one older than a few
// minutes means the crontab entry itself is missing or failing - a different problem from a
// job that is off or erroring, and the one people spend the longest not finding.
$cron_heard = !empty($cron_last_dispatch_at) && (time() - strtotime($cron_last_dispatch_at)) < 300;

// A managed instance its manager has made read-only or suspended runs only the jobs that keep it looked after
$managed_mode = managedMode();
$managed_kept = $managed_mode === 'active' ? [] : (MANAGED_KEEP_JOBS[$managed_mode] ?? []);

// The crontab line, and a job run by hand, as this server runs them - in this folder, as the user that owns the files
$install_root = dirname(__DIR__);
$file_owner = '';
if (function_exists('posix_getpwuid')) {
    $file_owner = (string) (posix_getpwuid((int) @fileowner($install_root . '/cron/cron.php'))['name'] ?? '');
}
$shell_word = function ($word) {
    return preg_match('/^[A-Za-z0-9_\/.@%+=:,-]+$/', $word) ? $word : escapeshellarg($word);
};
$cron_command = "* * * * * php $install_root/cron/cron.php >/dev/null";
$by_hand = function ($script) use ($install_root, $file_owner, $shell_word) {
    return "cd " . $shell_word($install_root) . " && " . ($file_owner !== '' ? "sudo -u " . $shell_word($file_owner) . " " : '') . "php cron/" . $shell_word($script);
};

// What a job said as it stopped early, as it reads on the page
$tidy_reason = function ($reason) {
    $reason = preg_replace('/\s*--\s*Quitting\.*\s*$/i', '', trim(ltrim((string) $reason, ': ')));
    if (stripos($reason, 'Cron: is not enabled') === 0) {
        return 'Cron is switched off';
    }
    return rtrim($reason, ". \n\r\t");
};

$job_rows = [];
$sql = mysqli_query($mysqli, "SELECT cron_job_daily_at, cron_job_enabled, cron_job_id, cron_job_interval_minutes,
    cron_job_last_duration, cron_job_last_error, cron_job_last_error_at, cron_job_last_run_at, cron_job_last_status,
    cron_job_name, cron_job_run_now, cron_job_schedule FROM cron_jobs");
while ($job_row = mysqli_fetch_assoc($sql)) {
    $job_rows[$job_row['cron_job_name']] = $job_row;
}

/*
 * Each job the registry lists, with what its row says. How its last run went is one of:
 * queued (Run now, not picked up yet), running, lost (still Running hours later - it was cut
 * off, and nothing recorded how), completed, failed, skipped (it stopped itself early, and
 * says why), paused (by a manager), never.
 */
$jobs = [];
foreach (cronJobRegistry() as $position => $entry) {

    $job_row = $job_rows[$entry['name']] ?? null;

    $job = [
        'name' => $entry['name'],
        'label' => $entry['label'],
        'description' => $entry['description'],
        'script' => $entry['script'],
        'icon' => $entry['icon'] ?? 'fa-cog',
        'position' => $position,
        'id' => intval($job_row['cron_job_id'] ?? 0),
        'enabled' => $job_row ? intval($job_row['cron_job_enabled']) === 1 : intval($entry['enabled'] ?? 1) === 1,
        'schedule' => $job_row ? (string) $job_row['cron_job_schedule'] : (string) $entry['schedule'],
        'interval_minutes' => max(1, intval($job_row ? $job_row['cron_job_interval_minutes'] : ($entry['interval_minutes'] ?? 1))),
        'daily_at' => substr((string) ($job_row ? $job_row['cron_job_daily_at'] : ($entry['daily_at'] ?? '')), 0, 5),
        'last_run_at' => $job_row['cron_job_last_run_at'] ?? null,
        'last_duration' => $job_row['cron_job_last_duration'] ?? null,
        'last_error' => trim((string) ($job_row['cron_job_last_error'] ?? '')),
        'last_error_at' => $job_row['cron_job_last_error_at'] ?? null,
        'reason' => '',
    ];
    $job['schedule_text'] = cronJobScheduleDescription($job['schedule'], $job['interval_minutes'], $job['daily_at']);
    $job['every_minute'] = $job['schedule'] !== 'Daily' && $job['interval_minutes'] === 1;

    $status = (string) ($job_row['cron_job_last_status'] ?? '');
    if (!empty($job_row['cron_job_run_now'])) {
        $job['state'] = 'queued';
    } elseif ($status === 'Running') {
        $job['state'] = strtotime((string) $job['last_run_at']) > strtotime('-6 hours') ? 'running' : 'lost';
    } elseif ($status === 'Completed') {
        $job['state'] = 'completed';
    } elseif ($status === 'Failed') {
        $job['state'] = 'failed';
    } elseif ($status === '') {
        $job['state'] = 'never';
    } else {
        $job['reason'] = $tidy_reason(str_starts_with($status, 'Stopped') ? substr($status, 7) : $status);
        $job['state'] = 'skipped';
        if (str_starts_with($job['reason'], 'Paused')) {
            $job['state'] = 'paused';
            $job['reason'] = ucfirst(ltrim(substr($job['reason'], 6), ' -'));
        }
    }

    // When it runs next - nothing to say while cron isn't running, is switched off, or holds this job back
    $dispatched = $cron_heard && $cron_on && ($managed_mode === 'active' || in_array($job['name'], $managed_kept, true));
    $job['next_run'] = $job_row && $job['enabled'] && $dispatched ? cronJobNextRun($job_row) : null;

    $jobs[$job['name']] = $job;
}

$jobs_on = array_filter($jobs, function ($job) { return $job['enabled']; });
$jobs_off = count($jobs) - count($jobs_on);
// Failed on its last run - with an error, or cut off without a word
$jobs_failed = array_filter($jobs, function ($job) { return in_array($job['state'], ['failed', 'lost'], true); });
// Failed before and has run since - its error stays until somebody dismisses it
$jobs_recovered = array_filter($jobs, function ($job) { return $job['last_error'] !== '' && !in_array($job['state'], ['failed', 'lost'], true); });
// On their way: asked for, or started in the last half hour
$jobs_busy = array_filter($jobs, function ($job) {
    return $job['state'] === 'queued' || ($job['state'] === 'running' && strtotime((string) $job['last_run_at']) > strtotime('-30 minutes'));
});

// The next job that isn't one of the every-minute ones
$next_job = null;
foreach ($jobs as $job) {
    if ($job['next_run'] !== null && !$job['every_minute'] && (!$next_job || $job['next_run'] < $next_job['next_run'])) {
        $next_job = $job;
    }
}

/*
 * The one thing to say at the top. In this order, because each makes the ones after it moot:
 * cron not running at all, running but switched off, most of it held back by a manager, a
 * job that failed, then all well.
 */
if (!$cron_heard) {
    $state = empty($cron_last_dispatch_at) ? 'never' : 'stopped';
} elseif (!$cron_on) {
    $state = 'off';
} elseif ($managed_mode !== 'active') {
    $state = 'paused';
} elseif ($jobs_failed) {
    $state = 'failed';
} else {
    $state = 'running';
}

$failed_count = count($jobs_failed);
$hero = [
    'never' => ['icon' => 'fa-plug', 'tone' => 'warning', 'title' => "Cron hasn't run yet"],
    'stopped' => ['icon' => 'fa-exclamation-triangle', 'tone' => 'danger', 'title' => 'Cron has stopped'],
    'off' => ['icon' => 'fa-power-off', 'tone' => 'warning', 'title' => 'Cron is switched off'],
    'paused' => ['icon' => 'fa-pause', 'tone' => 'warning', 'title' => 'Most jobs are paused'],
    'failed' => ['icon' => 'fa-exclamation-triangle', 'tone' => 'danger', 'title' => $failed_count === 1 ? reset($jobs_failed)['label'] . ' failed' : "$failed_count jobs failed"],
    'running' => ['icon' => 'fa-check', 'tone' => 'success', 'title' => 'Cron is running'],
][$state];

// Reload while something is on its way, and while waiting to hear from cron at all
$reload_seconds = !$cron_heard ? 15 : ($jobs_busy ? 10 : 0);

// A time as "3 hours ago", with the date and time behind it
$ago = function ($datetime) {
    return '<span title="' . escapeHtml($datetime) . '">' . escapeHtml(timeAgo($datetime)) . '</span>';
};
// How long a run took
$took = function ($seconds) {
    $seconds = floatval($seconds);
    if ($seconds < 0.1) {
        return 'under 0.1 s';
    }
    if ($seconds < 10) {
        return rtrim(rtrim(number_format($seconds, 1), '0'), '.') . ' s';
    }
    if ($seconds < 60) {
        return round($seconds) . ' s';
    }
    $minutes = intdiv((int) round($seconds), 60);
    if ($minutes < 60) {
        return $minutes . ' min ' . ((int) round($seconds) % 60) . ' s';
    }
    return intdiv($minutes, 60) . ' h ' . ($minutes % 60) . ' min';
};
// Job names in a sentence - "Mail Queue", "Mail Queue and Backup", "Mail Queue, Backup and Nightly Tasks" - each a link to its row
$job_names = function ($list) {
    $links = array_map(function ($job) {
        return '<a href="#cron-job-' . escapeHtml($job['name']) . '">' . escapeHtml($job['label']) . '</a>';
    }, array_values($list));
    $last = array_pop($links);
    return $links ? implode(', ', $links) . ' and ' . $last : (string) $last;
};
// The crontab line, with a button that copies it
$command_block = function () use ($cron_command) {
    return '<div class="position-relative" data-cron-command>'
        . '<pre class="itflow-command bg-body-tertiary border rounded p-2 pe-5 mb-2 small"><code>' . escapeHtml($cron_command) . '</code></pre>'
        . '<button type="button" class="btn btn-sm btn-link position-absolute top-0 end-0 clipboardjs" title="Copy" data-clipboard-text="' . escapeHtml($cron_command) . '"><i class="far fa-copy"></i></button>'
        . '</div>';
};
$owner_note = $file_owner !== '' ? ' (<code>' . escapeHtml($file_owner) . '</code> here)' : '';

// The list - only these sort; anything else asked for falls back to the schedule
$sort_keys = [
    'job' => function ($job) { return strtolower($job['label']); },
    'schedule' => function ($job) { return $job['schedule'] === 'Daily' ? 100000 + intval(substr($job['daily_at'], 0, 2)) * 60 + intval(substr($job['daily_at'], 3, 2)) : $job['interval_minutes']; },
    'last_run' => function ($job) { return $job['last_run_at'] ? strtotime($job['last_run_at']) : 0; },
    // What needs a look first
    'status' => function ($job) { return array_search($job['state'], ['failed', 'lost', 'queued', 'running', 'paused', 'skipped', 'never', 'completed'], true); },
];
if (!isset($sort_keys[$sort])) {
    $sort = 'schedule';
}
uasort($jobs, function ($a, $b) use ($sort_keys, $sort, $order) {
    $compared = $sort_keys[$sort]($a) <=> $sort_keys[$sort]($b);
    return ($order === 'DESC' ? -$compared : $compared) ?: $a['position'] <=> $b['position'];
});
// A heading sorts its own way round first - names A to Z, the latest run and what needs a look at the top - then the other
$sort_first = ['job' => 'ASC', 'schedule' => 'ASC', 'last_run' => 'DESC', 'status' => 'ASC'];
$sort_link = function ($column, $label) use ($url_query_strings_sort, $sort, $disp, $order_icon, $sort_first) {
    return '<a class="text-reset text-decoration-none" href="?' . escapeHtml($url_query_strings_sort) . '&amp;sort=' . $column . '&amp;order=' . ($sort === $column ? $disp : $sort_first[$column]) . '">'
        . $label . ($sort === $column ? " $order_icon" : '') . '</a>';
};

$status_looks = [
    'queued' => ['label' => 'Queued', 'tone' => 'warning'],
    'running' => ['label' => 'Running', 'tone' => 'primary'],
    'lost' => ['label' => "Didn't finish", 'tone' => 'danger'],
    'completed' => ['label' => 'Completed', 'tone' => 'success'],
    'failed' => ['label' => 'Failed', 'tone' => 'danger'],
    'skipped' => ['label' => 'Skipped', 'tone' => 'secondary'],
    'paused' => ['label' => 'Paused', 'tone' => 'warning'],
    'never' => ['label' => 'Not run yet', 'tone' => 'secondary'],
];

?>

<div class="row g-3">

    <div class="col-lg-8">

        <!-- Whether cron is running, and what to do when it isn't -->
        <div class="card mb-3" data-cron-state="<?= $state ?>">
            <div class="card-body p-4"<?= $reload_seconds ? ' data-itflow-reload-seconds="' . $reload_seconds . '"' : '' ?>>
                <div class="d-flex align-items-start gap-3">
                    <span class="itflow-asset-icon itflow-asset-icon-lg rounded-circle bg-<?= $hero['tone'] ?>-subtle text-<?= $hero['tone'] ?>-emphasis" aria-hidden="true"><i class="fas <?= $hero['icon'] ?>"></i></span>
                    <div class="flex-grow-1 min-w-0">
                        <h2 class="h4 mb-1"><?= escapeHtml($hero['title']) ?></h2>

                        <?php if ($state === 'never') { ?>
                            <p class="mb-2 text-body-secondary">
                                ITFlow does its background work from one cron job on the server - sending email, turning email into tickets, recurring invoices, reminders, backups - and it hasn't heard from it yet.
                                Add this line to the crontab of the user that owns ITFlow's files<?= $owner_note ?>, or as a cron job that runs every minute in your hosting control panel:
                            </p>
                            <?= $command_block() ?>
                            <p class="mb-0 small text-body-secondary">This page says so by itself once it has run - give it a minute.</p>

                        <?php } elseif ($state === 'stopped') { ?>
                            <p class="mb-2 text-body-secondary">
                                ITFlow last heard from it <?= $ago($cron_last_dispatch_at) ?>. Until it runs again no email is sent, no email becomes a ticket, and nothing is invoiced or backed up.
                                Check that the server's cron service is running, and that this line is still in the crontab of the user that owns ITFlow's files<?= $owner_note ?>:
                            </p>
                            <?= $command_block() ?>
                            <p class="mb-0 small text-body-secondary">This page says so by itself when it's back.</p>

                        <?php } elseif ($state === 'off') { ?>
                            <p class="mb-0 text-body-secondary">
                                The server still calls it every minute, but each job stops itself straight away - no email is sent, no email becomes a ticket, and nothing is invoiced or backed up.
                                That's right for a restored backup or a test copy, which mustn't email your clients or charge their cards. Anywhere else, turn it back on - every job's schedule is as it was left.
                            </p>

                        <?php } elseif ($state === 'paused') { ?>
                            <p class="mb-0 text-body-secondary">
                                <?= escapeHtml(ucfirst(managedManagerName())) ?> has made this ITFlow <?= $managed_mode === 'suspended' ? 'suspended' : 'read-only' ?>.
                                <?= $job_names(array_intersect_key($jobs, array_flip($managed_kept))) ?> keep running; the rest wait, and run when it's active again.
                            </p>

                        <?php } elseif ($state === 'failed') { ?>
                            <p class="mb-0 text-body-secondary">
                                Cron itself is running - it last ran <?= $ago($cron_last_dispatch_at) ?> - but <?= $job_names($jobs_failed) ?> didn't finish <?= $failed_count === 1 ? 'its' : 'their' ?> last run.
                                What went wrong is under <?= $failed_count === 1 ? 'it' : 'each' ?> below.
                            </p>

                        <?php } else { ?>
                            <p class="mb-0 text-body-secondary">
                                It last ran <?= $ago($cron_last_dispatch_at) ?>. <?= count($jobs_on) ?> job<?= count($jobs_on) === 1 ? ' is' : 's are' ?> on<?= $jobs_off ? ", $jobs_off off" : '' ?>, and none failed on its last run.
                                <?php if ($next_job) { ?>
                                    Next is <?= $job_names([$next_job]) ?>, <?= $next_job['next_run'] <= date('Y-m-d H:i:s') ? 'within a minute' : $ago($next_job['next_run']) ?>.
                                <?php } ?>
                            </p>
                        <?php } ?>

                        <?php if ($jobs_recovered && in_array($state, ['failed', 'running'], true)) { ?>
                            <p class="mb-0 mt-2 small text-body-secondary" data-cron-recovered>
                                <i class="fas fa-fw fa-history me-1"></i><?= $job_names($jobs_recovered) ?> failed earlier and <?= count($jobs_recovered) === 1 ? 'has' : 'have' ?> run since - what went wrong is kept below until you dismiss it.
                            </p>
                        <?php } ?>

                        <?php if ($jobs_busy && $cron_heard) { ?>
                            <p class="mb-0 mt-2 small text-body-secondary" data-cron-busy>
                                <i class="fas fa-fw fa-circle-notch fa-spin me-1"></i><?= $job_names($jobs_busy) ?> <?= count($jobs_busy) === 1 ? 'is' : 'are' ?> on <?= count($jobs_busy) === 1 ? 'its' : 'their' ?> way - this page keeps itself up to date.
                            </p>
                        <?php } ?>

                        <?php if (!$cron_on) { ?>
                            <?php if ($state !== 'off') { ?>
                                <p class="mb-0 mt-2 small text-warning-emphasis"><i class="fas fa-fw fa-exclamation-circle me-1"></i>Cron is switched off here as well, so its jobs do nothing when it runs until it's turned on.</p>
                            <?php } ?>
                            <div class="d-flex flex-wrap gap-2 mt-3">
                                <a class="btn <?= $state === 'off' ? 'btn-primary' : 'btn-outline-primary' ?>" href="post.php?enable_cron=1&amp;csrf_token=<?= $csrf ?>" data-cron-switch="on">
                                    <i class="fas fa-fw fa-power-off me-2"></i>Turn cron on
                                </a>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Every job: what it does, its schedule, and how its last run went -->
        <div class="card mb-3">
            <div class="card-header py-3">
                <h3 class="card-title"><i class="fas fa-fw fa-tasks me-2"></i>Jobs</h3>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0" data-cron-jobs>
                    <thead class="table-light text-nowrap">
                    <tr>
                        <th class="ps-3" colspan="2"><?= $sort_link('job', 'Job') ?></th>
                        <th class="d-none d-md-table-cell"><?= $sort_link('schedule', 'Schedule') ?></th>
                        <th class="d-none d-md-table-cell"><?= $sort_link('last_run', 'Last run') ?></th>
                        <th><?= $sort_link('status', 'Status') ?></th>
                        <th></th>
                    </tr>
                    </thead>
                    <?php foreach ($jobs as $job) {
                        $look = $status_looks[$job['state']];
                        $failing = in_array($job['state'], ['failed', 'lost'], true);
                        $icon_tone = $failing ? 'bg-danger-subtle text-danger-emphasis' : ($job['enabled'] ? 'itflow-asset-tone-peripheral' : 'itflow-asset-tone-other opacity-50');
                        // Why a skipped run was skipped - but not the switch, twelve times over, while the top of the page is saying it
                        $reason = $job['reason'] === 'Cron is switched off' && !$cron_on ? '' : $job['reason'];
                        ?>
                        <tbody id="cron-job-<?= escapeHtml($job['name']) ?>" data-cron-job="<?= escapeHtml($job['name']) ?>" data-cron-status="<?= $job['state'] ?>" data-cron-enabled="<?= $job['enabled'] ? 1 : 0 ?>">
                        <tr>
                            <td class="ps-3 pt-3 align-top d-none d-sm-table-cell" rowspan="2">
                                <span class="itflow-asset-icon <?= $icon_tone ?>" aria-hidden="true"><i class="fas <?= escapeHtml($job['icon']) ?>"></i></span>
                            </td>
                            <td class="border-bottom-0 pt-3 pb-1 text-nowrap">
                                <span class="fw-semibold<?= $job['enabled'] ? '' : ' text-body-secondary' ?>"><?= escapeHtml($job['label']) ?></span>
                                <?php if (!$job['enabled']) { ?>
                                    <span class="badge bg-secondary-subtle text-secondary-emphasis ms-1" title="It doesn't run on its schedule. Run now still works.">Off</span>
                                <?php } ?>
                            </td>
                            <td class="border-bottom-0 pt-3 pb-1 text-nowrap d-none d-md-table-cell<?= $job['enabled'] ? '' : ' text-body-secondary' ?>">
                                <?= escapeHtml($job['schedule_text']) ?>
                                <?php if ($job['next_run'] !== null && !$job['every_minute']) { ?>
                                    <div class="small text-body-secondary" title="<?= escapeHtml($job['next_run']) ?>">Next <?= $job['next_run'] <= date('Y-m-d H:i:s') ? 'within a minute' : escapeHtml(timeAgo($job['next_run'])) ?></div>
                                <?php } ?>
                            </td>
                            <td class="border-bottom-0 pt-3 pb-1 text-nowrap d-none d-md-table-cell">
                                <?php if ($job['last_run_at']) { ?>
                                    <?= $ago($job['last_run_at']) ?>
                                    <?php if ($job['last_duration'] !== null && !in_array($job['state'], ['running', 'lost'], true)) { ?>
                                        <div class="small text-body-secondary">Took <?= escapeHtml($took($job['last_duration'])) ?></div>
                                    <?php } ?>
                                <?php } else { ?>
                                    <span class="text-body-secondary">&mdash;</span>
                                <?php } ?>
                            </td>
                            <td class="border-bottom-0 pt-3 pb-1">
                                <span class="badge bg-<?= $look['tone'] ?>-subtle text-<?= $look['tone'] ?>-emphasis"><?php if ($job['state'] === 'running') { ?><i class="fas fa-circle-notch fa-spin me-1"></i><?php } ?><?= escapeHtml($look['label']) ?></span>
                            </td>
                            <td class="border-bottom-0 pt-3 pb-1 text-end pe-3">
                                <?php if ($job['id']) { ?>
                                    <div class="dropdown dropstart d-inline-block">
                                        <button class="btn btn-sm btn-light" title="More actions" type="button" data-bs-toggle="dropdown"><i class="fas fa-fw fa-ellipsis-v"></i></button>
                                        <div class="dropdown-menu">
                                            <?php if (in_array($job['state'], ['queued', 'running'], true)) { ?>
                                                <span class="dropdown-item disabled"><i class="fas fa-fw fa-play me-2"></i><?= $job['state'] === 'queued' ? 'Queued to run' : 'Running now' ?></span>
                                            <?php } else { ?>
                                                <a class="dropdown-item" href="post.php?run_cron_job=<?= $job['id'] ?>&amp;csrf_token=<?= $csrf ?>" data-cron-run>
                                                    <i class="fas fa-fw fa-play text-secondary me-2"></i>Run now
                                                </a>
                                            <?php } ?>
                                            <button class="dropdown-item ajax-modal" type="button" data-modal-url="modals/cron/cron_edit.php?id=<?= $job['id'] ?>" data-cron-edit>
                                                <i class="fas fa-fw fa-clock text-secondary me-2"></i>Change the schedule
                                            </button>
                                            <button type="button" class="dropdown-item clipboardjs" data-clipboard-text="<?= escapeHtml($by_hand($job['script'])) ?>" title="For running it yourself on the server, where it shows what it does as it goes">
                                                <i class="fas fa-fw fa-terminal text-secondary me-2"></i>Copy the command to run it
                                            </button>
                                            <div class="dropdown-divider"></div>
                                            <?php if ($job['enabled']) { ?>
                                                <a class="dropdown-item text-danger confirm-link" href="post.php?disable_cron_job=<?= $job['id'] ?>&amp;csrf_token=<?= $csrf ?>" data-cron-off
                                                    data-confirm-title="Turn <?= escapeHtml($job['label']) ?> off?" data-confirm-text="It stops running on its schedule until it's turned back on. Run now still works." data-confirm-button="Turn it off">
                                                    <i class="fas fa-fw fa-power-off me-2"></i>Turn off
                                                </a>
                                            <?php } else { ?>
                                                <a class="dropdown-item" href="post.php?enable_cron_job=<?= $job['id'] ?>&amp;csrf_token=<?= $csrf ?>" data-cron-on>
                                                    <i class="fas fa-fw fa-power-off text-secondary me-2"></i>Turn on
                                                </a>
                                            <?php } ?>
                                        </div>
                                    </div>
                                <?php } ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="pt-0 pb-3 pe-3" colspan="5">
                                <div class="small text-body-secondary"><?= escapeHtml($job['description']) ?></div>
                                <?php // On a phone the Schedule and Last run columns go, and sit here ?>
                                <div class="small text-body-secondary mt-1 d-md-none">
                                    <i class="fas fa-fw fa-clock me-1"></i><?= escapeHtml($job['schedule_text']) ?><?= $job['last_run_at'] ? ' &middot; last run ' . escapeHtml(timeAgo($job['last_run_at'])) : '' ?>
                                </div>

                                <?php if ($job['state'] === 'queued') { ?>
                                    <div class="small text-body-secondary mt-1"><i class="fas fa-fw fa-hourglass-half me-1"></i>Asked to run now - <?= $cron_heard ? 'cron picks it up within a minute' : 'it runs as soon as cron does' ?>.</div>
                                <?php } elseif ($job['state'] === 'lost') { ?>
                                    <div class="small text-danger-emphasis mt-1"><i class="fas fa-fw fa-exclamation-triangle me-1"></i>It started <?= $ago($job['last_run_at']) ?> and never said how it ended - most likely cut off by a restart or a time limit. It runs again when it's next due, or sooner with Run now.</div>
                                <?php } elseif ($job['state'] === 'skipped' && $job['reason'] === '') { ?>
                                    <div class="small text-body-secondary mt-1"><i class="fas fa-fw fa-info-circle me-1"></i>It stopped early without saying why - <a href="app_logs.php">App Logs</a> may have it.</div>
                                <?php } elseif ($reason !== '') { ?>
                                    <div class="small text-body-secondary mt-1 text-break" data-cron-reason><i class="fas fa-fw fa-info-circle me-1"></i><?= escapeHtml($reason) ?></div>
                                <?php } ?>

                                <?php if ($job['last_error'] !== '') { ?>
                                    <div class="d-flex align-items-start gap-2 small rounded bg-danger-subtle text-danger-emphasis p-2 mt-2" data-cron-error>
                                        <i class="fas fa-fw fa-exclamation-triangle mt-1" aria-hidden="true"></i>
                                        <div class="flex-grow-1 min-w-0">
                                            <div class="fw-semibold">
                                                Failed <?= $job['last_error_at'] ? $ago($job['last_error_at']) : 'earlier' ?><?= $failing ? '' : ' <span class="fw-normal">- it has run since</span>' ?>
                                            </div>
                                            <div class="text-break"><?= escapeHtml($job['last_error']) ?></div>
                                        </div>
                                        <a class="flex-shrink-0 link-danger" href="post.php?clear_cron_error=<?= $job['id'] ?>&amp;csrf_token=<?= $csrf ?>" data-cron-dismiss>Dismiss</a>
                                    </div>
                                <?php } ?>
                            </td>
                        </tr>
                        </tbody>
                    <?php } ?>
                </table>
            </div>
            <div class="card-footer small text-body-secondary">
                <strong>Run now</strong> doesn't run a job in your browser - cron picks it up within a minute, and this page shows how it went. What each job did is in <a href="app_logs.php">App Logs</a>.
            </div>
        </div>

    </div>

    <div class="col-lg-4">

        <!-- Cron, at a glance -->
        <div class="card mb-3">
            <div class="card-header py-3">
                <h3 class="card-title"><i class="fas fa-fw fa-heartbeat me-2"></i>At a glance</h3>
            </div>
            <ul class="list-group list-group-flush small" data-cron-glance>
                <li class="list-group-item d-flex justify-content-between gap-3">
                    <span class="text-body-secondary">Cron</span>
                    <span class="<?= $cron_heard && $cron_on ? 'text-success-emphasis' : 'text-danger-emphasis' ?>">
                        <i class="fas fa-fw fa-<?= $cron_heard && $cron_on ? 'check-circle' : 'times-circle' ?> me-1"></i><?= !$cron_heard ? 'Not running' : ($cron_on ? 'Running' : 'Switched off') ?>
                    </span>
                </li>
                <li class="list-group-item d-flex justify-content-between gap-3">
                    <span class="text-body-secondary">Last ran</span>
                    <span class="text-end fw-semibold<?= $cron_heard ? '' : ' text-danger-emphasis' ?>"><?= empty($cron_last_dispatch_at) ? 'Never' : $ago($cron_last_dispatch_at) ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between gap-3">
                    <span class="text-body-secondary">Jobs</span>
                    <span class="text-end fw-semibold"><?= count($jobs_on) ?> on<?= $jobs_off ? ", $jobs_off off" : '' ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between gap-3">
                    <span class="text-body-secondary">Failed</span>
                    <span class="text-end fw-semibold<?= $jobs_failed ? ' text-danger-emphasis' : '' ?>"><?= $jobs_failed ? $job_names($jobs_failed) : 'None' ?></span>
                </li>
                <?php if ($next_job) { ?>
                    <li class="list-group-item d-flex justify-content-between gap-3">
                        <span class="text-body-secondary">Next</span>
                        <span class="text-end">
                            <span class="fw-semibold"><?= escapeHtml($next_job['label']) ?></span>
                            <span class="d-block text-body-secondary" title="<?= escapeHtml($next_job['next_run']) ?>"><?= $next_job['next_run'] <= date('Y-m-d H:i:s') ? 'within a minute' : escapeHtml(timeAgo($next_job['next_run'])) ?></span>
                        </span>
                    </li>
                <?php } ?>
                <li class="list-group-item d-flex justify-content-between gap-3">
                    <span class="text-body-secondary">Times are in</span>
                    <span class="text-end">
                        <a href="settings_localization.php" class="fw-semibold"><?= escapeHtml($session_timezone) ?></a>
                        <span class="d-block text-body-secondary"><?= date('H:i') ?> now</span>
                    </span>
                </li>
            </ul>
        </div>

        <?php if ($cron_heard) { // not running, the card at the top has the line ?>
        <!-- The one crontab line everything on this page runs from -->
        <div class="card mb-3">
            <div class="card-header py-3">
                <h3 class="card-title"><i class="fas fa-fw fa-terminal me-2"></i>The cron job</h3>
            </div>
            <div class="card-body small">
                <p class="text-body-secondary">Everything on this page runs from one line in the server's crontab - that of the user that owns ITFlow's files<?= $owner_note ?>:</p>
                <?= $command_block() ?>
                <p class="text-body-secondary mb-0">It runs every minute and works out which jobs are due, so changing a job's schedule here never means changing it. On a hosting control panel, it's a cron job that runs every minute.</p>
            </div>
        </div>
        <?php } ?>

        <?php if ($cron_on) { // off, the card at the top turns it back on ?>
        <!-- The switch that stops every job at once -->
        <div class="card mb-3">
            <div class="card-header py-3">
                <h3 class="card-title"><i class="fas fa-fw fa-power-off me-2"></i>Turning cron off</h3>
            </div>
            <div class="card-body small">
                <p class="text-body-secondary">Stops every job at once, without touching their schedules. It's for a restored backup or a test copy, which would otherwise email your clients and charge their cards. Turning it back on picks up exactly where this leaves off.</p>
                <a class="btn btn-sm btn-light border text-danger confirm-link" href="post.php?disable_cron=1&amp;csrf_token=<?= $csrf ?>" data-cron-switch="off"
                    data-confirm-title="Turn cron off?" data-confirm-text="No email is sent, no email becomes a ticket, and nothing is invoiced or backed up until it's turned back on." data-confirm-button="Turn it off">
                    <i class="fas fa-fw fa-power-off me-2"></i>Turn cron off
                </a>
            </div>
        </div>
        <?php } ?>

    </div>

</div>

<script src="<?= staticFileUrl('/js/auto_reload.js') ?>"></script>

<?php require_once "../includes/footer.php"; ?>
