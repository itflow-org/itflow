<?php
require_once '../../includes/modal_header.php';
ob_start();
?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-stopwatch mr-2"></i>New SLA</h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="modal-body">
        <div class="form-group">
            <label>Name <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-stopwatch"></i></span>
                </div>
                <input type="text" class="form-control" name="name" placeholder="SLA name" maxlength="200" required autofocus>
            </div>
        </div>

        <div class="form-group">
            <label>Description</label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-comment"></i></span>
                </div>
                <input type="text" class="form-control" name="description" placeholder="Short description" maxlength="500">
            </div>
        </div>

        <div class="form-group">
            <label>Response target (business minutes) <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-reply"></i></span>
                </div>
                <input type="number" class="form-control" name="response_minutes" min="1" placeholder="e.g. 60" required>
            </div>
        </div>

        <div class="form-group">
            <label>Resolution target (business minutes)</label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-flag-checkered"></i></span>
                </div>
                <input type="number" class="form-control" name="resolution_minutes" min="1" placeholder="Leave empty for response-only">
            </div>
        </div>
    </div>

    <div class="modal-footer">
        <button type="submit" name="add_sla" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Create</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fas fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
