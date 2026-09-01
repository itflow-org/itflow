<?php
require_once "includes/inc_all_admin.php";
 ?>

    <div class="card card-dark">
        <div class="card-header py-3">
            <h3 class="card-title"><i class="fas fa-fw fa-project-diagram me-2"></i>Project Settings</h3>
        </div>
        <div class="card-body">
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                <h4>Project</h4>

                <div class="mb-3">
                    <label>Project Prefix</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-barcode"></i></span>
                        <input type="text" class="form-control" name="config_project_prefix" placeholder="Project Prefix" maxlength="200" value="<?= escapeHtml($config_project_prefix) ?>" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label>Next Number</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-barcode"></i></span>
                        <input type="number" min="0" class="form-control" name="config_project_next_number" placeholder="Next Project Number" value="<?= intval($config_project_next_number) ?>" required>
                    </div>
                </div>

                <hr>

                <button type="submit" name="edit_project_settings" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Save</button>

            </form>
        </div>
    </div>

<?php
require_once "../includes/footer.php";
