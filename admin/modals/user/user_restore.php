<?php

/*
 * Restore User - back from the archive, with a role and a new password (the
 * old one, and their copy of the vault key, were wiped when archived).
 */

require_once '../../includes/modal_header.php';

$user = userForModal($_GET['id'] ?? 0);
$user_id = intval($user['user_id']);
$name = trim(str_replace(' (archived)', '', $user['user_name']));
$role_id = intval($user['user_role_id']);
$roles = userRoles($role_id);
$email_taken = mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM users WHERE user_email = '" . escapeSql($user['user_email']) . "' AND user_archived_at IS NULL AND user_id <> $user_id"));

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title text-truncate"><i class="fas fa-fw fa-redo-alt me-2"></i>Restore <?= escapeHtml($name) ?></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="user_id" value="<?= $user_id ?>">

    <div class="modal-body">
        <div class="d-flex align-items-center gap-3 mb-3">
            <?= userAvatar($user_id, $name, $user['user_avatar'], 'lg') ?>
            <div class="min-w-0">
                <div class="fw-semibold"><?= escapeHtml($name) ?></div>
                <div class="small text-body-secondary"><?= escapeHtml($user['user_email']) ?> · archived <?= escapeHtml(timeAgo($user['user_archived_at'])) ?></div>
            </div>
        </div>

        <?php if (agentUserLimitReached()) { ?>
            <div class="alert alert-danger small"><?= agentUserLimitMessage() ?></div>
        <?php } ?>
        <?php if ($email_taken) { ?>
            <div class="alert alert-warning small"><i class="fas fa-exclamation-triangle me-2"></i><strong><?= escapeHtml($user['user_email']) ?></strong> signs in to another account now - change that one's email first.</div>
        <?php } ?>

        <div class="mb-3">
            <label class="form-label">New password <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw fa-lock"></i></span>
                <input type="password" class="form-control font-monospace" data-toggle="password" name="new_password" id="userRestorePassword<?= $user_id ?>"
                    placeholder="At least <?= USER_PASSWORD_MIN ?> characters" minlength="<?= USER_PASSWORD_MIN ?>" autocomplete="new-password" required>
                <span class="input-group-text"><i class="fa fa-fw fa-eye"></i></span>
                <button class="btn btn-outline-secondary clipboardjs" type="button" title="Copy password" data-clipboard-target="#userRestorePassword<?= $user_id ?>"><i class="far fa-fw fa-copy"></i></button>
                <button class="btn btn-outline-secondary" type="button" title="Make one up - words that are easy to read out" data-user-restore-generate><i class="fas fa-fw fa-magic"></i></button>
            </div>
            <div class="form-text">Their old password was wiped when they were archived - give them this one, and have them change it.</div>
        </div>

        <div>
            <label class="form-label">Role <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw fa-user-shield"></i></span>
                <select class="form-select select2" name="role" required>
                    <?php if (!isset($roles[$role_id])) { ?>
                        <option value="">- Pick a role -</option>
                    <?php } ?>
                    <?php foreach ($roles as $id => $role) { ?>
                        <option value="<?= $id ?>" <?= $id === $role_id ? 'selected' : '' ?><?= selectOptionRole($role) ?>><?= escapeHtml($role['role_name']) ?><?= $role['role_archived_at'] ? ' (archived)' : '' ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>
    </div>
    <div class="modal-footer">
        <button type="submit" name="restore_user" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Restore</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<script>
(function () {
    var password = document.getElementById('userRestorePassword<?= $user_id ?>');
    password.closest('form').querySelector('[data-user-restore-generate]').addEventListener('click', function () {
        itflowGet('/agent/ajax.php', { get_readable_pass: 'true' }, function (data) {
            password.value = JSON.parse(data);
            password.type = 'text';
        });
    });
})();
</script>

<?php
require_once "../../../includes/modal_footer.php";
