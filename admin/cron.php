<?php

require_once "includes/inc_all_admin.php";
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/cron_jobs.php';

$row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT config_enable_cron, config_cron_last_dispatch_at FROM settings WHERE company_id = 1"));

$config_enable_cron = intval($row['config_enable_cron']);
$cron_last_dispatch_at = $row['config_cron_last_dispatch_at'];

// The dispatcher writes its heartbeat before it runs anything, so anything older than a few
// minutes means the crontab entry itself is missing or failing - a different problem from a
// job that is disabled or erroring, and the one people spend the longest not finding.
$cron_is_running = $cron_last_dispatch_at !== null && (time() - strtotime($cron_last_dispatch_at)) < 300;

$cron_command = "* * * * * php " . dirname(__DIR__) . "/cron/cron.php >/dev/null";

// Registry order is dispatch order, so the table reads the way the cycle runs
$cron_jobs = [];
foreach (cronJobRegistry() as $job) {
    $cron_jobs[$job['name']] = $job;
    $cron_jobs[$job['name']]['row'] = null;
}

$sql = mysqli_query($mysqli, "SELECT cron_job_name FROM cron_jobs");
while ($job_row = mysqli_fetch_assoc($sql)) {
    if (isset($cron_jobs[$job_row['cron_job_name']])) {
        $cron_jobs[$job_row['cron_job_name']]['row'] = $job_row;
    }
}

?>

