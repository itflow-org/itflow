<?php

/*
 * Bulk - move the due date of the projects ticked on the projects list, or
 * clear it. Open ones only, and not to before one starts.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support', 2);

$project_ids = array_values(array_unique(array_filter(array_map('intval', (array) ($_GET['project_ids'] ?? [])))));
$count = count($project_ids);

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-calendar-day me-2"></i>Set the due date of <strong><?= $count ?></strong> project<?= $count === 1 ? '' : 's' ?></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <?php foreach ($project_ids as $project_id) { ?><input type="hidden" name="project_ids[]" value="<?= $project_id ?>"><?php } ?>

    <div class="modal-body">
        <label class="form-label">Due</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-fw fa-flag-checkered"></i></span>
            <input type="date" class="form-control" name="due_date">
        </div>
        <div class="form-text">Leave it empty to clear the due date. Open projects only, and not one that starts after it.</div>
    </div>

    <div class="modal-footer">
        <button type="submit" name="bulk_edit_project_due" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Set due date</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
