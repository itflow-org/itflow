<?php

require_once '../../../includes/modal_header.php';

$client_ids = array_map('intval', $_GET['client_ids'] ?? []);

$count = count($client_ids);

// Generate the HTML form content using output buffering.
ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-life-ring me-2"></i>New Tickets for <strong><?= $count ?></strong> Client(s)</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <?php foreach ($client_ids as $client_id) { ?><input type="hidden" name="client_ids[]" value="<?= $client_id ?>"><?php } ?>

    <div class="modal-body">

        <div class="mb-3">
            <label>Subject <strong class="text-danger">*</strong></label>
            <input type="text" class="form-control" name="bulk_subject" placeholder="Enter a subject" maxlength="200" required>
        </div>

        <div class="mb-3">
            <textarea class="form-control tinymceTicket" id="textInput" name="bulk_details"></textarea>
        </div>

        <div class="row">

            <div class="col">
                <div class="mb-3">
                    <label>Priority <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-thermometer-half"></i></span>
                        <select class="form-select select2" name="bulk_priority" required>
                            <option>Low</option>
                            <option selected>Medium</option>
                            <option>High</option>
                            <option>Urgent</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="col">
                <div class="mb-3">
                    <label>Category</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-layer-group"></i></span>
                        <select class="form-select select2" name="bulk_category">
                            <option value="0">- Not Categorized -</option>
                            <?php
                            $sql_categories = mysqli_query($mysqli, "SELECT category_id, category_name FROM categories WHERE category_type = 'Ticket' AND category_archived_at IS NULL");
                            while ($row = mysqli_fetch_assoc($sql_categories)) {
                                $category_id = intval($row['category_id']);
                                $category_name = escapeHtml($row['category_name']);

                                ?>
                                <option value="<?= $category_id ?>"><?= $category_name ?></option>
                            <?php } ?>

                        </select>
                    </div>
                </div>
            </div>

        </div>

        <div class="row">
            <div class="col">

                <div class="mb-3">
                    <label>Assign to</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-user-check"></i></span>
                        <select class="form-select select2" name="bulk_assigned_to">
                            <option value="0">Not Assigned</option>
                            <?php

                            $sql = mysqli_query(
                                $mysqli,
                                "SELECT user_id, user_name FROM users
                                WHERE user_type = 1 AND user_status = 1 AND user_archived_at IS NULL ORDER BY user_name ASC"
                            );
                            while ($row = mysqli_fetch_assoc($sql)) {
                                $user_id = intval($row['user_id']);
                                $user_name = escapeHtml($row['user_name']); ?>
                                <option <?php if ($session_user_id == $user_id) { echo "selected"; } ?> value="<?= $user_id ?>"><?= $user_name ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="col">

                <div class="mb-3">
                    <label>Project</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-project-diagram"></i></span>
                        <select class="form-select select2" name="bulk_project">
                            <option value="0">- None -</option>
                            <?php

                            $sql_projects = mysqli_query($mysqli, "SELECT project_id, project_name FROM projects WHERE project_completed_at IS NULL AND project_archived_at IS NULL ORDER BY project_name ASC");
                            while ($row = mysqli_fetch_assoc($sql_projects)) {
                                $project_id_select = intval($row['project_id']);
                                $project_name_select = escapeHtml($row['project_name']); ?>
                                <option value="<?= $project_id_select ?>"><?= $project_name_select ?></option>

                            <?php } ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($config_module_enable_accounting) { ?>
        <div class="mb-3">
            <div class="form-check form-switch">
                <input type="checkbox" class="form-check-input" name="bulk_billable" <?php if ($config_ticket_default_billable == 1) { echo "checked"; } ?> value="1" id="billableSwitch">
                <label class="form-check-label" for="billableSwitch">Billable</label>
            </div>
        </div>
        <?php } ?>

    </div>

    <div class="modal-footer">
        <button type="submit" name="bulk_add_client_ticket" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Create</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
