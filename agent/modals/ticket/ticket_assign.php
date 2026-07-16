<?php

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support', 2);

$ticket_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT * FROM tickets
    LEFT JOIN clients ON client_id = ticket_client_id
    WHERE ticket_id = $ticket_id
    LIMIT 1"
);

$row = mysqli_fetch_assoc($sql);
$ticket_prefix = escapeHtml($row['ticket_prefix']);
$ticket_number = intval($row['ticket_number']);
$ticket_assigned_to = intval($row['ticket_assigned_to']);
$ticket_status = intval($row['ticket_status']);
$ticket_closed_at = escapeHtml($row['ticket_closed_at']);
$client_name = escapeHtml($row['client_name']);
$client_id = intval($row['ticket_client_id']);

if ($client_id) {
    enforceClientAccess();
}

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class='fa fa-fw fa-user-check mr-2'></i>Assigning Ticket: <strong><?php echo "$ticket_prefix$ticket_number"; ?></strong> - <?php echo $client_name; ?></h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>

<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
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
                    <option value="0">Unassigned</option>
                    <?php
                    $sql_users_select = mysqli_query($mysqli, "SELECT user_id, user_name FROM users
                        WHERE user_type = 1
                        AND user_archived_at IS NULL
                        ORDER BY user_name ASC"
                    );
                    while ($row = mysqli_fetch_assoc($sql_users_select)) {
                        $user_id_select = intval($row['user_id']);
                        $user_name_select = escapeHtml($row['user_name']);

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

require_once '../../../includes/modal_footer.php';
