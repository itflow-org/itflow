<?php

require_once '../../../includes/modal_header.php';

$ticket_reply_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT * FROM ticket_replies
    LEFT JOIN tickets ON ticket_id = ticket_reply_ticket_id
    WHERE ticket_reply_id = $ticket_reply_id
    LIMIT 1"
);

$row = mysqli_fetch_array($sql);
$ticket_reply_type = nullable_htmlentities($row['ticket_reply_type']);
$ticket_reply_time_worked = date_create($row['ticket_reply_time_worked']);
$ticket_reply_time_worked_formatted = date_format($ticket_reply_time_worked, 'H:i:s');
$ticket_reply = nullable_htmlentities($row['ticket_reply']);
$ticket_reply_created_at = nullable_htmlentities($row['ticket_reply_created_at']);
$ticket_reply_by = intval($row['ticket_reply_by']);
$ticket_reply_date = '';
if (!empty($ticket_reply_created_at)) {
    $ticket_reply_date = date('Y-m-d', strtotime($ticket_reply_created_at));
}
if (empty($ticket_reply_date)) {
    $ticket_reply_date = date('Y-m-d');
}

$client_id = intval($row['ticket_client_id']);

// Generate the HTML form content using output buffering.
ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-edit mr-2"></i>Editing Ticket Reply</h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="ticket_reply_id" value="<?php echo $ticket_reply_id; ?>">
    <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">

    <div class="modal-body">

        <div class="form-group">
            <div class="btn-group btn-block btn-group-toggle" data-toggle="buttons">
                <label class="btn btn-outline-secondary <?php if ($ticket_reply_type == 'Internal') { echo "active"; } ?>">
                    <input type="radio" name="ticket_reply_type" value="Internal" <?php if ($ticket_reply_type == 'Internal') { echo "checked"; } ?>>Internal Note
                </label>
                <label class="btn btn-outline-secondary <?php if ($ticket_reply_type == 'Public') { echo "active"; } ?>">
                    <input type="radio" name="ticket_reply_type" value="Public" <?php if ($ticket_reply_type == 'Public') { echo "checked"; } ?>>Public Comment
                </label>
            </div>
        </div>

        <div class="form-group">
            <textarea class="form-control tinymce" name="ticket_reply"><?php echo $ticket_reply; ?></textarea>
        </div>

        <?php if (!empty($ticket_reply_time_worked)) { ?>
            <div class="form-row">

                <div class="col-md-4">
                    <div class="form-group">
                        <label>Datum</label>
                        <input type="date" class="form-control" name="time_date" value="<?php echo $ticket_reply_date; ?>" required>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="form-group">
                        <label>Medewerker</label>
                        <select class="form-control select2" name="time_user_id" required>
                            <?php
                            $sql_time_users = mysqli_query($mysqli, "SELECT user_id, user_name FROM users WHERE user_role_id > 1 AND user_type = 1 AND user_status = 1 AND user_archived_at IS NULL ORDER BY user_name ASC");
                            while ($row = mysqli_fetch_array($sql_time_users)) {
                                $time_user_id = intval($row['user_id']);
                                $time_user_name = nullable_htmlentities($row['user_name']);
                                ?>
                                <option value="<?php echo $time_user_id; ?>" <?php if ($time_user_id == $ticket_reply_by) { echo 'selected'; } ?>><?php echo $time_user_name; ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="form-group">
                        <label>Time worked</label>
                        <input class="form-control" name="time" type="text" placeholder="HH:MM:SS" pattern="([01]?[0-9]|2[0-3]):([0-5]?[0-9]):([0-5]?[0-9])" value="<?php echo $ticket_reply_time_worked_formatted; ?>" required>
                    </div>
                </div>

            </div>
        <?php } ?>

    </div>
    <div class="modal-footer">
        <button type="submit" name="edit_ticket_reply" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php

require_once '../../../includes/modal_footer.php';
