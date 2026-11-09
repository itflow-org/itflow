<?php
require_once "includes/inc_all_admin.php";

require_once "../includes/database_version.php";

/*
 * Maintenance > Update. One answer at the top - up to date, an update waiting, one running,
 * the database still to update - with the one button that acts on it, then what the update
 * brings and what to do first, and this install's details alongside.
 *
 * This page does not run git. cron/update_check.php does the fetch on its own schedule and
 * stores what it found; everything below is a read of that, plus .git for the local commit.
 * Check Now and Update ITFlow ask the dispatcher for a run rather than working inside the
 * request, so the page works the same on a host whose web PHP cannot run external commands.
 *
 * Every settings column it reads is checked for first. This page has to render on an install
 * whose files are newer than its schema, because that is the state it exists to get people
 * out of - mysqli throws on an unknown column, and the page would die before its button drew.
 */

$repo_branch = getRepoBranch();
$current_version = gitCurrentCommit();
$is_git_checkout = gitDir() !== '';

$update_check_available = settingsColumnExists($mysqli, 'config_update_latest_commit');
$update_details_available = settingsColumnExists($mysqli, 'config_update_details');
$update_queue_available = settingsColumnExists($mysqli, 'config_update_queued_at');

$latest_version = '';
$update_checked_at = null;
$pending_commits = [];
$check_job = null;
$details = [];

if ($update_check_available) {

    $update_check_row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT config_update_latest_commit, config_update_pending_commits, config_update_checked_at"
        . ($update_details_available ? ", config_update_details" : '') . " FROM settings WHERE company_id = 1"));

    $latest_version = (string) ($update_check_row['config_update_latest_commit'] ?? '');
    $update_checked_at = $update_check_row['config_update_checked_at'] ?? null;

    // Stored as JSON by the job: [[short hash, ISO date, subject], ...]. Rebuilt element by
    // element rather than trusted wholesale - it is the shape the list indexes into
    $stored_commits = json_decode((string) ($update_check_row['config_update_pending_commits'] ?? ''), true);
    if (is_array($stored_commits)) {
        foreach ($stored_commits as $stored_commit) {
            if (is_array($stored_commit) && count($stored_commit) === 3) {
                $pending_commits[] = array_map('strval', array_values($stored_commit));
            }
        }
    }

    $details = json_decode((string) ($update_check_row['config_update_details'] ?? ''), true);
    $details = is_array($details) ? $details : [];

    $check_job = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT cron_job_run_now, cron_job_last_run_at, cron_job_last_status, cron_job_last_error, cron_job_last_error_at
        FROM cron_jobs WHERE cron_job_name = 'update_check' LIMIT 1"));

}

// What the check found besides the commits (updateCheckDetails()) - each part checked for shape
$latest_release = is_string($details['version'] ?? null) ? $details['version'] : '';
$changelog = [];
foreach ((array) ($details['changelog'] ?? []) as $section) {
    if (is_array($section) && count($section) === 2 && is_string($section[0]) && is_string($section[1])) {
        $changelog[] = $section;
    }
}
$local_changes = array_values(array_filter((array) ($details['local_changes'] ?? []), 'is_string'));
$local_changes_count = intval($details['local_changes_count'] ?? count($local_changes));
$details_known = isset($details['local_changes_count']);

$update_queued_at = null;
$cron_last_dispatch_at = null;
$update_job = null;

if ($update_queue_available) {

    $update_queued_at = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT config_update_queued_at FROM settings WHERE company_id = 1"))['config_update_queued_at'] ?? null;

    $update_job = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT cron_job_last_run_at, cron_job_last_finished_at, cron_job_last_status, cron_job_last_error, cron_job_last_error_at
        FROM cron_jobs WHERE cron_job_name = 'app_update' LIMIT 1"));

}

