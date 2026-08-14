<?php

require_once "includes/inc_all_admin.php";

$backup_key = backupEncryptionKey();
$backup_dir = backupStorageDir();

$row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT config_enable_cron, config_cron_last_dispatch_at, config_backup_retention_days, config_backup_retention_count, config_backup_cron_type FROM settings WHERE company_id = 1"));

$config_enable_cron = intval($row['config_enable_cron']);
$cron_last_dispatch_at = $row['config_cron_last_dispatch_at'];
$config_backup_retention_days = intval($row['config_backup_retention_days']);
$config_backup_retention_count = intval($row['config_backup_retention_count']);
$config_backup_cron_type = $row['config_backup_cron_type'];

// Same heartbeat rule as Maintenance > Cron - archives are built by the dispatcher, so a dead
// crontab means the buttons below queue work that never runs
$cron_is_running = $cron_last_dispatch_at !== null && (time() - strtotime($cron_last_dispatch_at)) < 300;

$backup_job = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT cron_job_enabled, cron_job_daily_at FROM cron_jobs WHERE cron_job_name = 'backup'"));

$backups = mysqli_query($mysqli, "SELECT backup_created_at, backup_error, backup_id, backup_size, backup_source, backup_status,
    backup_type FROM backups ORDER BY backup_created_at DESC LIMIT 100");

$pending_count = intval(mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) AS c FROM backups WHERE backup_status IN ('Pending','Running')"))['c']);

?>

<?php
// Shown once, immediately after an export, then dropped - it must not survive a refresh
if (!empty($_SESSION['backup_master_key_reveal'])) {
    $master_key_reveal = $_SESSION['backup_master_key_reveal'];
    unset($_SESSION['backup_master_key_reveal']);
?>
    <div class="alert alert-warning">
        <h5><i class="fas fa-fw fa-key me-2"></i>Master encryption key</h5>
        <p class="mb-2">Shown once. Refreshing this page will not show it again.</p>
        <input type="text" class="form-control font-monospace" value="<?= escapeHtml($master_key_reveal) ?>" readonly onclick="this.select();">
    </div>
<?php } ?>

<?php if ($backup_key === '') { ?>
    <div class="alert alert-danger">
        <h5><i class="fas fa-fw fa-exclamation-triangle me-2"></i>No backup encryption key</h5>
        ITFlow could not write a backup encryption key to <strong>config.php</strong>, so it cannot produce an encrypted backup.
        Make config.php writable by the web server user and reload this page, or add a line like
        <code>$config_backup_key = '&lt;32 random characters&gt;';</code> to it yourself.
    </div>
<?php } ?>

<?php if (!$cron_is_running) { ?>
    <div class="alert alert-danger">
        <h5><i class="fas fa-fw fa-exclamation-triangle me-2"></i>Cron is not running</h5>
        Backups are built by the cron dispatcher, not by your browser. Until cron is running, anything you
        start here will sit in the queue. See <a href="cron.php">Maintenance &gt; Cron</a>.
    </div>
<?php } elseif ($config_enable_cron == 0) { ?>
    <div class="alert alert-warning">
        <i class="fas fa-fw fa-exclamation-circle me-2"></i>Cron is switched off in
        <a href="cron.php">Maintenance &gt; Cron</a>.
    </div>
<?php } ?>

<div class="card card-dark mb-3">
    <div class="card-header py-3">
        <h3 class="card-title"><i class="fas fa-fw fa-cloud-upload-alt me-2"></i>Create a Backup</h3>
    </div>
    <div class="card-body">

        <?php if ($pending_count > 0) { ?>
            <div class="alert alert-info">
                <i class="fas fa-fw fa-spinner me-2"></i><strong><?= $pending_count ?></strong> backup<?= $pending_count == 1 ? ' is' : 's are' ?>
                queued or building. You will get a notification when ready - this page does not refresh itself.
            </div>
        <?php } ?>

        <div class="row">
            <div class="col-md-4 mb-3">
                <div class="border rounded p-3 h-100 text-center">
                    <i class="fas fa-fw fa-3x fa-box-open text-dark mb-3"></i>
                    <h5>Full Backup</h5>
                    <p class="text-muted small">The database and everything in the uploads folder. This is the one to keep.</p>
                    <a class="btn btn-primary <?= $backup_key === '' ? 'disabled' : '' ?>" href="post.php?queue_backup=full&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                        <i class="fas fa-fw fa-play me-2"></i>Start
                    </a>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="border rounded p-3 h-100 text-center">
                    <i class="fas fa-fw fa-3x fa-database text-dark mb-3"></i>
                    <h5>Database Only</h5>
                    <p class="text-muted small">Just the SQL dump. Much smaller and much quicker, but no attachments or documents.</p>
                    <a class="btn btn-primary <?= $backup_key === '' ? 'disabled' : '' ?>" href="post.php?queue_backup=database&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                        <i class="fas fa-fw fa-play me-2"></i>Start
                    </a>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="border rounded p-3 h-100 text-center">
                    <i class="fas fa-fw fa-3x fa-key text-dark mb-3"></i>
                    <h5>Master Key</h5>
                    <p class="text-muted small">The credential vault key. Only needed if every user password is lost - a normal restore recovers the vault on its own.</p>
                    <button type="button" class="btn btn-secondary" data-bs-toggle="modal" data-bs-target="#masterKeyModal">
                        <i class="fas fa-fw fa-key me-2"></i>Export
                    </button>
                </div>
            </div>
        </div>

    </div>
</div>

<div class="card card-dark mb-3">
    <div class="card-header py-3">
        <h3 class="card-title"><i class="fas fa-fw fa-archive me-2"></i>Backups</h3>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-borderless mb-0">
                <thead class="text-dark">
                    <tr>
                        <th>Type</th>
                        <th>Created</th>
                        <th>Size</th>
                        <th>Source</th>
                        <th>Status</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (mysqli_num_rows($backups) === 0) { ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">No backups yet.</td></tr>
                <?php } ?>
                <?php while ($backup = mysqli_fetch_assoc($backups)) {

                    $backup_id = intval($backup['backup_id']);
                    $status = $backup['backup_status'];

                    $badge = 'secondary';
                    if ($status === 'Complete') { $badge = 'success'; }
                    if ($status === 'Failed') { $badge = 'danger'; }
                    if ($status === 'Missing') { $badge = 'warning'; }
                    if ($status === 'Running' || $status === 'Pending') { $badge = 'info'; }
                ?>
                    <tr>
                        <td><?= escapeHtml(backupTypeLabel($backup['backup_type'])) ?></td>
                        <td><?= escapeHtml($backup['backup_created_at']) ?></td>
                        <td><?= $backup['backup_size'] > 0 ? escapeHtml(backupFormatBytes($backup['backup_size'])) : '-' ?></td>
                        <td><?= escapeHtml($backup['backup_source']) ?></td>
                        <td>
                            <span class="badge badge-<?= $badge ?>"><?= escapeHtml($status) ?></span>
                            <?php if (!empty($backup['backup_error'])) { ?>
                                <br><small class="text-danger"><?= escapeHtml($backup['backup_error']) ?></small>
                            <?php } ?>
                        </td>
                        <td class="text-end">
                            <?php if ($status === 'Complete') { ?>
                                <a class="btn btn-sm btn-primary" href="backup_download.php?backup_id=<?= $backup_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                    <i class="fas fa-fw fa-download"></i>
                                </a>
                            <?php } ?>
                            <a class="btn btn-sm btn-danger confirm-link" href="post.php?delete_backup=<?= $backup_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                <i class="fas fa-fw fa-trash"></i>
                            </a>
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card card-dark mb-3">
    <div class="card-header py-3">
        <h3 class="card-title"><i class="fas fa-fw fa-lock me-2"></i>Encryption Key</h3>
    </div>
    <div class="card-body">
        <div class="alert alert-warning mb-3">
            <i class="fas fa-fw fa-exclamation-triangle me-2"></i>
            <strong>Write this down and keep it somewhere other than this server.</strong>
            Every backup is encrypted with it, and without it a backup cannot be restored - not by you,
            not by anyone. It is stored in config.php and never in the database, which is what stops a
            stolen backup from carrying its own key.
        </div>

        <?php if ($backup_key !== '') { ?>
            <div class="input-group col-md-6 px-0">
                <input type="text" class="form-control font-monospace" value="<?= escapeHtml($backup_key) ?>" readonly onclick="this.select();">
                    <button class="btn btn-secondary" type="button" onclick="navigator.clipboard.writeText('<?= escapeHtml($backup_key) ?>');">
                        <i class="fas fa-fw fa-copy"></i>
                    </button>
            </div>
        <?php } ?>

        <p class="text-muted small mt-3 mb-0">
            Archives are AES-256 encrypted zips. <strong>7-Zip, WinZip, PeaZip and Keka</strong> can open them with this key.
            The <code>unzip</code> command, Windows Explorer and the macOS Archive Utility cannot - they do not support AES.
        </p>
    </div>
</div>

<div class="card card-dark mb-3">
    <div class="card-header py-3">
        <h3 class="card-title"><i class="fas fa-fw fa-clock me-2"></i>Scheduled Backups &amp; Retention</h3>
    </div>
    <div class="card-body">
        <form action="post.php" method="POST" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

            <div class="row g-2">
                <div class="mb-3 col-md-4">
                    <label>Scheduled backup type</label>
                    <select class="form-select" name="config_backup_cron_type">
                        <option <?= $config_backup_cron_type === 'full' ? 'selected' : '' ?> value="full">Full Backup</option>
                        <option <?= $config_backup_cron_type === 'database' ? 'selected' : '' ?> value="database">Database Only</option>
                    </select>
                </div>
                <div class="mb-3 col-md-4">
                    <label>Keep backups for (days)</label>
                    <input type="number" class="form-control" name="config_backup_retention_days" min="0" value="<?= intval($config_backup_retention_days) ?>">
                    <small class="text-muted">0 disables age-based deletion.</small>
                </div>
                <div class="mb-3 col-md-4">
                    <label>Keep at most (per type)</label>
                    <input type="number" class="form-control" name="config_backup_retention_count" min="1" value="<?= intval($config_backup_retention_count) ?>">
                    <small class="text-muted">Counted separately for each type. The newest of each is never deleted.</small>
                </div>
            </div>

            <button type="submit" name="edit_backup_settings" class="btn btn-primary"><i class="fas fa-fw fa-check me-2"></i>Save</button>
        </form>

        <hr>

        <p class="mb-0">
            <?php if (!empty($backup_job) && intval($backup_job['cron_job_enabled']) === 1) { ?>
                <i class="fas fa-fw fa-check text-success me-2"></i>Scheduled backups run daily at
                <strong><?= escapeHtml(substr((string)$backup_job['cron_job_daily_at'], 0, 5)) ?></strong>.
            <?php } else { ?>
                <i class="fas fa-fw fa-times text-danger me-2"></i>Scheduled backups are switched off.
            <?php } ?>
            Turn them on or change the time in <a href="cron.php">Maintenance &gt; Cron</a>.
        </p>
        <p class="text-muted small mt-2 mb-0">
            Old backups are removed by the nightly job, never by the backup itself, so a failed nightly
            cannot delete an archive that was never replaced.
        </p>
    </div>
</div>

<div class="card card-dark">
    <div class="card-header py-3">
        <h3 class="card-title"><i class="fas fa-fw fa-undo me-2"></i>Restoring</h3>
    </div>
    <div class="card-body">
        <p>Restoring replaces the database and the uploads folder with what is in the archive. It cannot be done from here, on purpose - a running install is the wrong place to be dropping its own tables from a browser.</p>
        <p class="mb-2"><strong>From the command line</strong> - the only option that works for large backups:</p>
        <pre class="bg-dark text-white p-2"><?= escapeHtml("php " . dirname(__DIR__) . "/scripts/restore_cli.php --file=/path/to/backup.zip") ?></pre>
        <p class="mb-0"><strong>From a browser</strong>, on a fresh install only, the setup wizard has a restore step at <code>/setup</code>. Once an install has users, that step closes itself.</p>
    </div>
</div>

<div class="modal" id="masterKeyModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-fw fa-key me-2"></i>Export Master Key</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="post.php" method="POST" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <div class="modal-body">
                    <div class="alert alert-warning">
                        This key decrypts every credential in this install. It is shown on screen and is not written anywhere.
                    </div>
                    <div class="mb-3">
                        <label>Confirm your account password</label>
                        <input type="password" class="form-control" name="password" autocomplete="new-password" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="backup_master_key" class="btn btn-primary"><i class="fas fa-fw fa-key me-2"></i>Show Master Key</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
require_once "../includes/footer.php";
