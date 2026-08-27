<?php
require_once "includes/inc_all_admin.php";

require_once "../includes/database_version.php";

$repo_branch = getRepoBranch();

/*
 * This page does not run git. cron/update_check.php does the fetch on its own schedule and
 * stores what it found; everything below is a read of that, plus .git for the local commit.
 * Check Now asks the dispatcher for a run rather than checking inside the request, so the
 * page works the same on a host whose web PHP cannot run external commands at all.
 */
$current_version = gitCurrentCommit();

// The stored answer arrives with a migration, and this page has to render on an install
// whose files are newer than its schema
$update_check_available = settingsColumnExists($mysqli, 'config_update_latest_commit');

$latest_version = '';
$update_checked_at = null;
$pending_commits = [];
$check_job = null;

if ($update_check_available) {

    $update_check_row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT config_update_latest_commit, config_update_pending_commits, config_update_checked_at FROM settings WHERE company_id = 1"));

    $latest_version = (string) ($update_check_row['config_update_latest_commit'] ?? '');
    $update_checked_at = $update_check_row['config_update_checked_at'] ?? null;

    // Stored as JSON by the job: [[short hash, ISO date, subject], ...]. Rebuilt element by
    // element rather than trusted wholesale - it is the shape the table indexes into
    $stored_commits = json_decode((string) ($update_check_row['config_update_pending_commits'] ?? ''), true);

    if (is_array($stored_commits)) {
        foreach ($stored_commits as $stored_commit) {
            if (is_array($stored_commit) && count($stored_commit) === 3) {
                $pending_commits[] = array_values($stored_commit);
            }
        }
    }

    $check_job = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT cron_job_run_now, cron_job_last_error, cron_job_last_error_at FROM cron_jobs WHERE cron_job_name = 'update_check' LIMIT 1"));

}

// The dispatcher clears run_now inside the same UPDATE that claims the job, so this is true
// for exactly as long as the request is outstanding
$check_in_progress = !empty($check_job['cron_job_run_now']);

/*
 * The queue. Maintenance > Update writes config_update_queued_at and asks the dispatcher for
 * an immediate run; cron/app_update.php takes the request and runs scripts/update_cli.php in
 * its own process. Neither column is in the global settings load - one page needs them.
 *
 * The column is checked for rather than assumed. This page has to render on an install whose
 * files are newer than its schema, because that is the state it exists to get people out of.
 */
$update_queue_available = settingsColumnExists($mysqli, 'config_update_queued_at');

$update_queued_at = null;
$cron_last_dispatch_at = null;
$update_job = null;

if ($update_queue_available) {

    $update_cron_row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT config_update_queued_at, config_cron_last_dispatch_at FROM settings WHERE company_id = 1"));

    $update_queued_at = $update_cron_row['config_update_queued_at'] ?? null;
    $cron_last_dispatch_at = $update_cron_row['config_cron_last_dispatch_at'] ?? null;

    $update_job = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT cron_job_last_run_at, cron_job_last_status, cron_job_last_error, cron_job_last_error_at FROM cron_jobs WHERE cron_job_name = 'app_update' LIMIT 1"));

}

// Queueing is only worth offering if something is going to pick the request up
$cron_is_running = $update_queue_available
    && !empty($config_enable_cron)
    && !empty($cron_last_dispatch_at)
    && strtotime($cron_last_dispatch_at) > strtotime('-5 minutes');

// version_compare, not > - "2.6.10" is less than "2.6.9" as a plain string comparison, so
// the plain comparison silently stops offering database updates once a minor reaches 10.
$db_update_available = version_compare(LATEST_DATABASE_VERSION, CURRENT_DATABASE_VERSION, '>');

// Derived from the two commits, not from the list: a force-push or a local commit leaves the
// working tree at a different place with nothing to list, and that is still "not up to date"
$app_update_available = $latest_version !== ''
    && $current_version !== ''
    && $latest_version !== $current_version;

?>

