<?php

require_once '../../../includes/modal_header.php';

$selected_ids = array_map('intval', $_GET['selected_ids'] ?? []);

$count = count($selected_ids);

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-paper-plane mr-2"></i>Update & Reply <strong><?= $count ?></strong> Tickets</h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>

<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <?php foreach ($selected_ids as $id) { ?><input type="hidden" name="ticket_ids[]" value="<?= $id ?>"><?php } ?>
    <input type="hidden" name="bulk_private_reply" value="0">

    <div class="modal-body">
        <div class="input-group mb-3">
            <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-thermometer-half"></i></span>
            </div>

            <select class="form-control select2" name="bulk_status" required>

                <!-- Show all active ticket statuses, apart from new or closed as these are system-managed -->
                <?php $sql_ticket_status = mysqli_query($mysqli, "SELECT * FROM ticket_statuses WHERE ticket_status_id != 1 AND ticket_status_id != 5 AND ticket_status_active = 1");
                while ($row = mysqli_fetch_array($sql_ticket_status)) {
                    $ticket_status_id_select = intval($row['ticket_status_id']);
                    $ticket_status_name_select = nullable_htmlentities($row['ticket_status_name']); ?>

                    <option value="<?php echo $ticket_status_id_select ?>"> <?php echo $ticket_status_name_select ?> </option>

                <?php } ?>
            </select>

        </div>

        <div class="form-group">
            <textarea class="form-control tinymce" rows="5" name="bulk_reply_details" placeholder="Type an update here"></textarea>
        </div>

        <div class="col-3">
            <div class="form-group">
                <label>Time worked</label>
                <input class="form-control timepicker" id="time_worked" name="time" type="text" placeholder="HH:MM:SS" pattern="([01]?[0-9]|2[0-3]):([0-5]?[0-9]):([0-5]?[0-9])" value="00:01:00" required/>
            </div>
        </div>

        <div class="form-group">
            <div class="custom-control custom-checkbox">
                <input type="checkbox" class="custom-control-input" id="bulkPrivateReplyCheckbox" name="bulk_private_reply" value="1">
                <label class="custom-control-label" for="bulkPrivateReplyCheckbox">Mark as internal</label>
                <small class="form-text text-muted">If checked this note will only be visible to agents.</small>
            </div>
        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="bulk_ticket_reply" class="btn btn-primary text-bold"><i class="fas fa-paper-plane mr-2"></i>Update & Reply</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fas fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
