<?php

require_once '../../includes/modal_header.php';

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-user-shield me-2"></i>New Role</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="modal-body">

        <ul class="nav nav-pills nav-justified mb-3">
            <li class="nav-item">
                <a class="nav-link active" data-bs-toggle="pill" href="#pills-role-details">Details</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="pill" href="#pills-role-permissions">Permissions</a>
            </li>
        </ul>

        <hr>

        <div class="tab-content">

            <!-- DETAILS TAB -->
            <div class="tab-pane fade show active" id="pills-role-details">

                <div class="mb-3">
                    <label>Name <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-user-shield"></i></span>
                        <input type="text" class="form-control" name="role_name" placeholder="Role Name" maxlength="200" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label>Description <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-chevron-right"></i></span>
                        <input type="text" class="form-control" name="role_description" placeholder="Role Description" maxlength="200" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label>Admin Access <strong class="text-danger">*</strong></label>

                    <div class="form-check mb-2">
                        <input type="radio" class="form-check-input" id="admin_no" name="role_is_admin" value="0" checked required>
                        <label class="form-check-label" for="admin_no">
                            No - use permissions on the next tab
                        </label>
                    </div>

                    <div class="form-check">
                        <input type="radio" class="form-check-input" id="admin_yes" name="role_is_admin" value="1" required>
                        <label class="form-check-label" for="admin_yes">
                            Yes - this role should have full admin access
                        </label>
                    </div>

                </div>

            </div>

            <!-- PERMISSIONS TAB -->
            <div class="tab-pane fade" id="pills-role-permissions">

                <?php
                // Enumerate modules
                $sql_modules = mysqli_query($mysqli, "SELECT module_description, module_id, module_name FROM modules");
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

                    <div class="mb-3">
                        <label><?= $module_name_display_safe ?> <strong class="text-danger">*</strong></label>

                        <div class="btn-group w-100" role="group"
                             aria-label="Permissions for <?= $module_name_display_safe ?>">

                            <input class="btn-check"
                                    type="radio"
                                    name="<?= $field_name ?>"
                                    id="<?= $group_id ?>_0"
                                    value="0"
                                    autocomplete="off"
                                    checked
                                    required
                                >
                            <label class="btn btn-outline-secondary btn-sm" title="No Access" for="<?= $group_id ?>_0">None</label>

                            <input class="btn-check"
                                    type="radio"
                                    name="<?= $field_name ?>"
                                    id="<?= $group_id ?>_1"
                                    value="1"
                                    autocomplete="off"
                                >
                            <label class="btn btn-outline-primary btn-sm" title="Viewing Only" for="<?= $group_id ?>_1"><i class="fas fa-fw fa-eye me-1"></i>Read</label>

                            <input class="btn-check"
                                    type="radio"
                                    name="<?= $field_name ?>"
                                    id="<?= $group_id ?>_2"
                                    value="2"
                                    autocomplete="off"
                                >
                            <label class="btn btn-outline-warning btn-sm" title="Read, Edit, Archive" for="<?= $group_id ?>_2"><i class="fas fa-fw fa-edit me-1"></i>Modify</label>

                            <input class="btn-check"
                                    type="radio"
                                    name="<?= $field_name ?>"
                                    id="<?= $group_id ?>_3"
                                    value="3"
                                    autocomplete="off"
                                >
                            <label class="btn btn-outline-danger btn-sm" title="Read, Edit, Archive, Delete" for="<?= $group_id ?>_3"><i class="fas fa-fw fa-trash me-1"></i>Full</label>

                        </div>

                        <small class="form-text text-muted mt-2"><?= $module_description ?></small>
                    </div>

                <?php } // end while ?>
            </div>

        </div>

    </div>

    <div class="modal-footer">
        <button type="submit" name="add_role" class="btn btn-primary text-bold">
            <i class="fas fa-check me-2"></i>Create
        </button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">
            <i class="fas fa-times me-2"></i>Cancel
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
