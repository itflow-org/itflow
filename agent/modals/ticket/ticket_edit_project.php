<?php

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support', 2);

// The ticket, after the module and client checks - or the modal's error
$row = ticketForModal($_GET['id'] ?? 0, 2, true);
$ticket_id = intval($row['ticket_id']);

$client_name = escapeHtml($row['client_name']);
$ticket_prefix = escapeHtml($row['ticket_prefix']);
$ticket_number = intval($row['ticket_number']);
$ticket_project_id = intval($row['ticket_project_id']);
$client_id = intval($row['ticket_client_id']);

// Select box arrays
// The one it is on now stays on offer even once completed, so saving doesn't drop it
$sql_projects = mysqli_query($mysqli, "SELECT project_id, project_name, project_completed_at, project_archived_at FROM projects WHERE project_client_id IN (0, $client_id) AND ((project_completed_at IS NULL AND project_archived_at IS NULL) OR project_id = $ticket_project_id) ORDER BY project_client_id DESC, project_name ASC");

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-project-diagram me-2"></i>Project <small class="ms-1 opacity-75"><?= "$ticket_prefix$ticket_number" ?></small></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="ticket_id" value="<?= $ticket_id ?>">
    <div class="modal-body">

        <div class="mb-3">
            <label>Project</label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-project-diagram"></i></span>
                <select class="form-select select2" name="project">
                    <option value="0">No project</option>
                    <?php
                    while ($row = mysqli_fetch_assoc($sql_projects)) {
                        $project_id = intval($row['project_id']);
                        $project_name = escapeHtml($row['project_name']) . ($row['project_archived_at'] ? ' (archived)' : ($row['project_completed_at'] ? ' (completed)' : '')); ?>
                        <option <?php if ($ticket_project_id == $project_id) { echo "selected"; } ?>
                            value="<?= $project_id ?>"><?= $project_name ?>
                        </option>

                    <?php } ?>
                </select>
            </div>
        </div>

    </div>

    <div class="modal-footer">
        <button type="submit" name="edit_ticket_project" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>

</form>

<?php
require_once '../../../includes/modal_footer.php';
