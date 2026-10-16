<?php

require_once '../../includes/modal_header.php';

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-cubes me-2"></i>New Project Template</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="modal-body">
        <div class="mb-3">
            <label class="form-label">Name <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw fa-cubes"></i></span>
                <input type="text" class="form-control" name="name" placeholder="Client onboarding, server refresh, office move..." maxlength="200" required autofocus>
            </div>
        </div>

        <div class="mb-0">
            <label class="form-label">Description</label>
            <textarea class="form-control" name="description" rows="3" placeholder="When to use it"></textarea>
            <div class="form-text">Next, add its ticket templates - one per step of the work.</div>
        </div>
    </div>
    <div class="modal-footer">
        <button type="submit" name="add_project_template" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Create</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