<div class="card card-dark">
    <div class="card-header py-3">
        <h3 class="card-title"><i class="fas fa-fw fa-download me-2"></i>Update</h3>
    </div>
    <div class="card-body">

        <?php if (!empty($check_job['cron_job_last_error'])) { ?>
            <div class="alert alert-danger">
                <h5><i class="fas fa-fw fa-exclamation-triangle me-2"></i>The last update check did not finish</h5>
                Anything below is from the check before it, so it may be out of date.
                <pre class="bg-dark text-white p-2 mt-2 mb-2"><?= escapeHtml($check_job['cron_job_last_error']) ?></pre>
                Recorded <?= escapeHtml((string) $check_job['cron_job_last_error_at']) ?>. Check that Git is installed
                and that the remote is reachable from this server, then clear the error from
                <a href="cron.php" class="alert-link">Maintenance &gt; Cron</a>. The
                <a href="https://forum.itflow.org" class="alert-link" target="_blank">forum</a> can help - include the
                output above.
            </div>
        <?php } ?>

        <?php if ($check_in_progress) { ?>
            <?php /* data-itflow-reload-seconds is read by js/auto_reload.js, loaded at the foot
                     of this page. The dispatcher clears run_now when it claims the job, so one
                     reload a few seconds later is normally enough to land on the result. */ ?>
            <div class="alert alert-info" data-itflow-reload-seconds="15">
                <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                <strong>Checking for updates.</strong>
                <?php if ($cron_is_running) { ?>
                    Cron runs the check within a minute; this page refreshes itself.
                <?php } else { ?>
                    <strong class="text-warning">Nothing is going to pick it up</strong> - cron has not checked in
                    recently or the master switch is off. See
                    <a href="cron.php" class="alert-link">Maintenance &gt; Cron</a>.
                <?php } ?>
            </div>
        <?php } ?>

        <div class="row">
            <div class="col-md-3 col-6 mb-3">
                <small class="text-secondary text-uppercase">Release</small>
                <div class="h5 mb-0"><?= escapeHtml(APP_VERSION) ?></div>
            </div>
            <div class="col-md-3 col-6 mb-3">
                <small class="text-secondary text-uppercase">Branch</small>
                <div class="h5 mb-0"><?= escapeHtml($repo_branch) ?></div>
                <?php if ($repo_branch !== 'master') { ?>
                    <small class="text-warning">Not the release branch</small>
                <?php } ?>
            </div>
            <div class="col-md-3 col-6 mb-3">
                <small class="text-secondary text-uppercase">Database</small>
                <div class="h5 mb-0">
                    <?= escapeHtml(CURRENT_DATABASE_VERSION) ?>
                    <?php if ($db_update_available) { ?>
                        <small class="text-danger">&rarr; <?= escapeHtml(LATEST_DATABASE_VERSION) ?></small>
                    <?php } ?>
                </div>
            </div>
            <div class="col-md-3 col-6 mb-3">
                <small class="text-secondary text-uppercase">Commit</small>
                <div class="h5 mb-0"><code><?= $current_version === '' ? '&mdash;' : escapeHtml(substr((string) $current_version, 0, 7)) ?></code></div>
            </div>
        </div>

        <?php /* Outside the pending block on purpose - re-checking has to be reachable from the
                 up-to-date view too, which is where somebody goes when they have just read that
                 a release is out. */ ?>
        <p class="text-muted mb-3">
            <small>
                <?php if (!empty($update_checked_at)) { ?>
                    Last checked <?= escapeHtml(timeAgo($update_checked_at)) ?>
                    (<?= escapeHtml($update_checked_at) ?>).
                <?php } else { ?>
                    Never checked.
                <?php } ?>
                <?php if ($update_check_available && !$check_in_progress) { ?>
                    <a href="post.php?check_update&csrf_token=<?= $_SESSION['csrf_token'] ?>">Check now</a>
                <?php } elseif ($check_in_progress) { ?>
                    <span class="text-secondary">Checking&hellip;</span>
                <?php } ?>
            </small>
        </p>

        <?php if (!empty($update_queued_at)) { ?>
            <div class="alert alert-info">
                <i class="fas fa-fw fa-clock me-2"></i>An update was queued at
                <strong><?= escapeHtml($update_queued_at) ?></strong>.
                <?php if ($cron_is_running) { ?>
                    Cron will start it within a minute; this page will show the new version once it finishes.
                <?php } else { ?>
                    <strong>Nothing is going to pick it up</strong> - cron has not checked in recently or the master
                    switch is off. See <a href="cron.php" class="alert-link">Maintenance &gt; Cron</a>.
                <?php } ?>
            </div>
        <?php } ?>

        <?php if (!empty($update_job['cron_job_last_error'])) { ?>
            <div class="alert alert-warning">
                <h5><i class="fas fa-fw fa-exclamation-triangle me-2"></i>The last queued update did not finish</h5>
                <pre class="bg-dark text-white p-2 mt-2 mb-2"><?= escapeHtml($update_job['cron_job_last_error']) ?></pre>
                Recorded <?= escapeHtml((string) $update_job['cron_job_last_error_at']) ?>. Clear it from
                <a href="cron.php" class="alert-link">Maintenance &gt; Cron</a> once it has been dealt with.
            </div>
        <?php } ?>

        <hr>

        <?php if (!empty($update_checked_at) && !$app_update_available && !$db_update_available) { ?>

            <div class="text-center py-3">
                <i class="far fa-3x fa-smile-wink text-dark"></i>
                <p class="mt-3 mb-0"><strong>You are up to date</strong></p>
                <p class="text-muted">Everything is going to be alright.</p>
            </div>

            <?php if (rand(1, 10) == 1) { ?>
                <div class="alert alert-info alert-dismissible fade show mb-0" role="alert">
                    You are up to date, but when did you last check that your backup restores?
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php } ?>

        <?php } else { ?>

            <div class="alert alert-danger">
                <h5><i class="fas fa-fw fa-exclamation-triangle me-2"></i>Do not update without a backup</h5>
                A VM snapshot is the safest option - other methods are covered in the
                <a href="https://docs.itflow.org/backups" class="alert-link" target="_blank">docs</a>. Read the
                <a href="https://github.com/itflow-org/itflow/blob/master/CHANGELOG.md" class="alert-link" target="_blank">changelog</a>
                first: some releases need manual steps, and this page will not do them for you.
            </div>

            <?php /* One action covers both halves - scripts/update_cli.php always runs the database
                     phase, whether or not the file phase moved anything - so the two are reported
                     together under one button rather than as separate steps. The db_update_available
                     arm of the condition matters: a schema behind its code with nothing to pull is
                     exactly the state that needs queueing, and without it the button would not draw. */ ?>
            <?php if ($app_update_available || $db_update_available || empty($update_checked_at)) { ?>
                <div class="mb-4">
                    <h6 class="text-uppercase text-secondary">Pending</h6>

                    <?php if ($app_update_available) { ?>
                        <p class="mb-2">
                            <strong>Application files:</strong>
                            <?= count($pending_commits) ?> commit<?= count($pending_commits) === 1 ? '' : 's' ?>
                            behind <code><?= escapeHtml("origin/$repo_branch") ?></code>.
                        </p>
                    <?php } elseif (empty($update_checked_at)) { ?>
                        <p class="mb-2">
                            <strong>Application files:</strong> this install has not checked
                            <code><?= escapeHtml("origin/$repo_branch") ?></code> yet, so there may or may not be
                            anything waiting. Queueing an update when there is nothing to do is harmless.
                        </p>
                    <?php } ?>

                    <?php if ($db_update_available) { ?>
                        <p class="mb-2">
                            <strong>Database:</strong> schema is at
                            <strong><?= escapeHtml(CURRENT_DATABASE_VERSION) ?></strong> and this code expects
                            <strong><?= escapeHtml(LATEST_DATABASE_VERSION) ?></strong>. Parts of the app will error
                            until the update runs.
                        </p>
                    <?php } ?>

                    <?php if ($update_queue_available) { ?>
                    <a class="btn btn-primary confirm-link" href="post.php?queue_update&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                        <i class="fas fa-fw fa-clock me-2"></i>Queue Update
                    </a>
                    <?php } ?>

                    <p class="text-muted mt-2 mb-0">
                        <small>
                            <?php if ($update_queue_available) { ?>
                                Queue Update hands the job to cron, which runs
                                <code>scripts/update_cli.php</code> in its own process - it updates the files and then
                                applies every pending migration, with no request timeout, as the user that owns the
                                files. It resets the files to <code><?= escapeHtml("origin/$repo_branch") ?></code>, so
                                local changes to them are lost. A migration that fails part way stops without advancing
                                the recorded version, so it is safe to queue again once the cause is fixed.
                                <?php if (!$cron_is_running) { ?>
                                    <strong class="text-warning">Cron is not checking in, so a queued update will sit there
                                    until it is.</strong>
                                <?php } ?>
                            <?php } else { ?>
                                <strong>This install cannot queue yet.</strong> Queueing needs a schema change it has not
                                caught up with, so this one has to be started from a shell:
                                <code>php scripts/update_cli.php</code>, as the user that owns the files. It updates the
                                files and the database in one step, and Queue Update works from then on.
                            <?php } ?>
                        </small>
                    </p>
                </div>
            <?php } ?>

        <?php } ?>

        <?php if ($app_update_available) { ?>
            <h6 class="text-uppercase text-secondary mt-4">Pending commits</h6>
            <div class="table-responsive-sm">
                <table class="table table-borderless table-hover">
                    <thead class="text-secondary">
                        <tr>
                            <th>Commit</th>
                            <th>When</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending_commits as $commit) { ?>
                            <tr>
                                <td><code><?= escapeHtml($commit[0]) ?></code></td>
                                <?php /* stored as an absolute ISO date by the check job - a stored
                                         "2 hours ago" would be wrong the moment it was written */ ?>
                                <td class="text-nowrap"><?= escapeHtml(timeAgo($commit[1])) ?></td>
                                <td><?= escapeHtml($commit[2]) ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        <?php } ?>

    </div>
</div>

<script src="../js/auto_reload.js"></script>

<?php

require_once "../includes/footer.php";
