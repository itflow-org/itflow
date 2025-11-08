<?php

require_once '../../../includes/modal_header.php';

$ticket_id = intval($_GET['ticket_id']);

$sql = mysqli_query($mysqli, "SELECT * FROM tickets
    LEFT JOIN clients ON client_id = ticket_client_id
    WHERE ticket_id = $ticket_id
    LIMIT 1"
);

$row = mysqli_fetch_array($sql);
$ticket_prefix = nullable_htmlentities($row['ticket_prefix']);
$ticket_number = intval($row['ticket_number']);
$ticket_scheduled_for = nullable_htmlentities($row['ticket_schedule']);
$ticket_onsite = intval($row['ticket_onsite']);
$client_id = intval($row['ticket_client_id']);
$client_name = nullable_htmlentities($row['client_name']);

// Generate the HTML form content using output buffering.
ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title">
        <i class="fa fa-fw fa-calendar-check mr-2"></i>Scheduling Ticket: <strong><?php echo "$ticket_prefix$ticket_number"; ?></strong>
    </h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <div class="modal-body">
        <input type="hidden" name="ticket_id" value="<?php echo $ticket_id; ?>">

        <div class="form-group">
            <label>Date / Time</label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-calendar-day"></i></span>
                </div>
                <input type="datetime-local" class="form-control" name="scheduled_date_time" placeholder="Scheduled Date & Time" min="<?php echo date('Y-m-d\TH:i'); ?>" <?php if ($ticket_scheduled_for) { echo "value='$ticket_scheduled_for'"; } ?>>
            </div>
        </div>

        <div class="form-group">
            <label>Onsite?</label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-map-marker-alt"></i></span>
                </div>
                <select class="form-control" name="onsite" required>
                    <option value="0" <?php if ($ticket_onsite == 0) echo "selected"; ?>>No</option>
                    <option value="1" <?php if ($ticket_onsite == 1) echo "selected"; ?>>Yes</option>
                </select>
            </div>
        </div>
    </div>

    <div class="modal-footer">
    <?php if ($ticket_scheduled_for) { ?>
        <a href="post.php?cancel_ticket_schedule=<?php echo htmlspecialchars($ticket_id); ?>" class="btn btn-danger text-bold">
            <i class="fa fa-trash mr-2"></i>Cancel Scheduled Time
        </a>
    <?php } ?>
        <button type="submit" name="edit_ticket_schedule" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>

</form>

<?php
require_once '../../../includes/modal_footer.php';
