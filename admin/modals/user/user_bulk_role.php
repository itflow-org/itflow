<?php

/*
 * Bulk - give the users ticked on the users list one role. Never the admin
 * themselves, and the only active admin keeps an admin role.
 */

require_once '../../includes/modal_header.php';

$user_ids = array_values(array_diff(array_unique(array_filter(array_map('intval', (array) ($_GET['user_ids'] ?? [])))), [intval($session_user_id)]));
$count = count($user_ids);
$roles = userRoles();

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-user-tag me-2"></i>Role for <strong><?= $count ?></strong> user<?= $count === 1 ? '' : 's' ?></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <?php foreach ($user_ids as $user_id) { ?><input type="hidden" name="user_ids[]" value="<?= $user_id ?>"><?php } ?>

    <div class="modal-body">
        <label class="form-label">Role <strong class="text-danger">*</strong></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-fw fa-user-shield"></i></span>
            <select class="form-select select2" name="role" required>
                <option value="">- Pick a role -</option>
                <?php foreach ($roles as $role_id => $role) { ?>
                    <option value="<?= $role_id ?>"<?= selectOptionRole($role) ?>><?= escapeHtml($role['role_name']) ?></option>
                <?php } ?>
            </select>
        </div>
        <div class="form-text">The only active admin keeps an admin role, so there is always someone to run ITFlow.</div>
    </div>
    <div class="modal-footer">
        <button type="submit" name="bulk_role_users" class="btn btn-primary text-bold" <?= $count ? '' : 'disabled' ?>><i class="fas fa-check me-2"></i>Set role</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once "../../../includes/modal_footer.php";
