<?php

/*
 * Get started - Backups: the backup key, to be kept somewhere other than this
 * server, and the nightly backup - when, what and how many to keep
 * (getting_started_backups, gettingStartedBackupSave()). The same job as
 * Maintenance > Cron, and the same settings as Maintenance > Backup.
 */

defined('FROM_GETTING_STARTED') || die("Direct file access is not allowed");

$gs_key = backupEncryptionKey();
$gs_job = gettingStartedBackupJob();
$gs_backup = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT config_backup_cron_type, config_backup_retention_count, config_backup_retention_days FROM settings WHERE company_id = 1")) ?: [];
// Offered on - turning it on is what the step is for
$gs_backup_on = settingsChecked('backup_enabled', true);

?>
<?php if ($gs_key === '') { ?>
    <div class="alert alert-danger d-flex gap-2 align-items-start" data-gs-backup-no-key>
        <i class="fas fa-exclamation-triangle mt-1"></i>
        <div>ITFlow couldn't write a backup key to <strong>config.php</strong>, so it can't make an encrypted backup. Make config.php writable by the web server's user and come back to this page - or add <code>$config_backup_key = '&lt;32 random characters&gt;';</code> to it yourself.</div>
    </div>
<?php } else { ?>
    <div class="itflow-gs-key mb-4" data-gs-backup-key>
        <div class="fw-semibold mb-1"><i class="fas fa-key text-body-secondary me-2"></i>Your backup key</div>
        <p class="small text-body-secondary mb-2">Every backup is locked with it, and without it none can be restored - on this server or another. Keep a copy somewhere other than this server, as a password manager.</p>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-lock"></i></span>
            <input type="password" class="form-control font-monospace" value="<?= escapeHtml($gs_key) ?>" id="gsBackupKey" aria-label="Backup key" readonly data-gs-secret>
            <button type="button" class="btn btn-outline-secondary" data-gs-show="gsBackupKey" title="Show"><i class="far fa-eye"></i></button>
            <button type="button" class="btn btn-outline-secondary clipboardjs" data-clipboard-text="<?= escapeHtml($gs_key) ?>" title="Copy"><i class="far fa-copy"></i></button>
        </div>
    </div>
<?php } ?>

<form action="post.php" method="post" autocomplete="off" data-gs-form>
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <?= settingsSwitch('backup_enabled', $gs_backup_on, ['id' => 'gsBackupOn', 'label' => 'Back up every night', 'data' => ['gs-reveal' => 'gsBackupWhen'],
        'disabled' => $gs_key === '', 'help' => 'Cron takes it while no one is about. Old ones are cleared by the same job, never before a new one is made.']) ?>

    <div id="gsBackupWhen" class="mt-3" data-gs-reveal-target<?= $gs_backup_on ? '' : ' hidden' ?>>
        <div class="row g-3 mb-3">
            <div class="col-sm-5">
                <?= settingsInput('backup_daily_at', $gs_job['daily_at'], ['icon' => 'fa-clock', 'type' => 'time', 'label' => 'At', 'required' => true]) ?>
            </div>
            <div class="col-sm-7">
                <?= settingsSelect('config_backup_cron_type', (string) ($gs_backup['config_backup_cron_type'] ?? 'full'), ['full' => 'Everything - the database and your files', 'database' => 'The database only'], ['icon' => 'fa-archive', 'label' => 'What it takes']) ?>
            </div>
            <div class="col-sm-6">
                <?= settingsInput('config_backup_retention_count', intval($gs_backup['config_backup_retention_count'] ?? 5), ['icon' => 'fa-layer-group', 'type' => 'number', 'label' => 'Keep the last', 'min' => 1, 'max' => 1000, 'required' => true, 'inputmode' => 'numeric', 'suffix' => 'backups']) ?>
            </div>
            <div class="col-sm-6">
                <?= settingsInput('config_backup_retention_days', intval($gs_backup['config_backup_retention_days'] ?? 30), ['icon' => 'fa-calendar-alt', 'type' => 'number', 'label' => 'For up to', 'min' => 0, 'max' => 3650, 'required' => true, 'inputmode' => 'numeric', 'suffix' => 'days',
                    'help' => '0 keeps them however old they are.']) ?>
            </div>
        </div>
        <?php if (!$gs_job['enabled'] && $gs_key !== '') { ?>
            <?= settingsSwitch('backup_key_saved', false, ['id' => 'gsBackupKeySaved', 'label' => "I've kept a copy of the backup key somewhere else"]) ?>
        <?php } ?>
    </div>

    <p class="small text-body-secondary mt-3">Backups are kept in <code><?= escapeHtml(backupStorageDir()) ?></code>. Copy them somewhere else too - a backup on the same disk goes with it. Maintenance &gt; Backup downloads one, or takes one now.</p>

    <button type="submit" name="getting_started_backups" value="1" class="btn btn-primary" data-gs-save<?= $gs_key === '' ? ' disabled' : '' ?>>Save and continue<i class="fas fa-arrow-right ms-2"></i></button>
</form>
