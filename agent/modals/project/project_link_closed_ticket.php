<?php

require_once '../../../includes/modal_header.php';

$project_id = intval($_GET['project_id']);
$client_id = intval($_GET['client_id'] ?? 0);

if ($client_id) {
    $client_ticket_select_query = "AND ticket_client_id = $client_id"; // Used when linking a ticket to the project
} else {
    $client_ticket_select_query = '';
}

$sql = mysqli_query($mysqli, "SELECT * FROM projects WHERE project_id = $project_id LIMIT 1");

$row = mysqli_fetch_array($sql);
$project_name = nullable_htmlentities($row['project_name']);

ob_start();
?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-life-ring mr-2"></i>Link closed ticket to project: <strong><?php echo $project_name; ?></strong></h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="project_id" value="<?php echo $project_id; ?>">
    <div class="modal-body">

        <div class="form-group">
            <label>Ticket number <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <?php
                    // Show the ticket prefix, or just the tag icon
                    $config_row = mysqli_fetch_array(mysqli_query($mysqli, "SELECT config_ticket_prefix FROM settings WHERE company_id = 1"));
                    $config_ticket_prefix = $config_row['config_ticket_prefix'];
                    if (empty($config_ticket_prefix)) {
                        echo "<span class=\"input-group-text\"><i class=\"fa fa-fw fa-tag\"></i></span>";
                    } else {
                        echo "<div class=\"input-group-text\"> $config_ticket_prefix </div>";
                    }
                    ?>
                </div>
                <input type="text" class="form-control" name="ticket_number" inputmode="numeric" pattern="[0-9]*\.?[0-9]{0,2}" placeholder="Closed ticket number to link with project" required>
            </div>
        </div>

    </div>

    <div class="modal-footer">
        <button type="submit" name="link_closed_ticket_to_project" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Link Ticket</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fas fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
