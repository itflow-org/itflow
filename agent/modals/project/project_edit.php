<?php

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support', 2);

$project_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT project_archived_at, project_client_id, project_completed_at, project_created_at,
    project_description, project_due, project_manager, project_name, project_number,
    project_prefix, project_updated_at FROM projects WHERE project_id = $project_id LIMIT 1");

$row = mysqli_fetch_assoc($sql);
$project_prefix = escapeHtml($row['project_prefix']);
$project_number = intval($row['project_number']);
$project_name = escapeHtml($row['project_name']);
$project_description = escapeHtml($row['project_description']);
$project_due = escapeHtml($row['project_due']);
$project_created_at = escapeHtml($row['project_created_at']);
$project_created_at_display = date("Y-m-d", strtotime($project_created_at));
$project_updated_at = escapeHtml($row['project_updated_at']);
$project_completed_at = escapeHtml($row['project_completed_at']);
$project_completed_at_display = date("Y-m-d", strtotime($project_completed_at));
$project_archived_at = escapeHtml($row['project_archived_at']);
$client_id = intval($row['project_client_id']);
$project_manager = intval($row['project_manager']);

if ($client_id) {
    enforceClientAccess();
}

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title">
        <i class="fas fa-fw fa-project-diagram me-2"></i>Editing Project: <strong><?= $project_name ?></strong>
    </h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="project_id" value="<?= $project_id ?>">
    <div class="modal-body">
        <div class="mb-3">
            <label>Project Name <strong class="text-danger">*</strong></label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-project-diagram"></i></span>
                <input type="text" class="form-control" name="name" placeholder="Project Name" maxlength="255" value="<?= $project_name ?>" required autofocus>
            </div>
        </div>
        <div class="mb-3">
            <label>Description</label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-align-left"></i></span>
                <input type="text" class="form-control" name="description" placeholder="Description" value="<?= $project_description ?>">
            </div>
        </div>
        <div class="mb-3">
            <label>Date Due <strong class="text-danger">*</strong></label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                <input type="date" class="form-control" name="due_date" value="<?= $project_due ?>" required>
            </div>
        </div>
        <div class="mb-3">
            <label>Manager</label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-user-tie"></i></span>
                <select class="form-select select2" name="project_manager">
                    <option value="0">No Manager</option>
                    <?php
                    $sql_project_managers_select = mysqli_query(
                        $mysqli,
                        "SELECT user_id, user_name FROM users
                        WHERE user_role_id > 1 AND user_status = 1 AND user_archived_at IS NULL ORDER BY user_name ASC"
                    );
                    while ($row = mysqli_fetch_assoc($sql_project_managers_select)) {
                        $user_id_select = intval($row['user_id']);
                        $user_name_select = escapeHtml($row['user_name']); ?>
                        <option <?php if ($project_manager == $user_id_select) { echo "selected"; } ?> value="<?= $user_id_select ?>"><?= $user_name_select ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>
    </div>
    <div class="modal-footer">
        <button type="submit" name="edit_project" class="btn btn-primary text-bold">
            <i class="fas fa-check me-2"></i>Save
        </button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">
            <i class="fa fa-times me-2"></i>Cancel
        </button>
    </div>
</form>

<?php

require_once '../../../includes/modal_footer.php';
