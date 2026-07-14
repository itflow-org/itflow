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
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="modal-body">

        <ul class="nav nav-pills nav-justified mb-3">
            <li class="nav-item">
                <a class="nav-link active" data-toggle="pill" href="#pills-role-details">Details</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="pill" href="#pills-role-permissions">Permissions</a>
            </li>
        </ul>

        <hr>

        <div class="tab-content">

            <!-- DETAILS TAB -->
            <div class="tab-pane fade show active" id="pills-role-details">

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

                    <div class="custom-control custom-radio mb-2">
                        <input type="radio" class="custom-control-input" id="admin_no" name="role_is_admin" value="0" checked required>
                        <label class="custom-control-label" for="admin_no">
                            No - use permissions on the next tab
                        </label>
                    </div>

                    <div class="custom-control custom-radio">
                        <input type="radio" class="custom-control-input" id="admin_yes" name="role_is_admin" value="1" required>
                        <label class="custom-control-label" for="admin_yes">
                            Yes - this role should have full admin access
                        </label>
                    </div>

                </div>

            </div>

            <!-- PERMISSIONS TAB -->
            <div class="tab-pane fade" id="pills-role-permissions">

                <?php
                // Enumerate modules
                $sql_modules = mysqli_query($mysqli, "SELECT * FROM modules");
                while ($row_modules = mysqli_fetch_assoc($sql_modules)) {

                    $module_id = intval($row_modules['module_id']);

                    // raw for name, escaped for display
                    $module_name_raw = $row_modules['module_name'];
                    $module_name_display = ucfirst(str_replace("module_", "", $module_name_raw));

                    $module_name_display_safe = escapeHtml($module_name_display);
                    $module_description = escapeHtml($row_modules['module_description']);

                    // default for new role
                    $module_permission = 0;

                    $field_name = $module_id . "##" . $module_name_raw;
                    $group_id = "perm_group_$module_id";
                    ?>

                    <div class="form-group">
                        <label><?= $module_name_display_safe ?> <strong class="text-danger">*</strong></label>

                        <div class="btn-group btn-group-toggle btn-block" data-toggle="buttons" role="group"
                             aria-label="Permissions for <?= $module_name_display_safe ?>">

                            <label class="btn btn-outline-secondary btn-sm active" title="No Access">
                                <input
                                    type="radio"
                                    name="<?= $field_name ?>"
                                    id="<?= $group_id ?>_0"
                                    value="0"
                                    autocomplete="off"
                                    checked
                                    required
                                >
                                None
                            </label>

                            <label class="btn btn-outline-primary btn-sm" title="Viewing Only">
                                <input
                                    type="radio"
                                    name="<?= $field_name ?>"
                                    id="<?= $group_id ?>_1"
                                    value="1"
                                    autocomplete="off"
                                >
                                <i class="fas fa-fw fa-eye mr-1"></i>Read
                            </label>

                            <label class="btn btn-outline-warning btn-sm" title="Read, Edit, Archive">
                                <input
                                    type="radio"
                                    name="<?= $field_name ?>"
                                    id="<?= $group_id ?>_2"
                                    value="2"
                                    autocomplete="off"
                                >
                                <i class="fas fa-fw fa-edit mr-1"></i>Modify
                            </label>

                            <label class="btn btn-outline-danger btn-sm" title="Read, Edit, Archive, Delete">
                                <input
                                    type="radio"
                                    name="<?= $field_name ?>"
                                    id="<?= $group_id ?>_3"
                                    value="3"
                                    autocomplete="off"
                                >
                                <i class="fas fa-fw fa-trash mr-1"></i>Full
                            </label>

                        </div>

                        <small class="form-text text-muted mt-2"><?= $module_description ?></small>
                    </div>

                <?php } // end while ?>
            </div>

        </div>

    </div>

    <div class="modal-footer">
        <button type="submit" name="add_role" class="btn btn-primary text-bold">
            <i class="fas fa-check mr-2"></i>Create
        </button>
        <button type="button" class="btn btn-light" data-dismiss="modal">
            <i class="fas fa-times mr-2"></i>Cancel
        </button>
    </div>
</form>

<script>
    // Optional: when Admin Yes is selected, disable permission radios + switch to Details tab
    (function () {
        function setPermissionsEnabled(enabled) {
            const permsTab = document.getElementById('pills-role-permissions');
            if (!permsTab) return;

            permsTab.querySelectorAll('input[type="radio"]').forEach(function (el) {
                el.disabled = !enabled;
            });

            // also visually dim the tab content
            permsTab.style.opacity = enabled ? '1' : '0.5';
        }

        const adminYes = document.getElementById('admin_yes');
        const adminNo  = document.getElementById('admin_no');

        function refresh() {
            const isAdmin = adminYes && adminYes.checked;
            setPermissionsEnabled(!isAdmin);

            if (isAdmin) {
                // move user back to Details tab (avoids confusion)
                const detailsTab = document.querySelector('a[href="#pills-role-details"]');
                if (detailsTab) detailsTab.click();
            }
        }

        if (adminYes && adminNo) {
            adminYes.addEventListener('change', refresh);
            adminNo.addEventListener('change', refresh);
            refresh();
        }
    })();
</script>

<?php
require_once '../../../includes/modal_footer.php';
