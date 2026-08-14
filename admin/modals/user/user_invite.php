<?php

require_once '../../includes/modal_header.php';

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-user-plus"></i>Invite User</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <div class="modal-body">

        <div class="mb-3">
            <label>Email <strong class="text-danger">*</strong></label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-envelope"></i></span>
                <input type="email" class="form-control" name="email" placeholder="Email Address" maxlength="200" required>
            </div>
        </div>

        <div class="mb-3">
            <label>Role <strong class="text-danger">*</strong></label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-user-shield"></i></span>
                <select class="form-control select2" name="role" required>
                    <option value="">- Role -</option>
                    <!-- //TODO: Pull from roles -->
                </select>
            </div>
        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="invite_user" class="btn btn-primary text-bold"><i class="fas fa-paper-plane me-2"></i>Send Invite</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once "../../../includes/modal_footer.php";
