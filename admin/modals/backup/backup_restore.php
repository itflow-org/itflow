<?php

/*
 * Maintenance > Backup - restore a backup in the list (functions/backup_restore.php). What's in
 * it first, then the admin's password and RESTORE typed to confirm; admin/post/backup.php,
 * restore_backup, queues it for cron.
 */

require_once '../../includes/modal_header.php';

[$restore_row, $restore_path] = backupRestoreStored($mysqli, intval($_GET['backup_id'] ?? 0));
$restore_meta = null;
$restore_problem = '';
$restore_needs_key = false;
if ($restore_row) {
    [$restore_meta, $restore_problem] = backupRestoreCheck($mysqli, $restore_path, backupRestoreKey(''));
    // Made with another install's key - it opens once that key is typed
    $restore_needs_key = !$restore_meta;
}
$restore_can = $restore_row && ($restore_problem === '' || $restore_needs_key) && !backupRestoreInProgress() && backupEncryptionKey() !== '';
$restore_with_uploads = !$restore_meta || in_array('uploads.zip', $restore_meta['entries'], true);

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-undo me-2"></i>Restore a Backup</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<form action="post.php" method="post" autocomplete="off" data-backup-restore>
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="backup_id" value="<?= intval($restore_row['backup_id'] ?? 0) ?>">

    <div class="modal-body">
        <?php if (!$restore_row) { ?>
            <div class="alert alert-danger mb-0"><?= escapeHtml($restore_path) ?></div>
        <?php } else { ?>
            <dl class="row small mb-3" data-backup-restore-details>
                <dt class="col-sm-4 text-body-secondary fw-normal">Backup</dt>
                <dd class="col-sm-8"><?= escapeHtml(backupTypeLabel((string) $restore_row['backup_type'])) ?>, <?= escapeHtml(backupFormatBytes($restore_row['backup_size'])) ?></dd>
                <dt class="col-sm-4 text-body-secondary fw-normal">In the list since</dt>
                <dd class="col-sm-8"><?= escapeHtml(substr((string) $restore_row['backup_created_at'], 0, 16)) ?> - <?= escapeHtml(backupMadeBy($restore_row)) ?></dd>
                <?php if ($restore_meta) { ?>
                    <dt class="col-sm-4 text-body-secondary fw-normal">Taken</dt>
                    <dd class="col-sm-8"><?= escapeHtml($restore_meta['generated']) ?></dd>
                    <dt class="col-sm-4 text-body-secondary fw-normal">Database version</dt>
                    <dd class="col-sm-8"><?= escapeHtml($restore_meta['database_version']) ?> <span class="text-body-secondary">- this ITFlow is at <?= escapeHtml(backupCurrentDatabaseVersion($mysqli)) ?></span></dd>
                    <dt class="col-sm-4 text-body-secondary fw-normal">Holds</dt>
                    <dd class="col-sm-8"><?= $restore_with_uploads ? 'The database and everything uploaded' : 'The database only' ?></dd>
                <?php } ?>
            </dl>

            <?php if (backupEncryptionKey() === '') { ?>
                <div class="alert alert-danger mb-0">There's no encryption key, so ITFlow can't back itself up before a restore. See the top of this page.</div>
            <?php } elseif (backupRestoreInProgress()) { ?>
                <div class="alert alert-info mb-0">A restore is running - wait for it to finish.</div>
            <?php } elseif ($restore_problem !== '' && !$restore_needs_key) { ?>
                <div class="alert alert-danger mb-0" data-backup-restore-problem><?= escapeHtml($restore_problem) ?></div>
            <?php } else { ?>
                <div class="alert alert-warning small">
                    <strong>This replaces everything</strong> - the database<?= $restore_with_uploads ? ' and every file uploaded to ITFlow' : '' ?> - with what's in the backup.
                    ITFlow is backed up as it is first and kept as <strong>Before restore</strong>, so this can be undone.
                    Cron does it within a minute, and it takes a minute or more - anyone working then may see errors.
                    Afterwards, sign in with an account from the backup if you're asked to.
                </div>

                <?php if ($restore_needs_key) { ?>
                    <label class="form-label" for="restoreKey">Its encryption key <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-fw fa-key"></i></span>
                        <input type="password" class="form-control" data-toggle="password" name="restore_key" id="restoreKey" placeholder="From Backup on the ITFlow that made it" autocomplete="off" required>
                        <span class="input-group-text"><i class="fas fa-fw fa-eye"></i></span>
                    </div>
                    <div class="form-text mb-3">This ITFlow's key doesn't open it - it was made on another ITFlow, whose Maintenance &gt; Backup has its key. <span class="text-body-secondary">(<?= escapeHtml($restore_problem) ?>)</span></div>
                <?php } ?>

                <label class="form-label" for="restorePassword">Your password <strong class="text-danger">*</strong></label>
                <div class="input-group mb-3">
                    <span class="input-group-text"><i class="fas fa-fw fa-lock"></i></span>
                    <input type="password" class="form-control" data-toggle="password" name="password" id="restorePassword" placeholder="To confirm it's you" autocomplete="current-password" required>
                    <span class="input-group-text"><i class="fas fa-fw fa-eye"></i></span>
                </div>

                <label class="form-label" for="restoreConfirm">Type <strong>RESTORE</strong> to confirm <strong class="text-danger">*</strong></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-fw fa-keyboard"></i></span>
                    <input type="text" class="form-control" name="confirm" id="restoreConfirm" pattern="RESTORE" placeholder="RESTORE" autocomplete="off" required>
                </div>
            <?php } ?>
        <?php } ?>
    </div>
    <div class="modal-footer">
        <?php if ($restore_can) { ?>
            <button type="submit" name="restore_backup" class="btn btn-danger fw-bold"><i class="fas fa-fw fa-undo me-2"></i>Restore</button>
        <?php } ?>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once "../../../includes/modal_footer.php";
