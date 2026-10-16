<?php

/*
 * Bulk - set the manager of the projects ticked on the projects list. Open
 * ones only; completed and archived ones are left as they are.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support', 2);

$project_ids = array_values(array_unique(array_filter(array_map('intval', (array) ($_GET['project_ids'] ?? [])))));
$count = count($project_ids);

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-user-tie me-2"></i>Set the manager of <strong><?= $count ?></strong> project<?= $count === 1 ? '' : 's' ?></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <?php foreach ($project_ids as $project_id) { ?><input type="hidden" name="project_ids[]" value="<?= $project_id ?>"><?php } ?>

    <div class="modal-body">
        <label class="form-label">Manager</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-fw fa-user-tie"></i></span>
            <select class="form-select select2" name="project_manager">
                <option value="0" data-icon="fas fa-user-slash">No manager</option>
                <?php
                $sql = mysqli_query($mysqli, "SELECT user_id, user_name, user_avatar FROM users WHERE user_type = 1 AND user_status = 1 AND user_archived_at IS NULL
                    ORDER BY user_id = " . intval($session_user_id) . " DESC, user_name ASC");
                while ($row = mysqli_fetch_assoc($sql)) {
                    $user_id = intval($row['user_id']); ?>
                    <option value="<?= $user_id ?>"<?= selectOptionUser($row['user_id'], $row['user_name'], $row['user_avatar'] ?? '') ?>><?= escapeHtml($row['user_name']) ?><?= $user_id === intval($session_user_id) ? ' (me)' : '' ?></option>
                <?php } ?>
            </select>
        </div>
        <div class="form-text">Open projects only - completed and archived ones keep theirs. The new manager gets a notification.</div>
    </div>

    <div class="modal-footer">
        <button type="submit" name="bulk_edit_project_manager" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Set manager</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
