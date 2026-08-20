<?php

require_once '../../includes/modal_header.php';

$task_template_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT task_template_completion_estimate, task_template_name, task_template_order FROM task_templates WHERE task_template_id = $task_template_id LIMIT 1");

$row = mysqli_fetch_assoc($sql);
$task_template_name = escapeHtml($row['task_template_name']);
$task_template_order = intval($row['task_template_order']);
$task_template_completion_estimate = intval($row['task_template_completion_estimate']);
//$task_template_description = escapeHtml($row['task_template_description']);

// Generate the HTML form content using output buffering.
ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-tasks me-2"></i>Editing task</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="task_template_id" value="<?= $task_template_id ?>">

    <div class="modal-body">

        <div class="mb-3">
            <label>Name <strong class="text-danger">*</strong></label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                <input type="text" class="form-control" name="name" placeholder="Name the task" maxlength="255" value="<?= $task_template_name ?>" required autofocus>
            </div>
        </div>

        <div class="mb-3">
            <label>Estimated Completion Time <span class="text-secondary">(Minutes)</span></label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-clock"></i></span>
                <input type="number" class="form-control" name="completion_estimate" placeholder="Estimated time to complete task in mins" value="<?= $task_template_completion_estimate ?>">
            </div>
        </div>

    </div>

    <div class="modal-footer">
        <button type="submit" name="edit_ticket_template_task" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>

</form>

<?php

require_once '../../../includes/modal_footer.php';
