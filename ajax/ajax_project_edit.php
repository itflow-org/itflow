<?php

require_once '../includes/ajax_header.php';

$project_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT * FROM projects WHERE project_id = $project_id LIMIT 1");

$row = mysqli_fetch_array($sql);
$project_prefix = nullable_htmlentities($row['project_prefix']);
$project_number = intval($row['project_number']);
$project_name = nullable_htmlentities($row['project_name']);
$project_description = nullable_htmlentities($row['project_description']);
$project_due = nullable_htmlentities($row['project_due']);
$project_created_at = nullable_htmlentities($row['project_created_at']);
$project_created_at_display = date("Y-m-d", strtotime($project_created_at));
$project_updated_at = nullable_htmlentities($row['project_updated_at']);
$project_completed_at = nullable_htmlentities($row['project_completed_at']);
$project_completed_at_display = date("Y-m-d", strtotime($project_completed_at));
$project_archived_at = nullable_htmlentities($row['project_archived_at']);
$client_id = intval($row['project_client_id']);
$project_manager = intval($row['project_manager']);

// Generate the HTML form content using output buffering.
ob_start();
?>

<div class="modal-header">
    <h5 class="modal-title">
        <i class="fas fa-fw fa-project-diagram mr-2"></i>Editing Project: <strong><?php echo $project_name; ?></strong>
    </h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="project_id" value="<?php echo $project_id; ?>">
    <div class="modal-body bg-white">
        <div class="form-group">
            <label>Project Name <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-project-diagram"></i></span>
                </div>
                <input type="text" class="form-control" name="name" placeholder="Project Name" maxlength="255" value="<?php echo $project_name; ?>" required autofocus>
            </div>
        </div>
        <div class="form-group">
            <label>Description</label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-angle-right"></i></span>
                </div>
                <input type="text" class="form-control" name="description" placeholder="Description" value="<?php echo $project_description; ?>">
            </div>
        </div>
        <div class="form-group">
            <label>Date Due <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                </div>
                <input type="date" class="form-control" name="due_date" value="<?php echo $project_due; ?>" required>
            </div>
        </div>
        <div class="form-group">
            <label>Manager</label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-user-tie"></i></span>
                </div>
                <select class="form-control select2" name="project_manager">
                    <option value="0">No Manager</option>
                    <?php
                    $sql_project_managers_select = mysqli_query(
                        $mysqli,
                        "SELECT user_id, user_name FROM users
                        WHERE user_role_id > 1 AND user_status = 1 AND user_archived_at IS NULL ORDER BY user_name ASC"
                    );
                    while ($row = mysqli_fetch_array($sql_project_managers_select)) {
                        $user_id_select = intval($row['user_id']);
                        $user_name_select = nullable_htmlentities($row['user_name']); ?>
                        <option <?php if ($project_manager == $user_id_select) { echo "selected"; } ?> value="<?php echo $user_id_select; ?>"><?php echo $user_name_select; ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>
    </div>
    <div class="modal-footer bg-white">
        <button type="submit" name="edit_project" class="btn btn-primary text-bold">
            <i class="fas fa-check mr-2"></i>Save
        </button>
        <button type="button" class="btn btn-light" data-dismiss="modal">
            <i class="fa fa-times mr-2"></i>Cancel
        </button>
    </div>
</form>

<?php

require_once "../includes/ajax_footer.php";
