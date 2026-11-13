<?php

/*
 * Maintenance > Backup. One answer at the top - backed up, one being made, the last one
 * failed, none yet - with the buttons that take one. Under it the backups on this server,
 * then the schedule and how long backups are kept. Beside them: where backups stand at a
 * glance, the key they are encrypted with, how one is restored, and the credential vault's
 * master key.
 *
 * Nothing is built here. Back up now records a Pending row and asks the dispatcher for a
 * run (backupQueue()), and cron/backup.php builds it - so while one is on its way the page
 * reloads itself (js/auto_reload.js), as Maintenance > Update does. The handlers are
 * admin/post/backup.php; the schedule's own switch and time are the Backup job's, edited
 * in the Cron page's window (modals/cron/cron_edit.php), which comes back here.
 */

// The list: newest first until a heading is clicked
$sort = "backup_created_at";
$order = "DESC";

require_once "includes/inc_all_admin.php";

$backup_key = backupEncryptionKey();
$backup_dir = backupStorageDir();
$csrf = $_SESSION['csrf_token'];

$row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT config_enable_cron, config_cron_last_dispatch_at, config_backup_retention_days, config_backup_retention_count, config_backup_cron_type FROM settings WHERE company_id = 1"));

$config_enable_cron = intval($row['config_enable_cron']);
$cron_last_dispatch_at = $row['config_cron_last_dispatch_at'];
$config_backup_retention_days = intval($row['config_backup_retention_days']);
$config_backup_retention_count = intval($row['config_backup_retention_count']);
$config_backup_cron_type = in_array($row['config_backup_cron_type'], backupUnattendedTypes(), true) ? $row['config_backup_cron_type'] : BACKUP_TYPE_FULL;

// Same heartbeat rule as Maintenance > Cron - backups are built by the dispatcher, so
// without it the buttons below queue work that never runs
$cron_heartbeat = $cron_last_dispatch_at !== null && (time() - strtotime($cron_last_dispatch_at)) < 300;
$cron_is_running = $cron_heartbeat && $config_enable_cron === 1;

$backup_job = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT cron_job_id, cron_job_enabled, cron_job_daily_at, cron_job_last_status, cron_job_last_run_at FROM cron_jobs WHERE cron_job_name = 'backup'"));
$schedule_on = !empty($backup_job) && intval($backup_job['cron_job_enabled']) === 1;
$schedule_time = substr((string) ($backup_job['cron_job_daily_at'] ?? ''), 0, 5);
$backup_job_id = intval($backup_job['cron_job_id'] ?? 0);

// The newest finished backup of each kind cron can make - the vault's key isn't a backup
$latest = [];
foreach (backupUnattendedTypes() as $type) {
    $latest[$type] = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT backup_type, backup_size, backup_source, backup_created_by, backup_created_at
        FROM backups WHERE backup_status = 'Complete' AND backup_type = '$type' ORDER BY backup_created_at DESC, backup_id DESC LIMIT 1"));
}
$last_good = null;
foreach (array_filter($latest) as $candidate) {
    if (!$last_good || strtotime($candidate['backup_created_at']) > strtotime($last_good['backup_created_at'])) {
        $last_good = $candidate;
    }
}
$last_full = $latest[BACKUP_TYPE_FULL];

// A failure matters while nothing has worked since
$last_failed = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT backup_type, backup_error, backup_created_at FROM backups
    WHERE backup_status = 'Failed' AND backup_type <> '" . BACKUP_TYPE_MASTER_KEY . "' ORDER BY backup_created_at DESC, backup_id DESC LIMIT 1"));
if ($last_failed && $last_good && strtotime($last_failed['backup_created_at']) <= strtotime($last_good['backup_created_at'])) {
    $last_failed = null;
}

// What is queued or being built
$queue = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) AS backups, COALESCE(SUM(backup_status = 'Running'), 0) AS started, MIN(backup_created_at) AS since, MIN(backup_type) AS type FROM backups WHERE backup_status IN ('Pending', 'Running')"));
$queued = intval($queue['backups']);
$started = intval($queue['started']) > 0;
// Waiting longer than cron takes to come round, with the Backup job not at work, is stuck -
// cron stopped, or the job died part way. The nightly job tidies it; no need to wait for that
$job_at_work = ($backup_job['cron_job_last_status'] ?? '') === 'Running' && !empty($backup_job['cron_job_last_run_at'])
    && strtotime($backup_job['cron_job_last_run_at']) > strtotime('-6 hours');
$stuck = $queued > 0 && strtotime($queue['since']) < strtotime('-10 minutes') && !$job_at_work;

$kept = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) AS backups, COALESCE(SUM(backup_size), 0) AS bytes FROM backups WHERE backup_status = 'Complete'"));
$free_bytes = @disk_free_space($backup_dir);