<div class="card card-dark">
    <div class="card-header py-3">
        <h3 class="card-title"><i class="fas fa-fw fa-clock mr-2"></i>Cron</h3>
    </div>
    <div class="card-body">

        <?php if (!$cron_is_running) { ?>
            <div class="alert alert-danger">
                <h5><i class="fas fa-fw fa-exclamation-triangle mr-2"></i>Cron is not running</h5>
                ITFlow last heard from cron <strong><?= escapeHtml(strtolower(cronJobTimeAgo($cron_last_dispatch_at))) ?></strong>.
                Nothing below will run - no mail is being sent, no email is being turned into tickets, and invoices are not being generated.
                Add this line to the crontab of the user that owns the ITFlow files:
                <pre class="bg-dark text-white p-2 mt-2 mb-0"><?= escapeHtml($cron_command) ?></pre>
            </div>
        <?php } else { ?>
            <div class="alert alert-success">
                <i class="fas fa-fw fa-check mr-2"></i>Cron last checked in <strong><?= escapeHtml(strtolower(cronJobTimeAgo($cron_last_dispatch_at))) ?></strong>.
                <span class="text-muted ml-2"><?= escapeHtml($cron_command) ?></span>
            </div>
        <?php } ?>

        <?php if ($config_enable_cron == 0) { ?>
            <div class="alert alert-warning">
                <div class="float-right">
                    <a class="btn btn-sm btn-warning" href="post.php?enable_cron=1&amp;csrf_token=<?= $_SESSION['csrf_token'] ?>">
                        <i class="fas fa-fw fa-power-off mr-2"></i>Turn cron on
                    </a>
                </div>
                <h5><i class="fas fa-fw fa-exclamation-circle mr-2"></i>Cron is switched off</h5>
                The dispatcher is running, but every job below stops itself immediately while this is off -
                no mail is sent, no email becomes a ticket, and nothing is invoiced.
            </div>
        <?php } else { ?>
            <p class="text-muted">
                <small>
                    <i class="fas fa-fw fa-power-off mr-1"></i>The master switch is <strong>on</strong>. Turning it off
                    stops every job at once without touching their schedules, which is what you want on a restored
                    backup or a staging clone - those come up with every job enabled and will otherwise email clients
                    and charge cards. Switching back on returns you to exactly this configuration.
                    <a href="post.php?disable_cron=1&amp;csrf_token=<?= $_SESSION['csrf_token'] ?>">Turn cron off</a>.
                </small>
            </p>
        <?php } ?>

        <div class="table-responsive-sm">
            <table class="table table-borderless table-hover">
                <thead class="text-secondary">
                    <tr>
                        <th>Job</th>
                        <th>Schedule</th>
                        <th>Last Run</th>
                        <th>Duration</th>
                        <th>Status</th>
                        <th>Next Run</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($cron_jobs as $job) {

                    $job_row = $job['row'];

                    // A job the dispatcher has not met yet has no row. It gets one on the next
                    // pass, seeded from the schedule in the registry, so show that meanwhile.
                    $cron_job_id = $job_row ? intval($job_row['cron_job_id']) : 0;
                    $enabled = $job_row ? intval($job_row['cron_job_enabled']) : 1;
                    $schedule = $job_row ? $job_row['cron_job_schedule'] : $job['schedule'];
                    $interval_minutes = $job_row ? intval($job_row['cron_job_interval_minutes']) : intval($job['interval_minutes'] ?? 1);
                    $daily_at = $job_row ? $job_row['cron_job_daily_at'] : ($job['daily_at'] ?? null);
                    $run_now = $job_row ? intval($job_row['cron_job_run_now']) : 0;
                    $last_run_at = $job_row ? $job_row['cron_job_last_run_at'] : null;
                    $last_duration = $job_row ? $job_row['cron_job_last_duration'] : null;
                    $last_status = $job_row ? $job_row['cron_job_last_status'] : null;
                    $last_error = $job_row ? $job_row['cron_job_last_error'] : null;
                    $last_error_at = $job_row ? $job_row['cron_job_last_error_at'] : null;

                    $next_run = $job_row ? cronJobNextRun($job_row) : null;

                    if ($run_now) {
                        $status_badge = '<span class="badge badge-warning">Queued</span>';
                    } elseif ($last_status === 'Running') {
                        $status_badge = '<span class="badge badge-info">Running</span>';
                    } elseif ($last_status === 'Completed') {
                        $status_badge = '<span class="badge badge-success">Completed</span>';
                    } elseif ($last_status === 'Failed') {
                        $status_badge = '<span class="badge badge-danger">Failed</span>';
                    } elseif ($last_status !== null) {
                        $status_badge = '<span class="badge badge-secondary">Stopped</span>';
                    } else {
                        $status_badge = '<span class="badge badge-light">Never run</span>';
                    }

                    ?>
                    <tr class="<?= $enabled ? '' : 'text-muted' ?>">
                        <td>
                            <strong><?= escapeHtml($job['label']) ?></strong>
                            <?php if (!$enabled) { ?><span class="badge badge-secondary ml-1">Disabled</span><?php } ?>
                            <br><small class="text-secondary"><?= escapeHtml($job['description']) ?></small>
                            <br><small class="text-muted"><code>cron/<?= escapeHtml($job['script']) ?></code></small>
                        </td>
                        <td><?= escapeHtml(cronJobScheduleDescription($schedule, $interval_minutes, $daily_at)) ?></td>
                        <td>
                            <?= escapeHtml(cronJobTimeAgo($last_run_at)) ?>
                            <?php if ($last_run_at) { ?><br><small class="text-muted"><?= escapeHtml(date('M j, g:i A', strtotime($last_run_at))) ?></small><?php } ?>
                        </td>
                        <td><?= $last_duration === null ? '-' : escapeHtml($last_duration) . 's' ?></td>
                        <td>
                            <?= $status_badge ?>
                            <?php if ($last_status !== null && $last_status !== 'Running' && strlen($last_status) > 9) { ?>
                                <br><small class="text-muted"><?= escapeHtml($last_status) ?></small>
                            <?php } ?>
                        </td>
                        <td><?= $next_run === null ? '-' : escapeHtml(cronJobTimeAgo($next_run)) ?></td>
                        <td class="text-center">
                            <div class="dropdown dropleft text-center">
                                <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                                <div class="dropdown-menu">
                                    <a class="dropdown-item <?= $cron_job_id === 0 ? 'disabled' : '' ?>" href="post.php?run_cron_job=<?= $cron_job_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                        <i class="fas fa-fw fa-play mr-2"></i>Run Now
                                    </a>
                                    <button class="dropdown-item ajax-modal <?= $cron_job_id === 0 ? 'disabled' : '' ?>" type="button" data-toggle="ajax-modal"
                                            data-modal-url="modals/cron/cron_edit.php?id=<?= $cron_job_id ?>">
                                        <i class="fas fa-fw fa-edit mr-2"></i>Edit Schedule
                                    </button>
                                    <?php if ($enabled) { ?>
                                        <a class="dropdown-item text-danger" href="post.php?disable_cron_job=<?= $cron_job_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                            <i class="fas fa-fw fa-pause mr-2"></i>Disable
                                        </a>
                                    <?php } else { ?>
                                        <a class="dropdown-item text-success" href="post.php?enable_cron_job=<?= $cron_job_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                            <i class="fas fa-fw fa-play-circle mr-2"></i>Enable
                                        </a>
                                    <?php } ?>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php if (!empty($last_error)) { ?>
                        <tr>
                            <td colspan="7" class="pt-0">
                                <div class="alert alert-danger mb-0 py-2">
                                    <div class="float-right">
                                        <a class="text-danger" href="post.php?clear_cron_error=<?= $cron_job_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" title="Dismiss">
                                            <i class="fas fa-fw fa-times"></i>
                                        </a>
                                    </div>
                                    <strong><i class="fas fa-fw fa-exclamation-triangle mr-2"></i>Last error</strong>
                                    <small class="text-muted ml-2"><?= escapeHtml(cronJobTimeAgo($last_error_at)) ?></small>
                                    <div class="mt-1"><small><?= escapeHtml($last_error) ?></small></div>
                                </div>
                            </td>
                        </tr>
                    <?php } ?>
                <?php } ?>
                </tbody>
            </table>
        </div>

        <p class="text-muted mb-0">
            <small>
                <i class="fas fa-fw fa-info-circle mr-1"></i>Run Now does not start the job in your browser - it asks the
                dispatcher to pick it up on its next pass, so a job starts within a minute and still runs on the command
                line with the same locking as a scheduled run. Detailed per-job output is in
                <a href="app_logs.php">App Logs</a>.
            </small>
        </p>

    </div>
</div>

<?php require_once "../includes/footer.php"; ?>