// The dispatcher's heartbeat - queueing is only worth offering if something will pick it up
if (settingsColumnExists($mysqli, 'config_cron_last_dispatch_at')) {
    $cron_last_dispatch_at = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT config_cron_last_dispatch_at FROM settings WHERE company_id = 1"))['config_cron_last_dispatch_at'] ?? null;
}
$cron_is_running = !empty($config_enable_cron)
    && !empty($cron_last_dispatch_at)
    && strtotime($cron_last_dispatch_at) > strtotime('-5 minutes');

// version_compare, not > - "2.6.10" is less than "2.6.9" as a plain string comparison
$db_update_available = version_compare(LATEST_DATABASE_VERSION, CURRENT_DATABASE_VERSION, '>');

// Derived from the two commits, not from the list: a force-push or a local commit leaves the
// working tree at a different place with nothing to list, and that is still "not up to date"
$app_update_available = $latest_version !== ''
    && $current_version !== ''
    && $latest_version !== $current_version;

// The dispatcher clears run_now inside the same UPDATE that claims the job, so this is true
// for exactly as long as the request is outstanding
// A job left at Running long past its time died without being recorded - not still going
$job_running = function ($job, $minutes) {
    return ($job['cron_job_last_status'] ?? '') === 'Running' && !empty($job['cron_job_last_run_at'])
        && strtotime($job['cron_job_last_run_at']) > strtotime("-$minutes minutes");
};
$check_in_progress = !empty($check_job['cron_job_run_now']) || $job_running($check_job, 10);
$check_failed = ($check_job['cron_job_last_status'] ?? '') === 'Failed' && !empty($check_job['cron_job_last_error']);
$check_stopped = str_starts_with((string) ($check_job['cron_job_last_status'] ?? ''), 'Stopped: ')
    ? substr((string) $check_job['cron_job_last_status'], 9) : '';

$update_status = (string) ($update_job['cron_job_last_status'] ?? '');
$update_running = !empty($update_queued_at) || $job_running($update_job, 30);
$update_failed = !$update_running && $update_status === 'Failed' && !empty($update_job['cron_job_last_error']);
$update_finished = !$update_running && $update_status === 'Completed' && !empty($update_job['cron_job_last_finished_at'])
    && strtotime($update_job['cron_job_last_finished_at']) > strtotime('-1 hour');

// A release number to show when the branch moves by release (master), or nothing to compare
$new_release = $latest_release !== '' && version_compare($latest_release, APP_VERSION, '>') ? $latest_release : '';
$commit_count = count($pending_commits);
$has_upgrading_notes = (bool) array_filter($changelog, function ($section) {
    return preg_match('/^###\s+Upgrading/mi', $section[1]) === 1;
});

/*
 * The one thing to say at the top. In this order, because each hides the ones after it: an
 * update already on its way, a check on its way, a database behind its code (parts of ITFlow
 * error until it catches up), an update waiting, then the quiet states.
 */
if ($update_running) {
    $state = 'updating';
} elseif ($check_in_progress) {
    $state = 'checking';
} elseif ($db_update_available) {
    $state = 'database';
} elseif ($app_update_available) {
    $state = 'available';
} elseif (!$is_git_checkout) {
    $state = 'manual';
} elseif (empty($update_checked_at)) {
    $state = 'unknown';
} else {
    $state = 'current';
}

// A managed instance is updated by its manager, not from here (functions/managed.php)
$updates_managed = managedLocks()['updates'];
$updates_policy = managedState();

// Update ITFlow where there is something to update (or no telling), Check where a check can run
$show_update_button = in_array($state, ['database', 'available', 'unknown'], true) && $update_queue_available && !$updates_managed;
$show_check_button = in_array($state, ['available', 'unknown', 'current'], true) && $update_check_available && $is_git_checkout;

$queue_url = "post.php?queue_update&csrf_token=" . urlencode($_SESSION['csrf_token']);
$check_url = "post.php?check_update&csrf_token=" . urlencode($_SESSION['csrf_token']);

// What the confirmation says - the parts that differ from one install to the next first
$confirm_text = "Cron updates ITFlow's files and then its database in the background, within a minute.";
if ($local_changes_count > 0) {
    $confirm_text .= " The $local_changes_count file" . ($local_changes_count === 1 ? '' : 's') . " changed on this install will be put back to the released version" . ($local_changes_count === 1 ? '' : 's') . ".";
}
$confirm_text .= " Make sure you have a backup first.";