/*
 * The one thing to say at the top. In this order, because each hides the ones after it:
 * no key to encrypt with, one on its way, a failure nothing has made up for, none at all,
 * one too old to count on, then all is well.
 */
$stale_days = 7;    // the age Debug's backup check starts warning at
if ($backup_key === '') {
    $state = 'nokey';
} elseif ($queued > 0) {
    $state = $stuck ? 'stuck' : 'working';
} elseif ($last_failed) {
    $state = 'failed';
} elseif (!$last_good) {
    $state = 'none';
} elseif (strtotime($last_good['backup_created_at']) < strtotime("-$stale_days days")) {
    $state = 'stale';
} else {
    $state = 'current';
}

$working_title = $queued === 1 ? 'Making a ' . backupTypeLabel((string) $queue['type']) . ($queue['type'] === BACKUP_TYPE_FULL ? '' : ' backup') : "Making $queued backups";
$hero = [
    'nokey' => ['icon' => 'fa-key', 'tone' => 'danger', 'title' => "Backups can't be made yet"],
    'working' => ['icon' => 'fa-circle-notch fa-spin', 'tone' => 'primary', 'title' => $working_title],
    'stuck' => ['icon' => 'fa-hourglass-half', 'tone' => 'warning', 'title' => $started ? 'A backup stopped part way' : ($queued === 1 ? 'A backup is waiting to be made' : "$queued backups are waiting to be made")],
    'failed' => ['icon' => 'fa-exclamation-triangle', 'tone' => 'danger', 'title' => "The last backup didn't finish"],
    'none' => ['icon' => 'fa-life-ring', 'tone' => 'warning', 'title' => 'No backup yet'],
    'stale' => ['icon' => 'fa-history', 'tone' => 'warning', 'title' => 'The last backup is ' . ($last_good ? floor((time() - strtotime($last_good['backup_created_at'])) / 86400) : 0) . ' days old'],
    'current' => ['icon' => 'fa-check', 'tone' => 'success', 'title' => 'Backed up ' . ($last_good ? timeAgo($last_good['backup_created_at']) : '')],
][$state];

// Shown once, straight after an export, then dropped - it must not survive a reload
$master_key_reveal = '';
if (!empty($_SESSION['backup_master_key_reveal'])) {
    $master_key_reveal = $_SESSION['backup_master_key_reveal'];
    unset($_SESSION['backup_master_key_reveal']);
}

// The page reloads while a backup is on its way - but never out from under a key shown once
$reload = $state === 'working' && $master_key_reveal === '';

// A time as "3 hours ago", with the date and time behind it
$ago = function ($datetime) {
    return '<span title="' . escapeHtml($datetime) . '">' . escapeHtml(timeAgo($datetime)) . '</span>';
};
// Who a backup is down to, from its row
$made_by = function ($backup) {
    if ($backup['backup_source'] === 'Cron') {
        return 'Scheduled';
    }
    if ($backup['backup_source'] === 'Adopted') {
        return 'Found on this server';
    }
    return (string) $backup['backup_created_by'] !== '' ? 'By ' . $backup['backup_created_by'] : 'By hand';
};
$schedule_text = $schedule_on ? "Every night at $schedule_time" : 'Off';
// A type as it reads in a sentence - "a Full Backup", "a Database Only backup"
$type_noun = function ($type) {
    return backupTypeLabel((string) $type) . ($type === BACKUP_TYPE_FULL ? '' : ($type === BACKUP_TYPE_MASTER_KEY ? ' export' : ' backup'));
};

// The restore command, as this server runs it - the user that owns the files, in this folder
$install_root = dirname(__DIR__);
$file_owner = '';
if (function_exists('posix_getpwuid')) {
    $file_owner = (string) (posix_getpwuid((int) @fileowner($install_root . '/scripts/restore_cli.php'))['name'] ?? '');
}
$shell_word = function ($word) {
    return preg_match('/^[A-Za-z0-9_\/.@%+=:,-]+$/', $word) ? $word : escapeshellarg($word);
};
$cli_command = "cd " . $shell_word($install_root) . "\n" . ($file_owner !== '' ? "sudo -u " . $shell_word($file_owner) . " " : '') . "php scripts/restore_cli.php \\\n    --file=/path/to/backup.zip";

// The list - only these sort; an unknown column would take the page down
$sortable_columns = [
    'backup_created_at' => "backup_created_at $order, backup_id $order",
    'backup_type' => "backup_type $order, backup_created_at DESC, backup_id DESC",
    'backup_size' => "backup_size $order, backup_created_at DESC, backup_id DESC",
    'backup_status' => "backup_status $order, backup_created_at DESC, backup_id DESC",
];
if (!isset($sortable_columns[$sort])) {
    $sort = 'backup_created_at';
}

