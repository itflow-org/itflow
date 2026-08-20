<?php

require_once '../../../includes/modal_header.php';

$client_id = intval($_GET['client_id'] ?? 0);

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-project-diagram me-2"></i>New Project</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="modal-body">

        <?php if ($client_id) { ?>
            <input type="hidden" name="client_id" value="<?= $client_id ?>">
        <?php } else { ?>
            <div class="mb-3">
                <label>Client</label>
                <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-users"></i></span>
                    <select class="form-select select2" name="client_id">
                        <option value="0">- No Client -</option>
                        <?php
                        $sql = mysqli_query($mysqli, "SELECT client_id, client_name FROM clients WHERE client_archived_at IS NULL " . clientScopeSql('clients.client_id') . " ORDER BY client_name ASC");
                        while ($row = mysqli_fetch_assoc($sql)) {
                            $client_id_select = intval($row['client_id']);
                            $client_name = escapeHtml($row['client_name']);
                        ?>
                        <option value="<?= $client_id_select ?>"><?= $client_name ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>
        <?php } ?>

        <div class="mb-3">
            <label>Project Name <strong class="text-danger">*</strong></label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-project-diagram"></i></span>
                <input type="text" class="form-control" name="name" placeholder="Project Name" maxlength="255" required autofocus>
            </div>
        </div>

        <div class="mb-3">
            <label>Template</label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-cube"></i></span>
                <select class="form-select select2" name="project_template_id">
                    <option value="">- Template -</option>
                    <?php
                    $sql = mysqli_query($mysqli, "SELECT project_template_id, project_template_name FROM project_templates WHERE project_template_archived_at IS NULL ORDER BY project_template_name ASC");
                    while ($row = mysqli_fetch_assoc($sql)) {
                        $project_template_id = intval($row['project_template_id']);
                        $project_template_name = escapeHtml($row['project_template_name']);
                    ?>
                    <option value="<?= $project_template_id ?>"><?= $project_template_name ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>

        <div class="mb-3">
            <label>Description</label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-align-left"></i></span>
                <input type="text" class="form-control" name="description" placeholder="Description">
            </div>
        </div>


        <div class="mb-3">
            <label>Date Due <strong class="text-danger">*</strong></label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                <input type="date" class="form-control" name="due_date" required>
            </div>
        </div>

        <div class="mb-3">
            <label>Project Manager</label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-user-tie"></i></span>
                <select class="form-select select2" name="project_manager">
                    <option value="0">No Manager</option>
                    <?php

                    $sql = mysqli_query(
                        $mysqli,
                        "SELECT user_id, user_name FROM users
                        WHERE user_role_id > 1 AND user_status = 1 AND user_archived_at IS NULL ORDER BY user_name ASC"
                    );
                    while ($row = mysqli_fetch_assoc($sql)) {
                        $user_id = intval($row['user_id']);
                        $user_name = escapeHtml($row['user_name']); ?>
                        <option value="<?= $user_id ?>"><?= $user_name ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="add_project" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Create</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
