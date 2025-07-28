<?php

require_once '../includes/ajax_header.php';

$task_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT * FROM tasks
    WHERE task_id = $task_id
    LIMIT 1"
);

$row = mysqli_fetch_array($sql);
$task_name = nullable_htmlentities($row['task_name']);
$task_completion_estimate = intval($row['task_completion_estimate']);
$task_completed_at = nullable_htmlentities($row['task_completed_at']);

// Generate the HTML form content using output buffering.
ob_start();

?>

<div class="modal-header">
    <h5 class="modal-title"><i class="fa fa-fw fa-tasks mr-2"></i>Editing task</h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="task_id" value="<?php echo $task_id; ?>">
    
    <div class="modal-body bg-white">

        <div class="form-group">
            <label>Name <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                </div>
                <input type="text" class="form-control" name="name" placeholder="Name the task" maxlength="255" value="<?php echo $task_name; ?>" required autofocus>
            </div>
        </div>

        <div class="form-group">
            <label>Estimated Completion Time <span class="text-secondary">(Minutes)</span></label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-clock"></i></span>
                </div>
                <input type="number" class="form-control" name="completion_estimate" placeholder="Estimated time to complete task in mins" value="<?php echo $task_completion_estimate; ?>">
            </div>
        </div>
    
    </div>

    <div class="modal-footer bg-white">
        <button type="submit" name="edit_ticket_task" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>

</form>

<?php

require_once "../includes/ajax_footer.php";
