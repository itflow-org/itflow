<?php
require_once "includes/inc_all_admin.php";

require_once "../includes/database_version.php";

$repo_branch = getRepoBranch();
$remote_ref = escapeshellarg("origin/$repo_branch");

/*
 * Everything git can tell us needs a shell. Where PHP cannot run one - shared hosting, a
 * hardened php.ini, an FPM pool locked down while the command line is not - the page cannot
 * say whether an update is waiting and the web server cannot apply one either, so the
 * application half of this page is replaced by the queue, which cron carries out. The
 * database half is plain PHP and works everywhere.
 */
$shell_available = shellCommandsAvailable();

$current_version = '';
$fetch_ok = true;
$updates = null;

// Commits sitting between this working tree and the remote branch. Fields are separated
// by \x1f rather than having git build the table markup, because a commit subject comes
// from outside this install and used to reach the page as unescaped HTML.
$pending_commits = [];

if ($shell_available) {

    $updates = checkForUpdates();

    $current_version = $updates->current_version;
    $fetch_ok = $updates->result === 0;

    $git_log = shell_exec("git log HEAD..$remote_ref --pretty=format:'%h%x1f%ar%x1f%s'");

    foreach (explode("\n", trim((string) $git_log)) as $commit_line) {

        if ($commit_line === '') {
            continue;
        }

        $commit_fields = explode("\x1f", $commit_line, 3);

        if (count($commit_fields) === 3) {
            $pending_commits[] = $commit_fields;
        }

    }

}

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
$app_update_available = !empty($pending_commits);

?>

<div class="card card-dark">
    <div class="card-header py-3">
        <h3 class="card-title"><i class="fas fa-fw fa-download me-2"></i>Update</h3>
    </div>
    <div class="card-body">

        <?php if ($shell_available && !$fetch_ok) { ?>
            <div class="alert alert-danger">
                <h5><i class="fas fa-fw fa-exclamation-triangle me-2"></i>Cannot reach the Git remote</h5>
                ITFlow updates itself with Git, so nothing below is current until this is fixed.
                <?php if (!empty($updates->output)) { ?>
                    <pre class="bg-dark text-white p-2 mt-2 mb-2"><?= escapeHtml(implode("\n", $updates->output)) ?></pre>
                <?php } ?>
                Check that Git is installed, that the remote is reachable from this server, and that the web server
                user can write to the ITFlow directory. The
                <a href="https://forum.itflow.org" class="alert-link" target="_blank">forum</a> can help - include
                your PHP error log and the output above.
            </div>
        <?php } ?>

        <?php if (!$shell_available) { ?>
            <div class="alert alert-info">
                <h5><i class="fas fa-fw fa-info-circle me-2"></i>This server cannot run Git from the web</h5>
                PHP here has <code>exec</code> and <code>shell_exec</code> disabled, so this page can neither check for
                application updates nor apply one. Queue an update instead: cron runs it from the command line, which
                is usually not restricted the same way. Database updates are plain PHP and are unaffected.
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

        <?php if ($shell_available && !$app_update_available && !$db_update_available) { ?>

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

            <?php if ($app_update_available && $db_update_available) { ?>
                <p class="text-muted">
                    <i class="fas fa-fw fa-info-circle me-1"></i>Both are pending. Update the application files first -
                    they bring the database migrations that the second step then applies.
                </p>
            <?php } ?>

            <?php if ($app_update_available || !$shell_available) { ?>
                <div class="mb-4">
                    <h6 class="text-uppercase text-secondary">Application files</h6>
                    <?php if ($app_update_available) { ?>
                        <p class="mb-2">
                            <?= count($pending_commits) ?> commit<?= count($pending_commits) === 1 ? '' : 's' ?>
                            behind <code><?= escapeHtml("origin/$repo_branch") ?></code>.
                        </p>
                    <?php } else { ?>
                        <p class="mb-2">
                            This server cannot check <code><?= escapeHtml("origin/$repo_branch") ?></code>, so there may
                            or may not be anything waiting. Queueing an update when there is nothing to do is harmless.
                        </p>
                    <?php } ?>

                    <?php if ($shell_available) { ?>
                        <a class="btn btn-primary confirm-link" href="post.php?update&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                            <i class="fas fa-fw fa-download me-2"></i>Update App
                        </a>
                        <a class="btn btn-outline-danger ms-2 confirm-link" href="post.php?update&force_update=1&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                            <i class="fas fa-fw fa-hammer me-2"></i>Force Update
                        </a>
                    <?php } ?>

                    <?php if ($update_queue_available) { ?>
                    <a class="btn btn-dark <?= $shell_available ? 'ms-2' : '' ?> confirm-link" href="post.php?queue_update&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                        <i class="fas fa-fw fa-clock me-2"></i>Queue Update
                    </a>
                    <?php } ?>

                    <p class="text-muted mt-2 mb-0">
                        <small>
                            <?php if ($shell_available) { ?>
                                Update App runs <code>git pull</code>. Force Update discards every local change and resets
                                the files to <code><?= escapeHtml("origin/$repo_branch") ?></code> - use it only when a
                                normal update will not apply. Both run inside this request and stop if PHP runs out of time.
                            <?php } ?>
                            <?php if ($update_queue_available) { ?>
                                Queue Update hands the job to cron, which runs
                                <code>scripts/update_cli.php</code> in its own process - it updates the files and then the
                                database, with no request timeout, as the user that owns the files. It resets the files to
                                <code><?= escapeHtml("origin/$repo_branch") ?></code>, so local changes to them are lost.
                                <?php if (!$cron_is_running) { ?>
                                    <strong class="text-warning">Cron is not checking in, so a queued update will sit there
                                    until it is.</strong>
                                <?php } ?>
                            <?php } else { ?>
                                <strong>Apply the database update below first.</strong> Queueing an update needs a schema
                                change this install has not caught up with yet. From a shell,
                                <code>php scripts/update_cli.php</code> does both in one step.
                            <?php } ?>
                        </small>
                    </p>
                </div>
            <?php } ?>

            <?php if ($db_update_available) { ?>
                <div class="mb-4">
                    <h6 class="text-uppercase text-secondary">Database</h6>
                    <p class="mb-2">
                        Schema is at <strong><?= escapeHtml(CURRENT_DATABASE_VERSION) ?></strong> and this code expects
                        <strong><?= escapeHtml(LATEST_DATABASE_VERSION) ?></strong>. Parts of the app will error until
                        this is applied.
                    </p>
                    <a class="btn btn-dark confirm-link" href="post.php?update_db&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                        <i class="fas fa-fw fa-database me-2"></i>Update Database
                    </a>
                    <p class="text-muted mt-2 mb-0">
                        <small>
                            A large instance can take a minute or more. If it fails part way it stops without advancing
                            the recorded version, so it is safe to run again after fixing the cause.
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
                                <td class="text-nowrap"><?= escapeHtml($commit[1]) ?></td>
                                <td><?= escapeHtml($commit[2]) ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        <?php } ?>

    </div>
</div>

<?php

require_once "../includes/footer.php";
