<?php

require_once '../../../includes/modal_header.php';

$role_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT * FROM user_roles WHERE role_id = $role_id LIMIT 1");

$row = mysqli_fetch_assoc($sql);
$role_name = escapeHtml($row['role_name']);
$role_description = escapeHtml($row['role_description']);
$role_admin = intval($row['role_is_admin']);

// Count number of users that have each role
$sql_role_user_count = mysqli_query($mysqli, "SELECT COUNT(user_id) FROM users WHERE user_role_id = $role_id AND user_archived_at IS NULL");
$role_user_count = mysqli_fetch_row($sql_role_user_count)[0];

$sql_users = mysqli_query($mysqli, "SELECT * FROM users WHERE user_role_id = $role_id AND user_archived_at IS NULL");
// Initialize an empty array to hold user names
$user_names = [];

// Fetch each row and store the user_name in the array
while($row = mysqli_fetch_assoc($sql_users)) {
    $user_names[] = escapeHtml($row['user_name']);
}

// Convert the array of user names to a comma-separated string
$user_names_string = implode(",", $user_names) ;

if (empty($user_names_string)) {
    $user_names_string = "-";
}

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-user-shield mr-2"></i>Editing role:
        <strong><?= $role_name ?></strong></h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="role_id" value="<?= $role_id ?>">
    <div class="modal-body">
        <ul class="nav nav-pills nav-justified mb-3">
            <li class="nav-item">
                <a class="nav-link active" data-toggle="pill" href="#pills-role-details">Details</a>
            </li>
            <?php if (!$role_admin) { ?>
            <li class="nav-item">
                <a class="nav-link" data-toggle="pill" href="#pills-role-permissions">Permissions</a>
            </li>
            <?php } ?>
        </ul>

        <hr>

        <div class="tab-content">

            <div class="tab-pane fade show active" id="pills-role-details">

                <div class="form-group">
                    <label>Name <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-user-shield"></i></span>
                        </div>
                        <input type="text" class="form-control" name="role_name" placeholder="Role Name" maxlength="200" value="<?= $role_name ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Description <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-chevron-right"></i></span>
                        </div>
                        <input type="text" class="form-control" name="role_description" placeholder="Role Description" maxlength="200" value="<?= $role_description ?>" required>
                    </div>
                </div>


                <div class="form-group">
                    <label>Admin Access <strong class="text-danger">*</strong></label>
                    <div class="custom-control custom-radio mb-2">
                        <input type="radio" class="custom-control-input" id="admin_yes" name="role_is_admin" value="1"
                            <?php if ($role_admin) { echo 'checked'; } ?> required>
                        <label class="custom-control-label" for="admin_yes">
                            Yes - this role should have full admin access
                        </label>
                    </div>

                    <div class="custom-control custom-radio">
                        <input type="radio" class="custom-control-input" id="admin_no" name="role_is_admin" value="0"
                            <?php if (!$role_admin) { echo 'checked'; } ?> required>
                        <label class="custom-control-label" for="admin_no">
                            No - use permissions on the next tab
                        </label>
                    </div>
                </div>

            </div>
            <?php if (!$role_admin) { ?>
            <div class="tab-pane fade" id="pills-role-permissions">

                <?php if ($role_admin) { ?>
                    <div class="alert alert-warning"><strong>Module permissions do not apply to Admins.</strong></div>
                <?php } ?>

                <?php

                // Enumerate modules
                $sql_modules = mysqli_query($mysqli, "SELECT * FROM modules");
                while ($row_modules = mysqli_fetch_assoc($sql_modules)) {
                    $module_id = intval($row_modules['module_id']);
                    $module_name = escapeHtml($row_modules['module_name']);
                    $module_name_display = ucfirst(str_replace("module_","",$module_name));
                    $module_description = escapeHtml($row_modules['module_description']);

                    // Get permission level for module
                    $module_permission_row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT user_role_permission_level FROM user_role_permissions WHERE module_id = $module_id AND user_role_id = $role_id LIMIT 1"));
                    $module_permission = 0;
                    if ($module_permission_row) {
                        $module_permission = $module_permission_row['user_role_permission_level'];
                    }
                    ?>

                    <div class="form-group">
                        <label> <?= $module_name_display ?> <strong class="text-danger">*</strong></label>
                        <?php
                        $field_name = "$module_id##$module_name";
                        $group_id = "perm_group_$module_id";
                        ?>

                        <div class="btn-group btn-group-toggle btn-block" data-toggle="buttons" role="group" aria-label="Permissions for <?= $module_name_display ?>">

                            <label class="btn btn-outline-secondary btn-sm <?php if ($module_permission == 0) { echo 'active'; } ?>" title="No Access">
                                <input
                                    type="radio"
                                    name="<?= $field_name ?>"
                                    id="<?= $group_id ?>_0"
                                    value="0"
                                    autocomplete="off"
                                    <?php if ($module_permission == 0) { echo 'checked'; } ?>
                                    required
                                >
                                None
                            </label>

                            <label class="btn btn-outline-primary btn-sm <?php if ($module_permission == 1) { echo 'active'; } ?>" title="Viewing Only">
                                <input
                                    type="radio"
                                    name="<?= $field_name ?>"
                                    id="<?= $group_id ?>_1"
                                    value="1"
                                    autocomplete="off"
                                    <?php if ($module_permission == 1) { echo 'checked'; } ?>
                                >
                                <i class="fas fa-fw fa-eye mr-1"></i>Read
                            </label>

                            <label class="btn btn-outline-warning btn-sm <?php if ($module_permission == 2) { echo 'active'; } ?>" title="Read, Edit, Archive">
                                <input
                                    type="radio"
                                    name="<?= $field_name ?>"
                                    id="<?= $group_id ?>_2"
                                    value="2"
                                    autocomplete="off"
                                    <?php if ($module_permission == 2) { echo 'checked'; } ?>
                                >
                                <i class="fas fa-fw fa-edit mr-1"></i>Modify
                            </label>

                            <label class="btn btn-outline-danger btn-sm <?php if ($module_permission == 3) { echo 'active'; } ?>" title="Read, Edit, Archive, Delete">
                                <input
                                    type="radio"
                                    name="<?= $field_name ?>"
                                    id="<?= $group_id ?>_3"
                                    value="3"
                                    autocomplete="off"
                                    <?php if ($module_permission == 3) { echo 'checked'; } ?>
                                >
                                <i class="fas fa-fw fa-trash mr-1"></i>Full
                            </label>

                        </div>

                        <small class="form-text text-muted mt-2"><?= $module_description ?></small>

                    </div>

                <?php } // End while ?>

            </div>
            <?php } ?>

        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="edit_role" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fas fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
