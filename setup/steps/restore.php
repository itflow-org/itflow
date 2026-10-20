<?php

/*
 * Setup - Restore a backup: an ITFlow backup archive brought back into an
 * install that has its tables but no users yet (restore in setup/index.php).
 * One that has users restores from the command line instead.
 */

defined('FROM_SETUP') || die("Direct file access is not allowed");

?>
<div class="card itflow-setup-card">
    <div class="card-body p-4">
        <h1 class="h4 fw-semibold mb-1"><i class="fas fa-upload text-body-secondary me-2"></i>Restore a backup</h1>
        <p class="text-body-secondary mb-4">Bring an ITFlow backup back into this install - its database and its uploaded files.</p>

        <?php if ($install_is_live) { ?>
            <div class="alert alert-warning mb-0" data-restore-state="live">
                <i class="fas fa-exclamation-triangle me-2"></i>This install already has users, so it can't be restored over from here. Restore from the command line instead:
                <pre class="bg-dark text-white rounded p-2 mt-2 mb-0"><?= escapeHtml("php " . dirname(__DIR__, 2) . "/scripts/restore_cli.php --file=/path/to/backup.zip") ?></pre>
            </div>
        <?php } elseif (!$can_show_restore) { ?>
            <div class="alert alert-info" data-restore-state="no-database">
                <i class="fas fa-info-circle me-2"></i>Set up the database first - an empty one - and the backup is restored into it.
            </div>
            <a href="?<?= file_exists('../config.php') ? 'database' : 'checks' ?>" class="btn btn-primary">Set up the database<i class="fas fa-arrow-right ms-2"></i></a>
        <?php } else {
            $setup_max_upload = backupMaxUploadBytes(); ?>
            <form method="post" enctype="multipart/form-data" autocomplete="off" data-restore-form>
                <div class="mb-3">
                    <label class="form-label" for="setupRestoreFile">Backup archive (.zip) <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-file-archive"></i></span>
                        <input type="file" class="form-control" id="setupRestoreFile" name="backup_zip" accept=".zip" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="setupRestoreKey">Backup encryption key <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-key"></i></span>
                        <input type="text" class="form-control" id="setupRestoreKey" name="backup_key" placeholder="The key from the install that made this backup" autocomplete="off" required>
                    </div>
                    <div class="form-text">Shown in Maintenance &gt; Backup on that install. The archive can't be opened without it.</div>
                </div>

                <div class="alert alert-light border small mb-3">
                    <strong>This server takes uploads up to <?= escapeHtml(backupFormatBytes($setup_max_upload)) ?>.</strong>
                    A full backup is often larger. If yours is, copy it onto the server and restore it from the command line - there is no size limit there:
                    <pre class="bg-dark text-white rounded p-2 mt-2 mb-0"><?= escapeHtml("php " . dirname(__DIR__, 2) . "/scripts/restore_cli.php --file=/path/to/backup.zip") ?></pre>
                </div>
                <p class="small text-body-secondary">The restore replaces the database and the uploads folder. A large one takes several minutes - keep this page open.</p>

                <div class="d-flex justify-content-between align-items-center gap-2 mt-4">
                    <a href="index.php" class="btn btn-link px-0 text-nowrap text-decoration-none"><i class="fas fa-arrow-left me-2"></i>Back</a>
                    <button type="submit" name="restore" class="btn btn-primary">Restore the backup<i class="fas fa-upload ms-2"></i></button>
                </div>
            </form>
        <?php } ?>
    </div>
</div>
