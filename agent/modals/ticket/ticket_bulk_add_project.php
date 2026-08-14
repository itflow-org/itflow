<?php

require_once '../../../includes/modal_header.php';

$ticket_ids = array_map('intval', $_GET['ticket_ids'] ?? []);

$count = count($ticket_ids);

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-user-check me-2"></i>Assign Project to <strong><?= $count ?></strong> Tickets</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <?php foreach ($ticket_ids as $ticket_id) { ?><input type="hidden" name="ticket_ids[]" value="<?= $ticket_id ?>"><?php } ?>

    <div class="modal-body">

        <div class="mb-3">
            <label>Project</label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-project-diagram"></i></span>
                <select class="form-select select2" name="project_id">
                    <option value="0">No Project</option>
                    <?php
                    $sql_projects_select = mysqli_query($mysqli, "SELECT project_id, project_name, project_prefix, project_number FROM projects
                        WHERE project_archived_at IS NULL
                        AND project_completed_at IS NULL
                        ORDER BY project_name DESC"
                    );
                    while ($row = mysqli_fetch_assoc($sql_projects_select)) {
                        $project_id_select = intval($row['project_id']);
                        $project_prefix_select = escapeHtml($row['project_prefix']);
                        $project_number_select = intval($row['project_number']);
                        $project_name_select = escapeHtml($row['project_name']);

                        ?>
                        <option value="<?= $project_id_select ?>"><?= " $project_prefix_select$project_number_select - $project_name_select" ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>
    </div>

    <div class="modal-footer">
        <button type="submit" name="bulk_add_ticket_project" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Assign</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
