<div class="modal" id="editRoleModal<?php echo $role_id; ?>" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-fw fa-user-shield mr-2"></i>Editing role:
                    <strong><?php echo $role_name; ?></strong></h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="role_id" value="<?php echo $role_id; ?>">
                <div class="modal-body bg-white">

                    <ul class="nav nav-pills nav-justified mb-3">
                        <li class="nav-item">
                            <a class="nav-link active" data-toggle="pill" href="#pills-role-details<?php echo $role_id; ?>">Details</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="pill" href="#pills-role-access<?php echo $role_id; ?>">Access</a>
                        </li>
                    </ul>

                    <hr>

                    <div class="tab-content">

                        <div class="tab-pane fade show active" id="pills-role-details<?php echo $role_id; ?>">

                            <div class="form-group">
                                <label>Name <strong class="text-danger">*</strong></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-user-shield"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="role_name" placeholder="Role Name" maxlength="200" value="<?php echo $role_name; ?>" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Description <strong class="text-danger">*</strong></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-chevron-right"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="role_description" placeholder="Role Description" maxlength="200" value="<?php echo $role_description; ?>" required>
                                </div>
                            </div>


                            <div class="form-group">
                                <label>Admin Access <strong class="text-danger">*</strong></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-tools"></i></span>
                                    </div>
                                    <select class="form-control select2" name="role_is_admin" required>
                                        <option value="1" <?php if ($role_admin) { echo 'selected'; } ?> >Yes - this role should have full admin access</option>
                                        <option value="0" <?php if (!$role_admin) { echo 'selected'; } ?>>No - use permissions on the next tab</option>
                                    </select>
                                </div>
                            </div>

                        </div>

                        <div class="tab-pane fade" id="pills-role-access<?php echo $role_id; ?>">

                            <?php if ($role_admin) { ?>
                                <div class="alert alert-warning"><strong>Module permissions do not apply to Admins.</strong></div>
                            <?php } ?>

                            <?php

                            // Enumerate modules
                            $sql_modules = mysqli_query($mysqli, "SELECT * FROM modules");
                            while ($row_modules = mysqli_fetch_array($sql_modules)) {
                                $module_id = intval($row_modules['module_id']);
                                $module_name = nullable_htmlentities($row_modules['module_name']);
                                $module_name_display = ucfirst(str_replace("module_","",$module_name));
                                $module_description = nullable_htmlentities($row_modules['module_description']);

                                // Get permission level for module
                                $module_permission_row = mysqli_fetch_array(mysqli_query($mysqli, "SELECT user_role_permission_level FROM user_role_permissions WHERE module_id = $module_id AND user_role_id = $role_id LIMIT 1"));
                                $module_permission = 0;
                                if ($module_permission_row) {
                                    $module_permission = $module_permission_row['user_role_permission_level'];
                                }
                                ?>

                                <div class="form-group">
                                    <label> <?php echo $module_name_display ?> <strong class="text-danger">*</strong></label>
                                    <div class="input-group">
                                        <select class="form-control select2" name="<?php echo "$module_id##$module_name" ?>" required>
                                            <option value="0" <?php if ($module_permission == 0) { echo 'selected'; } ?> >None</option>
                                            <option value="1" <?php if ($module_permission == 1) { echo 'selected'; } ?> >Read</option>
                                            <option value="2" <?php if ($module_permission == 2) { echo 'selected'; } ?>>Modify (Read, Edit, Archive)</option>
                                            <option value="3" <?php if ($module_permission == 3) { echo 'selected'; } ?>>Full (Read, Edit, Archive, Delete)</option>
                                        </select>
                                    </div>
                                    <small class="form-text text-muted"><?php echo $module_description ?></small>

                                </div>

                            <?php } // End while ?>

                        </div>

                    </div>

                </div>
                <div class="modal-footer bg-white">
                    <button type="submit" name="edit_role" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Save</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fas fa-times mr-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
