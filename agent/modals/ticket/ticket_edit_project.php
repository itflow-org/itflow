<?php

require_once '../../../includes/modal_header.php';

$ticket_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT * FROM tickets LEFT JOIN clients ON client_id = ticket_client_id WHERE ticket_id = $ticket_id LIMIT 1");

$row = mysqli_fetch_array($sql);
$client_id = intval($row['ticket_client_id']);
$client_name = nullable_htmlentities($row['client_name']);
$ticket_prefix = nullable_htmlentities($row['ticket_prefix']);
$ticket_number = intval($row['ticket_number']);
$ticket_project_id = intval($row['ticket_project_id']);


// Select box arrays
$sql_projects = mysqli_query($mysqli, "SELECT project_id, project_name FROM projects WHERE (project_client_id = $client_id OR project_client_id = 0) AND project_completed_at IS NULL AND project_archived_at IS NULL ORDER BY project_name ASC");

// Generate the HTML form content using output buffering.
ob_start();
?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-project-diagram mr-2"></i>Project: <strong><?= "$ticket_prefix$ticket_number" ?></strong> - <?= $client_name ?></h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="ticket_id" value="<?php echo $ticket_id; ?>">
    <div class="modal-body">

        <div class="form-group">
            <label>Project</label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-project-diagram"></i></span>
                </div>
                <select class="form-control select2" name="project">
                    <option value="0">- None -</option>
                    <?php
                    while ($row = mysqli_fetch_array($sql_projects)) {
                        $project_id = intval($row['project_id']);
                        $project_name = nullable_htmlentities($row['project_name']); ?>
                        <option <?php if ($ticket_project_id == $project_id) { echo "selected"; } ?> 
                            value="<?= $project_id ?>"><?= $project_name ?>
                        </option>

                    <?php } ?>
                </select>
            </div>
        </div>

    </div>

    <div class="modal-footer">
        <button type="submit" name="edit_ticket_project" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>

</form>

<?php
require_once '../../../includes/modal_footer.php';