// The command line, as this server runs it - the user that owns the files, in this folder
$install_root = dirname(__DIR__);
$file_owner = '';
if (function_exists('posix_getpwuid')) {
    $file_owner = (string) (posix_getpwuid((int) @fileowner($install_root . '/scripts/update_cli.php'))['name'] ?? '');
}
$shell_word = function ($word) {
    return preg_match('/^[A-Za-z0-9_\/.@%+=:,-]+$/', $word) ? $word : escapeshellarg($word);
};
$cli_command = "cd " . $shell_word($install_root) . "\n" . ($file_owner !== '' ? "sudo -u " . $shell_word($file_owner) . " " : '') . "php scripts/update_cli.php";

$hero = [
    'updating' => ['icon' => 'fa-sync-alt fa-spin', 'tone' => 'primary', 'title' => 'Updating ITFlow'],
    'checking' => ['icon' => 'fa-circle-notch fa-spin', 'tone' => 'secondary', 'title' => 'Checking for updates'],
    'database' => ['icon' => 'fa-database', 'tone' => 'warning', 'title' => 'Finish updating ITFlow'],
    'available' => ['icon' => 'fa-arrow-alt-circle-up', 'tone' => 'primary', 'title' => $new_release !== '' ? "ITFlow $new_release is available" : 'An update is available'],
    'manual' => ['icon' => 'fa-box-open', 'tone' => 'secondary', 'title' => 'This install is updated by hand'],
    'unknown' => ['icon' => 'fa-question', 'tone' => 'secondary', 'title' => 'ITFlow has not checked for updates yet'],
    'current' => ['icon' => 'fa-check', 'tone' => 'success', 'title' => 'ITFlow is up to date'],
][$state];

?>

<?php if ($update_failed) { ?>
    <div class="alert alert-danger">
        <h5 class="alert-heading"><i class="fas fa-fw fa-exclamation-triangle me-2"></i>The last update did not finish</h5>
        <p class="mb-2">It stopped <?= escapeHtml(timeAgo($update_job['cron_job_last_error_at'] ?: $update_job['cron_job_last_finished_at'])) ?>. A database update that fails part way stops without recording its version, so once the cause is fixed it is safe to run the update again.</p>
        <pre class="bg-dark text-white p-2 mb-2 small text-wrap"><?= escapeHtml($update_job['cron_job_last_error']) ?></pre>
        <p class="mb-0 small">The <a href="https://forum.itflow.org" class="alert-link" target="_blank" rel="noopener">forum</a> can help - include the output above.</p>
    </div>
<?php } ?>

<?php if ($update_finished && !$update_failed) { ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="fas fa-fw fa-check-circle me-2"></i>ITFlow was updated <?= escapeHtml(timeAgo($update_job['cron_job_last_finished_at'])) ?> - this is <?= escapeHtml(APP_VERSION) ?><?= $current_version !== '' ? ', commit <code>' . escapeHtml(substr($current_version, 0, 7)) . '</code>' : '' ?>.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php } ?>

<?php if ($check_failed) { ?>
    <div class="alert alert-warning">
        <h5 class="alert-heading"><i class="fas fa-fw fa-exclamation-triangle me-2"></i>The last update check did not finish</h5>
        <p class="mb-2">What this page says about updates is from the check before it, so it may be out of date. Check that Git is installed and that this server can reach GitHub.</p>
        <pre class="bg-dark text-white p-2 mb-0 small text-wrap"><?= escapeHtml($check_job['cron_job_last_error']) ?></pre>
    </div>
<?php } ?>

