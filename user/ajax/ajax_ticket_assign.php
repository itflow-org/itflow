<?php

require_once '../../includes/modal_header.php';

$ticket_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT * FROM tickets
    LEFT JOIN clients ON client_id = ticket_client_id
    WHERE ticket_id = $ticket_id
    LIMIT 1"
);

$row = mysqli_fetch_array($sql);
$ticket_prefix = nullable_htmlentities($row['ticket_prefix']);
$ticket_number = intval($row['ticket_number']);
$ticket_assigned_to = intval($row['ticket_assigned_to']);
$ticket_status = intval($row['ticket_status']);
$ticket_closed_at = nullable_htmlentities($row['ticket_closed_at']);
$client_name = nullable_htmlentities($row['client_name']);

// Generate the HTML form content using output buffering.
ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class='fa fa-fw fa-user-check mr-2'></i>Assigning Ticket: <strong><?php echo "$ticket_prefix$ticket_number"; ?></strong> - <?php echo $client_name; ?></h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>

<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="ticket_id" value="<?php echo $ticket_id; ?>">
    <input type="hidden" name="ticket_status" value="<?php echo $ticket_status; ?>">
    <div class="modal-body">

        <div class="form-group">
            <label>Assign to</label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-user-check"></i></span>
                </div>
                <select class="form-control select2" name="assigned_to">
                    <option value="0">Not Assigned</option>
                    <?php
                    $sql_users_select = mysqli_query($mysqli, "SELECT users.user_id, user_name FROM users
                        LEFT JOIN user_settings on users.user_id = user_settings.user_id
                        WHERE user_type = 1
                        AND user_archived_at IS NULL
                        ORDER BY user_name DESC"
                    );
                    while ($row = mysqli_fetch_array($sql_users_select)) {
                        $user_id_select = intval($row['user_id']);
                        $user_name_select = nullable_htmlentities($row['user_name']);

                        ?>
                        <option value="<?php echo $user_id_select; ?>" <?php if ($user_id_select  == $ticket_assigned_to) { echo "selected"; } ?>><?php echo $user_name_select; ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>

    </div>

    <div class="modal-footer">
        <button type="submit" name="assign_ticket" class="btn btn-primary text-bold">
            <i class="fa fa-check mr-2"></i>Assign
        </button>
        <button type="button" class="btn btn-light" data-dismiss="modal">
            <i class="fa fa-times mr-2"></i>Cancel
        </button>
    </div>

</form>

<?php

require_once '../../includes/modal_footer.php';
