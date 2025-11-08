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

// Generate the HTML form content using output buffering.
ob_start();
?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-life-ring mr-2"></i>Link open ticket(s) to project: <strong><?php echo $project_name; ?></strong></h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="project_id" value="<?php echo $project_id; ?>">
    <div class="modal-body">

        <div class="form-group">
            <label>Tickets <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-life-ring"></i></span>
                </div>
                <select class="form-control select2" data-placeholder="- Select Tickets- " multiple name="tickets[]" required>
                    <?php

                    $sql_tickets_select = mysqli_query($mysqli, "SELECT * FROM tickets LEFT JOIN clients on ticket_client_id = client_id WHERE ticket_project_id = 0 AND ticket_closed_at IS NULL $client_ticket_select_query");
                    while ($row = mysqli_fetch_array($sql_tickets_select)) {
                        $ticket_id_select = intval($row['ticket_id']);
                        $ticket_prefix_select = nullable_htmlentities($row['ticket_prefix']);
                        $ticket_number_select = intval($row['ticket_number']);
                        $ticket_subject_select = nullable_htmlentities($row['ticket_subject']);
                        $ticket_client_abbreviation_select = nullable_htmlentities($row['client_abbreviation'])
                        ?>
                        <option value="<?php echo $ticket_id_select; ?>"><?php echo "$ticket_prefix_select$ticket_number_select - $ticket_subject_select ($ticket_client_abbreviation_select)"; ?></option>
                        <?php
                    }

                    ?>
                </select>
            </div>
        </div>

    </div>

    <div class="modal-footer">
        <button type="submit" name="link_ticket_to_project" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Link Ticket(s)</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fas fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
