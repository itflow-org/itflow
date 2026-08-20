<?php
require_once '../../includes/modal_header.php';
ob_start();
?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-stopwatch me-2"></i>New SLA</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="modal-body">
        <div class="mb-3">
            <label>Name <strong class="text-danger">*</strong></label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-stopwatch"></i></span>
                <input type="text" class="form-control" name="name" placeholder="SLA name" maxlength="200" required autofocus>
            </div>
        </div>

        <div class="mb-3">
            <label>Description</label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-comment"></i></span>
                <input type="text" class="form-control" name="description" placeholder="Short description" maxlength="500">
            </div>
        </div>

        <div class="mb-3">
            <label>Response target (business minutes) <strong class="text-danger">*</strong></label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-reply"></i></span>
                <input type="number" class="form-control" name="response_minutes" min="1" placeholder="e.g. 60" required>
            </div>
        </div>

        <div class="mb-3">
            <label>Resolution target (business minutes)</label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-flag-checkered"></i></span>
                <input type="number" class="form-control" name="resolution_minutes" min="1" placeholder="Leave empty for response-only">
            </div>
        </div>
    </div>

    <div class="modal-footer">
        <button type="submit" name="add_sla" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Create</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
