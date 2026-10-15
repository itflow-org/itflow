<?php

require_once '../../includes/modal_header.php';

$project_template_id = intval($_GET['project_template_id'] ?? 0);

$row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT project_template_description, project_template_name FROM project_templates WHERE project_template_id = $project_template_id LIMIT 1"));
if (!$row) {
    echo json_encode(['error' => 'That project template no longer exists.']);
    exit;
}

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title text-truncate"><i class="fas fa-fw fa-cubes me-2"></i>Edit Project Template <small class="ms-1 opacity-75"><?= escapeHtml($row['project_template_name']) ?></small></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="project_template_id" value="<?= $project_template_id ?>">

    <div class="modal-body">
        <div class="mb-3">
            <label class="form-label">Name <strong class="text-danger">*</strong></label>
            <input type="text" class="form-control" name="name" maxlength="200" value="<?= escapeHtml($row['project_template_name']) ?>" required autofocus>
        </div>

        <div class="mb-0">
            <label class="form-label">Description</label>
            <textarea class="form-control" name="description" rows="3" placeholder="When to use it"><?= escapeHtml($row['project_template_description']) ?></textarea>
        </div>
    </div>

    <div class="modal-footer">
        <button type="submit" name="edit_project_template" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>

</form>

<?php
require_once '../../../includes/modal_footer.php';
