<?php

require_once '../../includes/modal_header.php';

$ticket_status_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT * FROM ticket_statuses WHERE ticket_status_id = $ticket_status_id LIMIT 1");
$row = mysqli_fetch_assoc($sql);
$ticket_status_name = escapeHtml($row['ticket_status_name']);
$ticket_status_color = escapeHtml($row['ticket_status_color']);
$ticket_status_order = intval($row['ticket_status_order']);
$ticket_status_active = intval($row['ticket_status_active']);
$ticket_status_pauses_sla = intval($row['ticket_status_pauses_sla']);

// Generate the HTML form content using output buffering.
ob_start();
?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-info-circle mr-2"></i>Editing Ticket Status: <strong><?= $ticket_status_name ?></strong></h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="ticket_status_id" value="<?= $ticket_status_id ?>">

    <div class="modal-body">

        <div class="form-group">
            <label>Name <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                </div>
                <input type="text" class="form-control" name="name" maxlength="200" value="<?= $ticket_status_name ?>" required <?php if ($ticket_status_id <= 5) { echo "readonly"; } ?>>
            </div>
        </div>

        <div class="form-group">
            <label>Color <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-paint-brush"></i></span>
                </div>
                <input type="color" class="form-control col-3" name="color" value="<?= $ticket_status_color ?>" required>
            </div>
        </div>

        <div class="form-group">
            <label>Order</label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-sort-numeric-down"></i></span>
                </div>
                <input type="number" class="form-control" name="order" placeholder="Leave blank for no order" value="<?= $ticket_status_order ?>">
            </div>
        </div>

        <div class="form-group">
            <label>Status <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-info-circle"></i></span>
                </div>
                <select class="form-control select2" name="status" required>
                    <option <?php if ($ticket_status_active == 1) { echo "selected"; } ?> value="1">Active</option>
                    <option <?php if ($ticket_status_active == 0) { echo "selected"; } ?> value="0" <?php if ($ticket_status_id <= 5) { echo "disabled"; } ?>>Inactive</option>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label>SLA</label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-stopwatch"></i></span>
                </div>
                <select class="form-control select2" name="pauses_sla">
                    <option <?php if ($ticket_status_pauses_sla == 0) { echo "selected "; } ?>value="0">Resolution clock keeps running</option>
                    <option <?php if ($ticket_status_pauses_sla == 1) { echo "selected "; } ?>value="1">Pause the resolution clock</option>
                </select>
            </div>
            <small class="text-muted">Tickets sitting in a paused status never warn or breach on resolution. Time already spent is kept and the deadline moves out when the ticket comes back.</small>
        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="edit_ticket_status" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fas fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
