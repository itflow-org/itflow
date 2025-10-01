<?php

require_once '../../../includes/modal_header.php';

$user_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT * FROM users WHERE users.user_id = $user_id LIMIT 1");

$row = mysqli_fetch_array($sql);
$user_name = nullable_htmlentities($row['user_name']);
$user_email = nullable_htmlentities($row['user_email']);
$user_avatar = nullable_htmlentities($row['user_avatar']);
$user_initials = nullable_htmlentities(initials($user_name));

$sql_related_tickets = mysqli_query($mysqli, "SELECT * FROM tickets
    WHERE ticket_assigned_to = $user_id AND ticket_resolved_at IS NULL AND ticket_closed_at IS NULL");

$ticket_count = mysqli_num_rows($sql_related_tickets);

// Related Recurring Tickets Query
$sql_related_recurring_tickets = mysqli_query($mysqli, "SELECT * FROM recurring_tickets WHERE recurring_ticket_assigned_to = $user_id");

$recurring_ticket_count = mysqli_num_rows($sql_related_recurring_tickets);

// Generate the HTML form content using output buffering.
ob_start();
?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-user-slash mr-2"></i>Archiving user:
        <strong><?php echo $user_name; ?></strong></h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
    <div class="modal-body">


        <center class="mb-3">
            <?php if (!empty($user_avatar)) { ?>
                <img class="img-fluid" src="<?php echo "../uploads/users/$user_id/$user_avatar"; ?>">
            <?php } else { ?>
                <span class="fa-stack fa-4x">
                    <i class="fa fa-circle fa-stack-2x text-secondary"></i>
                    <span class="fa fa-stack-1x text-white"><?php echo $user_initials; ?></span>
                </span>
            <?php } ?>
        </center>


        <div class="form-group">
            <label>Reassign <?= $ticket_count ?> Open Tickets and <?= $recurring_ticket_count ?> Recurring Tickets To:</label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                </div>
                <select class="form-control select2" name="ticket_assign" required>
                    <option value="0">No one</option>
                    <?php
                    $sql_users = mysqli_query($mysqli, "SELECT * FROM users WHERE user_type = 1 AND user_archived_at IS NULL");
                    while ($row = mysqli_fetch_array($sql_users)) {
                        $user_id_select = intval($row['user_id']);
                        $user_name_select = nullable_htmlentities($row['user_name']);

                        ?>
                        <option value="<?= $user_id_select ?>"><?= $user_name_select ?></option>
                    <?php } ?>

                </select>
            </div>
        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="archive_user" class="btn btn-danger text-bold"><i class="fas fa-archive mr-2"></i>Archive</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fas fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once "../../../includes/modal_footer.php";
