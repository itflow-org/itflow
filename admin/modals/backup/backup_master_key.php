<?php

/*
 * Maintenance > Backup - export the credential vault's master key. It only exists unwrapped
 * inside a session, so the admin's own password is asked for again and is what unwraps it
 * (admin/post/backup.php, backup_master_key). The key is shown once on the page that
 * follows, and an encrypted copy is filed with the backups.
 */

require_once '../../includes/modal_header.php';

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-key me-2"></i>Export the master key</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="modal-body">
        <p class="fw-semibold mb-2">This key opens every credential saved in ITFlow.</p>
        <ul class="small mb-3 ps-3">
            <li>It is shown once, on the next page - copy it somewhere as safe as the credentials themselves.</li>
            <li>An encrypted copy is filed with the backups, opened with the backup encryption key.</li>
            <li>You only need it if every user's password is lost. A restored backup brings the vault back by itself.</li>
            <li>Exporting it is logged, and every agent gets a notification that you did.</li>
        </ul>

        <label class="form-label" for="backupMasterKeyPassword">Your password <strong class="text-danger">*</strong></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-fw fa-lock"></i></span>
            <input type="password" class="form-control" data-toggle="password" name="password" id="backupMasterKeyPassword" placeholder="To confirm it's you" autocomplete="current-password" required autofocus>
            <span class="input-group-text"><i class="fa fa-fw fa-eye"></i></span>
        </div>
    </div>
    <div class="modal-footer">
        <button type="submit" name="backup_master_key" class="btn btn-primary text-bold"><i class="fas fa-fw fa-key me-2"></i>Show the master key</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once "../../../includes/modal_footer.php";
