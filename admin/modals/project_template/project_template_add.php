<?php

require_once '../../includes/modal_header.php';

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-project-diagram me-2"></i>Creating Project Template</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="modal-body">
        <div class="mb-3">
            <label>Project Template Name <strong class="text-danger">*</strong></label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-project-diagram"></i></span>
                <input type="text" class="form-control" name="name" placeholder="Project Template Name" maxlength="255" required autofocus>
            </div>
        </div>

        <div class="mb-3">
            <label>Description</label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-angle-right"></i></span>
                <input type="text" class="form-control" name="description" placeholder="Description">
            </div>
        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="add_project_template" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Create</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
