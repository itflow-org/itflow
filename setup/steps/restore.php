<?php

/*
 * Setup - Restore a backup: an ITFlow backup archive brought back into an
 * install that has its tables but no users yet (restore in setup/index.php).
 * Dropped or picked, it is sent with how far it has got shown (js/setup.js);
 * one larger than this server takes is pointed at the command line before it
 * is sent. One that has users restores from the command line instead.
 */

defined('FROM_SETUP') || die("Direct file access is not allowed");

$restore_command = "php " . dirname(__DIR__, 2) . "/scripts/restore_cli.php --file=/path/to/backup.zip";
$command_field = function ($id) use ($restore_command) {
    ob_start(); ?>
    <div class="input-group input-group-sm mt-2">
        <input type="text" class="form-control font-monospace" id="<?= $id ?>" value="<?= escapeHtml($restore_command) ?>" aria-label="Restore command" readonly>
        <button type="button" class="btn btn-outline-secondary clipboardjs" data-clipboard-text="<?= escapeHtml($restore_command) ?>" title="Copy"><i class="far fa-copy"></i></button>
    </div>
    <?php return ob_get_clean();
};

?>
<div class="card itflow-setup-card">
    <div class="card-body p-4">
        <h1 class="h4 fw-semibold mb-1"><i class="fas fa-upload text-body-secondary me-2"></i>Restore a backup</h1>
        <p class="text-body-secondary mb-4">Bring an ITFlow backup back into this install - its database and its uploaded files.</p>

        <?php if ($install_is_live) { ?>
            <div class="alert alert-warning mb-0" data-restore-state="live">
                <i class="fas fa-exclamation-triangle me-2"></i>This install already has users, so it can't be restored over from here. Restore from the command line instead:
                <?= $command_field('setupRestoreCommandLive') ?>
            </div>
        <?php } elseif (!$can_show_restore) { ?>
            <div class="alert alert-info" data-restore-state="no-database">
                <i class="fas fa-info-circle me-2"></i>Set up the database first - an empty one - and the backup is restored into it.
            </div>
            <a href="?<?= file_exists('../config.php') ? 'database' : 'checks' ?>" class="btn btn-primary">Set up the database<i class="fas fa-arrow-right ms-2"></i></a>
        <?php } else {
            $setup_max_upload = backupMaxUploadBytes(); ?>
            <form method="post" action="?restore" enctype="multipart/form-data" autocomplete="off" data-restore-form data-setup-form novalidate>
                <div class="mb-3">
                    <label class="form-label" for="setupRestoreFile">Backup archive (.zip) <strong class="text-danger">*</strong></label>
                    <label class="itflow-setup-drop" for="setupRestoreFile" data-setup-drop>
                        <i class="fas fa-file-archive fa-2x text-body-secondary mb-2"></i>
                        <span class="fw-semibold" data-setup-drop-name>Drop the backup here, or choose it</span>
                        <span class="small text-body-secondary" data-setup-drop-size>A .zip from Maintenance &gt; Backup - up to <?= escapeHtml(backupFormatBytes($setup_max_upload)) ?> here</span>
                    </label>
                    <div class="input-group has-validation">
                        <span class="input-group-text"><i class="fa fa-fw fa-file-archive"></i></span>
                        <input type="file" class="form-control" id="setupRestoreFile" name="backup_zip" accept=".zip,application/zip" required data-setup-max-bytes="<?= intval($setup_max_upload) ?>">
                    </div>
                </div>

                <div class="alert alert-warning" data-restore-too-big hidden>
                    <div class="fw-semibold mb-1"><i class="fas fa-exclamation-triangle me-2"></i>That backup is larger than this server takes through a browser</div>
                    Copy it onto the server and restore it from the command line - there is no size limit there:
                    <?= $command_field('setupRestoreCommandTooBig') ?>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="setupRestoreKey">Backup encryption key <strong class="text-danger">*</strong></label>
                    <div class="input-group has-validation">
                        <span class="input-group-text"><i class="fa fa-fw fa-key"></i></span>
                        <input type="text" class="form-control font-monospace" id="setupRestoreKey" name="backup_key" placeholder="The key from the install that made this backup" autocomplete="off" spellcheck="false" required>
                    </div>
                    <div class="form-text">Shown in Maintenance &gt; Backup on that install. The archive can't be opened without it.</div>
                </div>

                <div class="mb-3" data-restore-progress hidden>
                    <div class="d-flex justify-content-between small mb-1">
                        <span class="fw-semibold" data-restore-progress-label>Sending the backup</span>
                        <span class="text-body-secondary" data-restore-progress-amount></span>
                    </div>
                    <div class="progress" role="progressbar" aria-label="Restore progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" style="height: .75rem;">
                        <div class="progress-bar" style="width: 0%"></div>
                    </div>
                    <div class="small text-body-secondary mt-1">The restore replaces the database and the uploads folder. A large one takes several minutes - keep this page open.</div>
                </div>
                <div class="alert alert-danger" data-restore-failed hidden></div>

                <details class="small text-body-secondary mb-3">
                    <summary>Restoring from the command line instead</summary>
                    <div class="mt-2">Any size, and nothing to upload - copy the backup onto this server and run:</div>
                    <?= $command_field('setupRestoreCommand') ?>
                </details>

                <div class="d-flex justify-content-between align-items-center gap-2 mt-4">
                    <a href="index.php" class="btn btn-link px-0 text-nowrap text-decoration-none"><i class="fas fa-arrow-left me-2"></i>Back</a>
                    <button type="submit" name="restore" class="btn btn-primary" data-setup-next>Restore the backup<i class="fas fa-upload ms-2"></i></button>
                </div>
            </form>
        <?php } ?>
    </div>
</div>