<div class="row g-3">

    <div class="col-lg-8">

        <!-- Where this install stands, and the one thing to do about it -->
        <div class="card mb-3" data-update-state="<?= $state ?>">
            <div class="card-body p-4"<?= in_array($state, ['updating', 'checking'], true) ? ' data-itflow-reload-seconds="10"' : '' ?>>
                <div class="d-flex align-items-start gap-3">
                    <span class="itflow-asset-icon itflow-asset-icon-lg rounded-circle bg-<?= $hero['tone'] ?>-subtle text-<?= $hero['tone'] ?>-emphasis" aria-hidden="true"><i class="fas <?= $hero['icon'] ?>"></i></span>
                    <div class="flex-grow-1 min-w-0">
                        <h2 class="h4 mb-1"><?= escapeHtml($hero['title']) ?></h2>

                        <?php if ($state === 'updating') { ?>
                            <p class="mb-0 text-body-secondary">
                                <?php if (!empty($update_queued_at)) { ?>
                                    Requested <?= escapeHtml(timeAgo($update_queued_at)) ?>.
                                <?php } else { ?>
                                    Started <?= escapeHtml(timeAgo($update_job['cron_job_last_run_at'])) ?>.
                                <?php } ?>
                                Cron updates the files and then the database in the background - usually a minute or two.
                                You can leave this page; you'll get a notification when it's done.
                            </p>
                            <?php if (!$cron_is_running) { ?>
                                <p class="mb-0 mt-2 text-danger-emphasis"><i class="fas fa-fw fa-exclamation-circle me-1"></i>Cron hasn't run in the last five minutes, or it's switched off, so nothing will pick the update up until it does. See <a href="cron.php">Maintenance &gt; Cron</a>.</p>
                            <?php } ?>

                        <?php } elseif ($state === 'checking') { ?>
                            <p class="mb-0 text-body-secondary">Cron checks within a minute; this page refreshes itself.</p>
                            <?php if (!$cron_is_running) { ?>
                                <p class="mb-0 mt-2 text-danger-emphasis"><i class="fas fa-fw fa-exclamation-circle me-1"></i>Cron hasn't run in the last five minutes, or it's switched off, so the check won't happen until it does. See <a href="cron.php">Maintenance &gt; Cron</a>.</p>
                            <?php } ?>

                        <?php } elseif ($state === 'database') { ?>
                            <p class="mb-0 text-body-secondary">
                                ITFlow's files are newer than its database - the database is at <strong><?= escapeHtml(CURRENT_DATABASE_VERSION) ?></strong>
                                and these files need <strong><?= escapeHtml(LATEST_DATABASE_VERSION) ?></strong>. Parts of ITFlow may show errors until it catches up.
                                <?php if ($app_update_available) { ?>A newer version is waiting too, and comes with it.<?php } ?>
                            </p>

                        <?php } elseif ($state === 'available') { ?>
                            <p class="mb-0 text-body-secondary">
                                <?php if ($new_release !== '') { ?>
                                    You're on <?= escapeHtml(APP_VERSION) ?>.
                                <?php } else { ?>
                                    <?= $commit_count ?: 'New' ?> change<?= $commit_count === 1 ? '' : 's' ?> on <strong><?= escapeHtml($repo_branch) ?></strong> since this copy<?= $repo_branch !== 'master' ? ' - not a release, as this install follows ' . escapeHtml($repo_branch) : '' ?>.
                                <?php } ?>
                                See what's new below, and take a backup first.
                            </p>

                        <?php } elseif ($state === 'manual') { ?>
                            <p class="mb-0 text-body-secondary">
                                It wasn't installed with Git, so it can't fetch new versions itself. Put the new release's files in place by hand, as the
                                <a href="https://docs.itflow.org/updates" target="_blank" rel="noopener">docs</a> describe - this page then updates the database.
                            </p>

                        <?php } elseif ($state === 'unknown') { ?>
                            <p class="mb-0 text-body-secondary">
                                <?php if ($check_stopped !== '') { ?>
                                    The last check stopped: <?= escapeHtml(rtrim($check_stopped, ".\n")) ?>.
                                <?php } else { ?>
                                    The Update Check job does it every night.<?= $show_check_button ? ' Check now to find out straight away.' : '' ?>
                                <?php } ?>
                            </p>

                        <?php } else { ?>
                            <p class="mb-0 text-body-secondary">You're on <?= escapeHtml(APP_VERSION) ?><?= $repo_branch !== 'master' ? ', following ' . escapeHtml($repo_branch) : '' ?>. Everything is going to be alright.</p>
                            <?php if (rand(1, 10) === 1) { ?>
                                <p class="mb-0 mt-1 small text-body-secondary"><i class="fas fa-fw fa-life-ring me-1"></i>While you're here - when did you last check that your backup restores?</p>
                            <?php } ?>
                        <?php } ?>

                        <?php if ($updates_managed && in_array($state, ['database', 'available', 'unknown', 'current'], true)) { ?>
                            <p class="mb-0 mt-2 small text-body-secondary" data-update-managed>
                                <i class="fas fa-fw fa-network-wired me-1"></i><strong><?= escapeHtml(managedManagerName()) ?></strong> manages this ITFlow, so it is updated from there<?php
                                if ($updates_policy['update_policy'] === 'auto') {
                                    echo ' - automatically, ' . ($updates_policy['update_delay_days'] > 0 ? $updates_policy['update_delay_days'] . ' day' . ($updates_policy['update_delay_days'] === 1 ? '' : 's') . ' after a release comes out' : 'as soon as a release comes out') . ', and straight away for security fixes';
                                } ?>.
                            </p>
                        <?php } ?>

                        <?php if ($show_update_button || $show_check_button) { ?>
                        <div class="d-flex flex-wrap gap-2 mt-3">
                        <?php if ($show_update_button) { ?>
                            <a class="btn <?= $state === 'unknown' ? 'btn-outline-primary' : 'btn-primary' ?> confirm-link" href="<?= escapeHtml($queue_url) ?>" data-update-button
                                data-confirm-title="<?= $state === 'database' ? 'Finish updating ITFlow?' : 'Update ITFlow?' ?>" data-confirm-text="<?= escapeHtml($confirm_text) ?>" data-confirm-button="Update" data-confirm-icon="question">
                                <i class="fas fa-fw fa-download me-2"></i><?= $state === 'database' ? 'Finish updating' : 'Update ITFlow' ?>
                            </a>
                        <?php } ?>
                        <?php if ($show_check_button) { ?>
                            <a class="btn <?= $state === 'available' ? 'btn-light' : ($state === 'unknown' ? 'btn-primary' : 'btn-outline-secondary') ?>" href="<?= escapeHtml($check_url) ?>">
                                <i class="fas fa-fw fa-sync-alt me-2"></i><?= $state === 'available' ? 'Check again' : 'Check now' ?>
                            </a>
                        <?php } ?>
                        </div>
                        <?php } ?>
                    </div>
                </div>

                <?php if (in_array($state, ['database', 'available', 'unknown'], true) && !$update_queue_available) { ?>
                    <?php /* The queue arrives with a migration, so an install this far behind its own
                             files has one way out, and it's the shell */ ?>
                    <div class="alert alert-warning mt-3 mb-0 small">
                        <strong>This install has to be updated from a shell once.</strong> Updating from this page needs a database change it
                        hasn't caught up with yet. Run the command under <em>From the command line</em>, and this page works from then on.
                    </div>
                <?php } elseif (in_array($state, ['database', 'available'], true) && !$cron_is_running) { ?>
                    <div class="alert alert-warning mt-3 mb-0 small">
                        <i class="fas fa-fw fa-exclamation-circle me-1"></i>Cron hasn't run in the last five minutes, or it's switched off, so an update would wait until it does.
                        Check <a href="cron.php" class="alert-link">Maintenance &gt; Cron</a>, or update from the command line.
                    </div>
                <?php } ?>
            </div>

            <?php if (in_array($state, ['database', 'available'], true)) { ?>
                <!-- What to do first. The update itself puts files back and changes the database,
                     and none of it can be undone from here -->
                <div class="list-group list-group-flush border-top" data-update-checklist>
                    <div class="list-group-item d-flex gap-3 py-3">
                        <i class="fas fa-fw fa-hdd text-secondary mt-1" aria-hidden="true"></i>
                        <div>
                            <div class="fw-semibold">Take a backup</div>
                            <div class="small text-body-secondary">A snapshot of the whole server is safest - you can roll back in a minute. Otherwise make a Full Backup in
                                <a href="backup.php">Maintenance &gt; Backup</a> and copy it off the server.</div>
                        </div>
                    </div>
                    <div class="list-group-item d-flex gap-3 py-3">
                        <i class="fas fa-fw fa-clipboard-list text-secondary mt-1" aria-hidden="true"></i>
                        <div>
                            <div class="fw-semibold">Read what's changing</div>
                            <div class="small text-body-secondary">
                                <?php if ($has_upgrading_notes) { ?>
                                    Each release's <strong>Upgrading</strong> notes below say whether it needs a step of its own - ITFlow won't do those for you.
                                <?php } elseif ($changelog) { ?>
                                    What's new is below. Some releases need a step of their own, and say so.
                                <?php } else { ?>
                                    Some releases need a step of their own - the
                                    <a href="https://github.com/itflow-org/itflow/blob/<?= escapeHtml(rawurlencode($repo_branch)) ?>/CHANGELOG.md" target="_blank" rel="noopener">changelog</a> says.
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                    <?php if ($details_known) { ?>
                        <div class="list-group-item d-flex gap-3 py-3<?= $local_changes_count > 0 ? ' bg-warning-subtle' : '' ?>" data-update-local-changes="<?= $local_changes_count ?>">
                            <i class="fas fa-fw fa-<?= $local_changes_count > 0 ? 'exclamation-triangle text-warning-emphasis' : 'code-branch text-secondary' ?> mt-1" aria-hidden="true"></i>
                            <div class="min-w-0">
                                <?php if ($local_changes_count > 0) { ?>
                                    <div class="fw-semibold"><?= $local_changes_count ?> of ITFlow's files <?= $local_changes_count === 1 ? 'has' : 'have' ?> been changed on this install</div>
                                    <div class="small text-body-secondary">Updating puts back the released version<?= $local_changes_count === 1 ? '' : 's' ?>, so <?= $local_changes_count === 1 ? 'that change is' : 'those changes are' ?> lost. Your config.php, uploads and custom folders are never touched.</div>
                                    <details class="small mt-1">
                                        <summary>Which files</summary>
                                        <ul class="mb-0 mt-1 font-monospace">
                                            <?php foreach ($local_changes as $path) { ?>
                                                <li class="text-break"><?= escapeHtml($path) ?></li>
                                            <?php } ?>
                                            <?php if ($local_changes_count > count($local_changes)) { ?>
                                                <li class="list-unstyled text-body-secondary">and <?= $local_changes_count - count($local_changes) ?> more</li>
                                            <?php } ?>
                                        </ul>
                                    </details>
                                <?php } else { ?>
                                    <div class="fw-semibold">Nothing of yours is overwritten</div>
                                    <div class="small text-body-secondary">None of ITFlow's own files have been changed on this install. Your config.php, uploads and custom folders are never touched.</div>
                                <?php } ?>
                            </div>
                        </div>
                    <?php } ?>
                    <div class="list-group-item d-flex gap-3 py-3">
                        <i class="fas fa-fw fa-clock text-secondary mt-1" aria-hidden="true"></i>
                        <div>
                            <div class="fw-semibold">Pick a quiet time</div>
                            <div class="small text-body-secondary">Your team may see errors for a minute or two while it runs.</div>
                        </div>
                    </div>
                </div>
            <?php } ?>
        </div>

        <?php if ($app_update_available && ($changelog || $pending_commits)) { ?>
            <!-- What the update brings - the release notes, then the commits behind them -->
            <div class="card mb-3">
                <div class="card-header py-3">
                    <h3 class="card-title"><i class="fas fa-fw fa-gift me-2"></i>What's new</h3>
                </div>
                <div class="card-body itflow-changelog">
                    <?php if ($changelog) { ?>
                        <?php /* Newest first, open; the releases between it and this install folded
                                 away under their version, as an install far behind has several */ ?>
                        <?php foreach ($changelog as $index => [$section_version, $section_markdown]) { ?>
                            <?php if ($index === 0) { ?>
                                <section data-changelog-version="<?= escapeHtml($section_version) ?>">
                                    <h4 class="h5 mb-2">
                                        <?= escapeHtml($section_version) ?>
                                        <?php if ($section_version === APP_VERSION) { ?>
                                            <span class="badge bg-secondary-subtle text-secondary-emphasis fw-normal align-middle ms-1">The version you're on, with changes since</span>
                                        <?php } ?>
                                    </h4>
                                    <?= updateChangelogHtml($section_markdown) ?>
                                </section>
                            <?php } else { ?>
                                <details class="border-top pt-3 mt-3" data-changelog-version="<?= escapeHtml($section_version) ?>">
                                    <summary class="h5 mb-0"><?= escapeHtml($section_version) ?></summary>
                                    <div class="mt-2"><?= updateChangelogHtml($section_markdown) ?></div>
                                </details>
                            <?php } ?>
                        <?php } ?>
                    <?php } else { ?>
                        <p class="text-body-secondary mb-0">The release notes are in the
                            <a href="https://github.com/itflow-org/itflow/blob/<?= escapeHtml(rawurlencode($repo_branch)) ?>/CHANGELOG.md" target="_blank" rel="noopener">changelog</a>.
                            Check again to see them here.</p>
                    <?php } ?>
                </div>
                <?php if ($pending_commits) { ?>
                    <details class="card-footer bg-body py-0" data-update-commits>
                        <summary class="py-3 text-body-secondary"><?= $commit_count ?> commit<?= $commit_count === 1 ? '' : 's' ?> on <?= escapeHtml("origin/$repo_branch") ?></summary>
                        <ul class="list-unstyled mb-3">
                            <?php foreach (array_slice($pending_commits, 0, 100) as [$commit_hash, $commit_date, $commit_subject]) { ?>
                                <li class="d-flex gap-3 py-1 small">
                                    <code class="flex-shrink-0"><?= escapeHtml($commit_hash) ?></code>
                                    <span class="flex-grow-1 min-w-0 text-truncate" title="<?= escapeHtml($commit_subject) ?>"><?= escapeHtml($commit_subject) ?></span>
                                    <?php /* stored as an absolute ISO date - a stored "2 hours ago" would be wrong the moment it was written */ ?>
                                    <span class="flex-shrink-0 text-body-secondary text-nowrap"><?= escapeHtml(timeAgo($commit_date)) ?></span>
                                </li>
                            <?php } ?>
                            <?php if ($commit_count > 100) { ?>
                                <li class="small text-body-secondary">and <?= $commit_count - 100 ?> more</li>
                            <?php } ?>
                        </ul>
                    </details>
                <?php } ?>
            </div>
        <?php } ?>

    </div>

    <div class="col-lg-4">

        <!-- This install, at a glance -->
        <div class="card mb-3">
            <div class="card-header py-3">
                <h3 class="card-title"><i class="fas fa-fw fa-server me-2"></i>This install</h3>
            </div>
            <ul class="list-group list-group-flush small" data-update-install>
                <li class="list-group-item d-flex justify-content-between gap-3">
                    <span class="text-body-secondary">Version</span>
                    <span class="fw-semibold"><?= escapeHtml(APP_VERSION) ?><?= $new_release !== '' ? ' <span class="text-primary-emphasis fw-normal">&rarr; ' . escapeHtml($new_release) . '</span>' : '' ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between gap-3">
                    <span class="text-body-secondary">Updates from</span>
                    <span class="text-end">
                        <span class="fw-semibold"><?= escapeHtml($repo_branch) ?></span>
                        <?php if ($repo_branch !== 'master') { ?>
                            <span class="d-block text-warning-emphasis" title="Set by $repo_branch in config.php">Not the release branch - for test copies</span>
                        <?php } ?>
                    </span>
                </li>
                <li class="list-group-item d-flex justify-content-between gap-3">
                    <span class="text-body-secondary">Database</span>
                    <span class="fw-semibold"><?= escapeHtml(CURRENT_DATABASE_VERSION) ?><?= $db_update_available ? ' <span class="text-warning-emphasis fw-normal">&rarr; ' . escapeHtml(LATEST_DATABASE_VERSION) . '</span>' : '' ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between gap-3">
                    <span class="text-body-secondary">Commit</span>
                    <span><?= $current_version === '' ? '<span class="text-body-secondary">&mdash;</span>' : '<code>' . escapeHtml(substr($current_version, 0, 7)) . '</code>' ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between gap-3">
                    <span class="text-body-secondary">Last checked</span>
                    <span class="text-end">
                        <?php if (!empty($update_checked_at)) { ?>
                            <span title="<?= escapeHtml($update_checked_at) ?>"><?= escapeHtml(timeAgo($update_checked_at)) ?></span>
                        <?php } else { ?>
                            <span class="text-body-secondary">Never</span>
                        <?php } ?>
                    </span>
                </li>
                <li class="list-group-item d-flex justify-content-between gap-3">
                    <span class="text-body-secondary">Last updated</span>
                    <span class="text-end">
                        <?php if (!empty($update_job['cron_job_last_finished_at']) && in_array($update_status, ['Completed', 'Failed'], true)) { ?>
                            <span title="<?= escapeHtml($update_job['cron_job_last_finished_at']) ?>"><?= escapeHtml(timeAgo($update_job['cron_job_last_finished_at'])) ?></span>
                            <?php if ($update_status === 'Failed') { ?><span class="badge bg-danger-subtle text-danger-emphasis ms-1">Failed</span><?php } ?>
                        <?php } else { ?>
                            <span class="text-body-secondary">Not from this page yet</span>
                        <?php } ?>
                    </span>
                </li>
                <li class="list-group-item d-flex justify-content-between gap-3">
                    <span class="text-body-secondary">Cron</span>
                    <a href="cron.php" class="<?= $cron_is_running ? 'text-success-emphasis' : 'text-danger-emphasis' ?>">
                        <i class="fas fa-fw fa-<?= $cron_is_running ? 'check-circle' : 'times-circle' ?> me-1"></i><?= $cron_is_running ? 'Running' : 'Not running' ?>
                    </a>
                </li>
            </ul>
        </div>

        <!-- The same update from a shell - for an install cron can't reach, or a person who'd rather watch -->
        <div class="card mb-3">
            <div class="card-header py-3">
                <h3 class="card-title"><i class="fas fa-fw fa-terminal me-2"></i>From the command line</h3>
            </div>
            <div class="card-body small">
                <p class="text-body-secondary">
                    <?php if ($is_git_checkout) { ?>
                        Does the same as <?= $state === 'database' ? 'Finish updating' : 'Update ITFlow' ?> - the files, then the database - without waiting for cron:
                    <?php } else { ?>
                        Updates the database once the new release's files are in place:
                    <?php } ?>
                </p>
                <div class="position-relative">
                    <pre class="itflow-command bg-body-tertiary border rounded p-2 pe-5 mb-2"><code><?= escapeHtml($cli_command) ?></code></pre>
                    <button type="button" class="btn btn-sm btn-link position-absolute top-0 end-0 clipboardjs" title="Copy" data-clipboard-text="<?= escapeHtml($cli_command) ?>"><i class="far fa-copy"></i></button>
                </div>
                <p class="text-body-secondary mb-0">Add <code>--update_db</code> to update only the database - after restoring an older backup, say. See
                    <a href="https://docs.itflow.org/updates" target="_blank" rel="noopener">Updating ITFlow</a>.</p>
            </div>
        </div>

    </div>

</div>

<script src="<?= staticFileUrl('/js/auto_reload.js') ?>"></script>

<?php

require_once "../includes/footer.php";
