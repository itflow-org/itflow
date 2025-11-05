<?php

require_once '../../../includes/modal_header.php';

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-user-shield mr-2"></i>New Role</h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">
    <div class="modal-body">
        <div class="tab-content">

            <div class="form-group">
                <label>Name <strong class="text-danger">*</strong></label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fa fa-fw fa-user-shield"></i></span>
                    </div>
                    <input type="text" class="form-control" name="role_name" placeholder="Role Name" maxlength="200" required>
                </div>
            </div>

            <div class="form-group">
                <label>Description <strong class="text-danger">*</strong></label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fa fa-fw fa-chevron-right"></i></span>
                    </div>
                    <input type="text" class="form-control" name="role_description" placeholder="Role Description" maxlength="200" required>
                </div>
            </div>

            <div class="form-group">
                <label>Admin Access <strong class="text-danger">*</strong></label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fa fa-fw fa-tools"></i></span>
                    </div>
                    <select class="form-control select2" name="role_is_admin" required>
                        <option value="0">No - edit after creation to set permissions</option>
                        <option value="1">Yes - this role should have full admin access</option>
                    </select>
                </div>
            </div>

        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="add_role" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fas fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