$sql = mysqli_query($mysqli, "SELECT SQL_CALC_FOUND_ROWS backup_id, backup_type, backup_file_name, backup_size, backup_sha256, backup_status, backup_error,
    backup_source, backup_created_by, backup_created_at, backup_downloaded_at
    FROM backups ORDER BY {$sortable_columns[$sort]} LIMIT $record_from, $record_to");

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

$sort_link = function ($column, $label) use ($url_query_strings_sort, $sort, $disp, $order_icon) {
    return '<a class="text-reset text-decoration-none" href="?' . escapeHtml($url_query_strings_sort) . '&amp;sort=' . $column . '&amp;order=' . $disp . '">'
        . $label . ($sort === $column ? " $order_icon" : '') . '</a>';
};

$type_looks = [
    BACKUP_TYPE_FULL => ['icon' => 'fa-box-open', 'tone' => 'itflow-asset-tone-server'],
    BACKUP_TYPE_DATABASE => ['icon' => 'fa-database', 'tone' => 'itflow-asset-tone-network'],
    BACKUP_TYPE_MASTER_KEY => ['icon' => 'fa-key', 'tone' => 'itflow-asset-tone-phone'],
];
$status_looks = [
    'Complete' => ['label' => 'Complete', 'tone' => 'success'],
    'Pending' => ['label' => 'Queued', 'tone' => 'secondary'],
    'Running' => ['label' => 'Being made', 'tone' => 'primary'],
    'Failed' => ['label' => 'Failed', 'tone' => 'danger'],
    'Missing' => ['label' => 'Missing', 'tone' => 'warning'],
];

?>

<?php if ($master_key_reveal !== '') { ?>
    <div class="alert alert-warning" data-backup-master-key>
        <h5 class="alert-heading"><i class="fas fa-fw fa-key me-2"></i>The credential vault's master key</h5>
        <p class="mb-2">Shown this once - it is gone when the page reloads. It opens every saved credential, so keep it the way you'd keep those.</p>
        <div class="input-group">
            <input type="text" class="form-control font-monospace" value="<?= escapeHtml($master_key_reveal) ?>" readonly aria-label="Master key">
            <button class="btn btn-dark clipboardjs" type="button" data-clipboard-text="<?= escapeHtml($master_key_reveal) ?>"><i class="far fa-fw fa-copy me-2"></i>Copy</button>
        </div>
    </div>
<?php } ?>

<div class="row g-3">

    <div class="col-lg-8">

        <!-- Where backups stand, and the buttons that take one -->
        <div class="card mb-3" data-backup-state="<?= $state ?>">
            <div class="card-body p-4"<?= $reload ? ' data-itflow-reload-seconds="10"' : '' ?>>
                <div class="d-flex align-items-start gap-3">
                    <span class="itflow-asset-icon itflow-asset-icon-lg rounded-circle bg-<?= $hero['tone'] ?>-subtle text-<?= $hero['tone'] ?>-emphasis" aria-hidden="true"><i class="fas <?= $hero['icon'] ?>"></i></span>
                    <div class="flex-grow-1 min-w-0">
                        <h2 class="h4 mb-1"><?= escapeHtml($hero['title']) ?></h2>

                        <?php if ($state === 'nokey') { ?>
                            <p class="mb-0 text-body-secondary">
                                Every backup is encrypted, and ITFlow couldn't write an encryption key to <strong>config.php</strong>. Make config.php writable by the web server's user and reload this page, or add this line to it yourself:
                            </p>
                            <pre class="itflow-command bg-body-tertiary border rounded p-2 mt-2 mb-0 small"><code>$config_backup_key = '<?= escapeHtml(randomString(32)) ?>';</code></pre>

                        <?php } elseif ($state === 'working') { ?>
                            <p class="mb-0 text-body-secondary">
                                <?= $started ? 'Started' : 'Asked for' ?> <?= $ago($queue['since']) ?>. Cron builds <?= $queued === 1 ? 'it' : 'them' ?> in the background - a minute or two for most installs, longer with a lot uploaded.
                                You can leave this page; you'll get a notification as each is ready.
                            </p>

                        <?php } elseif ($state === 'stuck') { ?>
                            <p class="mb-0 text-body-secondary">
                                <?php if ($started) { ?>
                                    It started <?= $ago($queue['since']) ?> and never finished - the Backup job was stopped while it worked. Remove it in the list below and take another; <a href="cron.php">Maintenance &gt; Cron</a> has what the job last said.
                                <?php } elseif ($cron_is_running) { ?>
                                    Asked for <?= $ago($queue['since']) ?>, and the Backup job hasn't picked it up - <a href="cron.php">Maintenance &gt; Cron</a> has what it last said. Cancel it in the list below and take another.
                                <?php } else { ?>
                                    Asked for <?= $ago($queue['since']) ?>. It is made as soon as cron runs again.
                                <?php } ?>
                            </p>

                        <?php } elseif ($state === 'failed') { ?>
                            <p class="mb-2 text-body-secondary">
                                The <?= escapeHtml($type_noun($last_failed['backup_type'])) ?> started <?= $ago($last_failed['backup_created_at']) ?> stopped<?= (string) $last_failed['backup_error'] !== '' ? ':' : '.' ?>
                            </p>
                            <?php if ((string) $last_failed['backup_error'] !== '') { ?>
                                <pre class="itflow-command bg-body-tertiary border rounded p-2 mb-2 small"><?= escapeHtml($last_failed['backup_error']) ?></pre>
                            <?php } ?>
                            <p class="mb-0 text-body-secondary">
                                <?= $last_good ? 'The last one that worked was ' . $ago($last_good['backup_created_at']) . '.' : 'There is no backup that worked yet.' ?>
                                Fix what stopped it and take another.
                            </p>

                        <?php } elseif ($state === 'none') { ?>
                            <p class="mb-0 text-body-secondary">
                                Take a Full Backup now - the database and everything uploaded, in one encrypted file.
                                <?= $schedule_on ? "After that, one is made every night at $schedule_time." : 'Then turn the schedule on below, and one is made every night.' ?>
                            </p>

                        <?php } else { ?>
                            <p class="mb-0 text-body-secondary">
                                <?= escapeHtml(backupTypeLabel((string) $last_good['backup_type'])) ?>, <?= escapeHtml(backupFormatBytes($last_good['backup_size'])) ?> - <?= escapeHtml(lcfirst($made_by($last_good))) ?>.
                                <?php if ($last_good['backup_type'] !== BACKUP_TYPE_FULL) { ?>
                                    <?= $last_full ? 'The last Full Backup, with everything uploaded, was ' . $ago($last_full['backup_created_at']) . '.' : "There's no Full Backup yet - attachments and documents aren't in a Database Only one." ?>
                                <?php } ?>
                                <?php if ($state === 'stale') { ?>
                                    <?= $schedule_on ? "One is due every night at $schedule_time, so something is stopping it." : 'The schedule is off, so the next one is whenever you take it.' ?>
                                <?php } else { ?>
                                    <?= $schedule_on ? "The next scheduled one is at $schedule_time." : 'The schedule is off, so the next one is whenever you take it.' ?>
                                <?php } ?>
                            </p>
                        <?php } ?>

                        <?php if (!in_array($state, ['nokey', 'working'], true)) { ?>
                            <div class="d-flex flex-wrap gap-2 mt-3">
                                <a class="btn btn-primary" href="post.php?queue_backup=full&amp;csrf_token=<?= $csrf ?>" data-backup-now>
                                    <i class="fas fa-fw fa-box-open me-2"></i>Back up now
                                </a>
                                <a class="btn btn-outline-secondary" href="post.php?queue_backup=database&amp;csrf_token=<?= $csrf ?>">
                                    <i class="fas fa-fw fa-database me-2"></i>Database only
                                </a>
                            </div>
                            <p class="mb-0 mt-2 small text-body-secondary">
                                <strong>Back up now</strong> makes a Full Backup: the database and every uploaded file. <strong>Database only</strong> is smaller and quicker, without attachments or documents.
                            </p>
                        <?php } ?>
                    </div>
                </div>

                <?php if ($state !== 'nokey' && !$cron_is_running) { ?>
                    <div class="alert alert-warning mt-3 mb-0 small" data-backup-cron-warning>
                        <i class="fas fa-fw fa-exclamation-circle me-1"></i>
                        <?php if ($config_enable_cron !== 1) { ?>
                            Cron is switched off, so no backup is made - scheduled or started here - until it's back on in <a href="cron.php" class="alert-link">Maintenance &gt; Cron</a>.
                        <?php } else { ?>
                            Cron hasn't run in the last five minutes. Backups are built by cron, not by your browser, so one started here waits until it runs. Check <a href="cron.php" class="alert-link">Maintenance &gt; Cron</a>.
                        <?php } ?>
                    </div>
                <?php } ?>
            </div>
        </div>

        <?php if ($num_rows[0] > 0) { // with none at all, the card above has said so ?>
        <!-- The backups on this server -->
        <div class="card mb-3">
            <div class="card-header py-3">
                <h3 class="card-title"><i class="fas fa-fw fa-archive me-2"></i>Backups</h3>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" data-backup-list>
                    <thead class="table-light text-nowrap">
                    <tr>
                        <th class="ps-3"><?= $sort_link('backup_type', 'Backup') ?></th>
                        <th class="d-none d-sm-table-cell"><?= $sort_link('backup_created_at', 'Made') ?></th>
                        <th class="d-none d-sm-table-cell"><?= $sort_link('backup_size', 'Size') ?></th>
                        <th><?= $sort_link('backup_status', 'Status') ?></th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php while ($backup = mysqli_fetch_assoc($sql)) {
                        $backup_id = intval($backup['backup_id']);
                        $status = $backup['backup_status'];
                        $type_label = backupTypeLabel((string) $backup['backup_type']);
                        $type_as_noun = $type_noun($backup['backup_type']);
                        $type_look = $type_looks[$backup['backup_type']] ?? ['icon' => 'fa-file-archive', 'tone' => 'itflow-asset-tone-other'];
                        $status_look = $status_looks[$status] ?? ['label' => $status, 'tone' => 'secondary'];
                        $made_at = substr((string) $backup['backup_created_at'], 0, 16);
                        $backup_path = $status === 'Complete' ? backupResolvePath((string) $backup['backup_file_name']) : false;
                        // A finished one is deleted, file and all; one still waiting is taken off the queue; the rest are only rows
                        $remove = [
                            'Complete' => ['Delete', 'Delete this backup?', "The $type_as_noun from $made_at is deleted from this server for good. A copy you've downloaded isn't touched."],
                            'Pending' => ['Take off the queue', 'Take it off the queue?', "The $type_as_noun asked for at $made_at won't be made."],
                            'Running' => ['Remove', 'Remove this backup?', "It's taken off this list, along with anything written so far."],
                        ][$status] ?? ['Remove', 'Remove it from the list?', "The $type_as_noun from $made_at is taken off this list. There is no file left to delete."];
                        ?>
                        <tr data-backup-id="<?= $backup_id ?>" data-backup-status="<?= escapeHtml($status) ?>">
                            <td class="ps-3">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="itflow-asset-icon <?= $type_look['tone'] ?> d-none d-sm-inline-flex" aria-hidden="true"><i class="fas <?= $type_look['icon'] ?>"></i></span>
                                    <div class="min-w-0">
                                        <div class="fw-semibold text-nowrap"><?= escapeHtml($type_label) ?></div>
                                        <div class="small text-body-secondary text-truncate"><?= escapeHtml($made_by($backup)) ?></div>
                                        <?php // On a phone the Made column goes, and its date sits here ?>
                                        <div class="small text-body-secondary text-nowrap d-sm-none"><?= escapeHtml($made_at) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="text-nowrap d-none d-sm-table-cell">
                                <div><?= escapeHtml($made_at) ?></div>
                                <div class="small text-body-secondary"><?= escapeHtml(timeAgo($backup['backup_created_at'])) ?></div>
                            </td>
                            <td class="d-none d-sm-table-cell text-nowrap"><?= $backup['backup_size'] > 0 ? escapeHtml(backupFormatBytes($backup['backup_size'])) : '<span class="text-body-secondary">&mdash;</span>' ?></td>
                            <td>
                                <span class="badge bg-<?= $status_look['tone'] ?>-subtle text-<?= $status_look['tone'] ?>-emphasis"><?php if ($status === 'Running') { ?><i class="fas fa-circle-notch fa-spin me-1"></i><?php } ?><?= escapeHtml($status_look['label']) ?></span>
                                <?php if ($status === 'Failed' && (string) $backup['backup_error'] !== '') { ?>
                                    <div class="small text-danger-emphasis mt-1"><?= escapeHtml($backup['backup_error']) ?></div>
                                <?php } elseif ($status === 'Missing') { ?>
                                    <div class="small text-body-secondary mt-1">Its file is no longer on this server</div>
                                <?php } elseif ($status === 'Complete' && !empty($backup['backup_downloaded_at'])) { ?>
                                    <div class="small text-body-secondary mt-1" title="<?= escapeHtml($backup['backup_downloaded_at']) ?>"><i class="fas fa-fw fa-download me-1"></i>Downloaded <?= escapeHtml(timeAgo($backup['backup_downloaded_at'])) ?></div>
                                <?php } ?>
                            </td>
                            <td class="text-end text-nowrap pe-3">
                                <?php if ($status === 'Complete') { ?>
                                    <a class="btn btn-sm btn-light" href="backup_download.php?backup_id=<?= $backup_id ?>&amp;csrf_token=<?= $csrf ?>" title="Download this backup">
                                        <i class="fas fa-fw fa-download"></i><span class="d-none d-md-inline ms-1">Download</span>
                                    </a>
                                <?php } ?>
                                <div class="dropdown dropstart d-inline-block">
                                    <button class="btn btn-sm btn-light" title="More actions" type="button" data-bs-toggle="dropdown"><i class="fas fa-fw fa-ellipsis-v"></i></button>
                                    <div class="dropdown-menu">
                                        <?php if ($backup_path !== false) { ?>
                                            <button type="button" class="dropdown-item clipboardjs" data-clipboard-text="<?= escapeHtml($backup_path) ?>" title="For restoring it from the command line"><i class="fas fa-fw fa-terminal text-secondary me-2"></i>Copy its path on this server</button>
                                        <?php } ?>
                                        <?php if ($status === 'Complete' && (string) $backup['backup_sha256'] !== '') { ?>
                                            <button type="button" class="dropdown-item clipboardjs" data-clipboard-text="<?= escapeHtml($backup['backup_sha256']) ?>" title="To check a copy is the same file"><i class="fas fa-fw fa-fingerprint text-secondary me-2"></i>Copy its SHA-256 checksum</button>
                                        <?php } ?>
                                        <?php if ($status === 'Complete') { ?>
                                            <div class="dropdown-divider"></div>
                                        <?php } ?>
                                        <a class="dropdown-item text-danger confirm-link" href="post.php?delete_backup=<?= $backup_id ?>&amp;csrf_token=<?= $csrf ?>"
                                            data-confirm-title="<?= escapeHtml($remove[1]) ?>" data-confirm-text="<?= escapeHtml($remove[2]) ?>" data-confirm-button="<?= $status === 'Pending' ? 'Take it off' : $remove[0] ?>">
                                            <i class="fas fa-fw fa-<?= $status === 'Pending' ? 'ban' : 'trash' ?> me-2"></i><?= $remove[0] ?>
                                        </a>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
            <?php require_once "../includes/filter_footer.php"; ?>
        </div>
        <?php } ?>

        <!-- The schedule, and how long backups are kept -->
        <div class="card mb-3">
            <div class="card-header py-3">
                <h3 class="card-title"><i class="fas fa-fw fa-clock me-2"></i>Schedule and retention</h3>
            </div>
            <div class="list-group list-group-flush border-bottom">
                <div class="list-group-item d-flex flex-wrap flex-md-nowrap align-items-center gap-3 py-3<?= $schedule_on ? '' : ' bg-warning-subtle' ?>" data-backup-schedule="<?= $schedule_on ? 'on' : 'off' ?>">
                    <i class="fas fa-fw fa-<?= $schedule_on ? 'check-circle text-success' : 'pause-circle text-warning-emphasis' ?>" aria-hidden="true"></i>
                    <div class="flex-grow-1 min-w-0">
                        <?php if ($schedule_on) { ?>
                            <div class="fw-semibold">A <?= escapeHtml($type_noun($config_backup_cron_type)) ?> is made every night at <?= escapeHtml($schedule_time) ?></div>
                            <div class="small text-body-secondary">It's the Backup job in <a href="cron.php">Maintenance &gt; Cron</a>.</div>
                        <?php } else { ?>
                            <div class="fw-semibold">Scheduled backups are off</div>
                            <div class="small text-body-secondary">Backups are only made when someone starts one here. Turn the schedule on and one is made every night<?= $schedule_time !== '' ? " at $schedule_time" : '' ?>.</div>
                        <?php } ?>
                    </div>
                    <?php if ($backup_job_id) { ?>
                        <?php // The Backup job's own switch and time (admin/post/cron.php) - both come back to this page ?>
                        <div class="d-flex gap-2 flex-shrink-0">
                            <?php if (!$schedule_on) { ?>
                                <a class="btn btn-sm btn-primary" href="post.php?enable_cron_job=<?= $backup_job_id ?>&amp;csrf_token=<?= $csrf ?>" data-backup-schedule-on>
                                    <i class="fas fa-fw fa-power-off me-1"></i>Turn on
                                </a>
                            <?php } ?>
                            <button type="button" class="btn btn-sm btn-light border ajax-modal" data-modal-url="modals/cron/cron_edit.php?id=<?= $backup_job_id ?>" data-backup-schedule-edit>
                                <i class="fas fa-fw fa-clock me-1"></i>Change the time
                            </button>
                        </div>
                    <?php } ?>
                </div>
            </div>
            <div class="card-body">
                <form action="post.php" method="post" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label" for="backupCronType">The scheduled backup is a</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-fw fa-box-open"></i></span>
                                <select class="form-select" name="config_backup_cron_type" id="backupCronType">
                                    <?php foreach (backupUnattendedTypes() as $type) { ?>
                                        <option value="<?= $type ?>" <?= $config_backup_cron_type === $type ? 'selected' : '' ?>><?= escapeHtml(backupTypeLabel($type)) ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="form-text">Database Only leaves out attachments and documents.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="backupRetentionDays">Keep backups for</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-fw fa-calendar-alt"></i></span>
                                <input type="number" class="form-control" name="config_backup_retention_days" id="backupRetentionDays" min="0" max="36500" value="<?= $config_backup_retention_days ?>">
                                <span class="input-group-text">days</span>
                            </div>
                            <div class="form-text">0 keeps them whatever their age.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="backupRetentionCount">Keep at most</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-fw fa-layer-group"></i></span>
                                <input type="number" class="form-control" name="config_backup_retention_count" id="backupRetentionCount" min="1" max="100000" value="<?= $config_backup_retention_count ?>">
                                <span class="input-group-text">of each type</span>
                            </div>
                            <div class="form-text">The newest of each type is never removed.</div>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap align-items-center gap-3 mt-3">
                        <button type="submit" name="edit_backup_settings" class="btn btn-primary"><i class="fas fa-fw fa-check me-2"></i>Save</button>
                        <span class="small text-body-secondary">Older backups are removed by the nightly job, not by a backup itself - so one that fails never costs you the one before it.</span>
                    </div>
                </form>
            </div>
        </div>

    </div>

    <div class="col-lg-4">

        <!-- Where backups stand, at a glance -->
        <div class="card mb-3">
            <div class="card-header py-3">
                <h3 class="card-title"><i class="fas fa-fw fa-shield-alt me-2"></i>At a glance</h3>
            </div>
            <ul class="list-group list-group-flush small" data-backup-glance>
                <li class="list-group-item d-flex justify-content-between gap-3">
                    <span class="text-body-secondary">Last Full Backup</span>
                    <span class="text-end fw-semibold"><?= $last_full ? $ago($last_full['backup_created_at']) : '<span class="text-warning-emphasis">None yet</span>' ?></span>
                </li>
                <?php if ($latest[BACKUP_TYPE_DATABASE]) { ?>
                    <li class="list-group-item d-flex justify-content-between gap-3">
                        <span class="text-body-secondary">Last Database Only</span>
                        <span class="text-end fw-semibold"><?= $ago($latest[BACKUP_TYPE_DATABASE]['backup_created_at']) ?></span>
                    </li>
                <?php } ?>
                <li class="list-group-item d-flex justify-content-between gap-3">
                    <span class="text-body-secondary">Schedule</span>
                    <span class="text-end">
                        <span class="fw-semibold<?= $schedule_on ? '' : ' text-warning-emphasis' ?>"><?= escapeHtml($schedule_text) ?></span>
                        <?php if ($schedule_on) { ?><span class="d-block text-body-secondary"><?= escapeHtml(backupTypeLabel($config_backup_cron_type)) ?></span><?php } ?>
                    </span>
                </li>
                <li class="list-group-item d-flex justify-content-between gap-3">
                    <span class="text-body-secondary">Kept</span>
                    <span class="text-end fw-semibold"><?= $config_backup_retention_days > 0 ? $config_backup_retention_days . ' day' . ($config_backup_retention_days === 1 ? '' : 's') . ', ' : '' ?><?= $config_backup_retention_count ?> of each type</span>
                </li>
                <li class="list-group-item d-flex justify-content-between gap-3">
                    <span class="text-body-secondary">On this server</span>
                    <span class="text-end fw-semibold"><?= intval($kept['backups']) ?> backup<?= intval($kept['backups']) === 1 ? '' : 's' ?><?= intval($kept['backups']) > 0 ? ', ' . escapeHtml(backupFormatBytes($kept['bytes'])) : '' ?></span>
                </li>
                <?php if ($free_bytes !== false && $free_bytes !== null) { ?>
                    <li class="list-group-item d-flex justify-content-between gap-3">
                        <span class="text-body-secondary">Free space</span>
                        <?php // Room for the next one - less than twice the last full backup is cutting it fine ?>
                        <span class="text-end fw-semibold<?= $last_full && $free_bytes < 2 * $last_full['backup_size'] ? ' text-danger-emphasis' : '' ?>"><?= escapeHtml(backupFormatBytes($free_bytes)) ?></span>
                    </li>
                <?php } ?>
                <li class="list-group-item d-flex justify-content-between gap-3">
                    <span class="text-body-secondary">Cron</span>
                    <a href="cron.php" class="<?= $cron_is_running ? 'text-success-emphasis' : 'text-danger-emphasis' ?>">
                        <i class="fas fa-fw fa-<?= $cron_is_running ? 'check-circle' : 'times-circle' ?> me-1"></i><?= $cron_is_running ? 'Running' : ($config_enable_cron !== 1 ? 'Switched off' : 'Not running') ?>
                    </a>
                </li>
                <li class="list-group-item">
                    <div class="text-body-secondary">Kept in</div>
                    <code class="text-break text-body" data-backup-folder><?= escapeHtml($backup_dir) ?></code>
                    <?php if (empty($config_backup_path)) { ?>
                        <div class="text-body-secondary mt-1">Inside ITFlow's own folder. <code>$config_backup_path</code> in config.php keeps them somewhere else - another disk, or a mounted share.</div>
                    <?php } ?>
                </li>
            </ul>
        </div>

        <!-- The key every backup is encrypted with -->
        <div class="card mb-3">
            <div class="card-header py-3">
                <h3 class="card-title"><i class="fas fa-fw fa-lock me-2"></i>Encryption key</h3>
            </div>
            <div class="card-body small">
                <p class="text-body-secondary">Every backup is encrypted with this key, and can't be opened or restored without it - not by you, not by anyone. <strong class="text-body">Keep a copy somewhere other than this server.</strong></p>
                <?php if ($backup_key !== '') { ?>
                    <div class="input-group mb-2">
                        <span class="input-group-text"><i class="fas fa-fw fa-key"></i></span>
                        <input type="password" class="form-control font-monospace" data-toggle="password" value="<?= escapeHtml($backup_key) ?>" readonly autocomplete="off" aria-label="Backup encryption key" data-backup-key>
                        <span class="input-group-text"><i class="fa fa-fw fa-eye"></i></span>
                        <button class="btn btn-light border clipboardjs" type="button" title="Copy the key" data-clipboard-text="<?= escapeHtml($backup_key) ?>"><i class="far fa-fw fa-copy"></i></button>
                    </div>
                <?php } else { ?>
                    <p class="text-danger-emphasis mb-2"><i class="fas fa-fw fa-exclamation-triangle me-1"></i>There isn't one yet - config.php couldn't be written.</p>
                <?php } ?>
                <p class="text-body-secondary mb-0">It is kept in config.php and never in the database, so a backup that gets out doesn't carry its own key.</p>
                <details class="mt-2">
                    <summary class="text-body-secondary">Opening a backup by hand</summary>
                    <p class="text-body-secondary mt-2 mb-0">A backup is an AES-256 encrypted zip. <strong>7-Zip, WinZip, PeaZip and Keka</strong> open it with this key. The <code>unzip</code> command, Windows Explorer and the macOS Archive Utility can't - they don't do AES.</p>
                </details>
            </div>
        </div>

        <!-- Restoring - not from here -->
        <div class="card mb-3">
            <div class="card-header py-3">
                <h3 class="card-title"><i class="fas fa-fw fa-undo me-2"></i>Restoring</h3>
            </div>
            <div class="card-body small">
                <p class="text-body-secondary">Restoring replaces the database - and, from a Full Backup, everything uploaded - with what's in the backup. It isn't done from this page, on purpose: a running ITFlow shouldn't drop its own tables from a browser.</p>
                <p class="fw-semibold mb-1">From the command line</p>
                <div class="position-relative">
                    <pre class="itflow-command bg-body-tertiary border rounded p-2 pe-5 mb-2"><code><?= escapeHtml($cli_command) ?></code></pre>
                    <button type="button" class="btn btn-sm btn-link position-absolute top-0 end-0 clipboardjs" title="Copy" data-clipboard-text="<?= escapeHtml($cli_command) ?>"><i class="far fa-copy"></i></button>
                </div>
                <p class="text-body-secondary">Works for a backup of any size. Add <code>--inspect</code> to see what's in one without changing anything, and <code>--key=</code> for a backup from another server, which needs that server's key. A backup's menu copies its path.</p>
                <p class="fw-semibold mb-1">On a new server</p>
                <p class="text-body-secondary mb-0">The setup wizard at <code>/setup</code> offers to restore a backup before the first user exists - up to <?= escapeHtml(backupFormatBytes(backupMaxUploadBytes())) ?> here, the most PHP takes in one upload.</p>
            </div>
        </div>

        <!-- The credential vault's own key - not part of a backup, and rarely needed -->
        <div class="card mb-3">
            <div class="card-header py-3">
                <h3 class="card-title"><i class="fas fa-fw fa-key me-2"></i>Credential vault key</h3>
            </div>
            <div class="card-body small">
                <p class="text-body-secondary">The master key that opens every saved credential. A restore brings the vault back by itself, as long as one user still knows their password - this is for when none does.</p>
                <button type="button" class="btn btn-sm btn-light border ajax-modal" data-modal-url="modals/backup/backup_master_key.php" data-backup-master-key-export>
                    <i class="fas fa-fw fa-key me-2"></i>Export the master key
                </button>
            </div>
        </div>

    </div>

</div>

<script src="<?= staticFileUrl('/js/auto_reload.js') ?>"></script>

<?php
require_once "../includes/footer.php";
