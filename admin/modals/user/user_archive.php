<?php

require_once '../../includes/modal_header.php';

$user_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT user_avatar, user_email, user_name FROM users WHERE users.user_id = $user_id LIMIT 1");

$row = mysqli_fetch_assoc($sql);
$user_name = escapeHtml($row['user_name']);
$user_email = escapeHtml($row['user_email']);
$user_avatar = escapeHtml($row['user_avatar']);
$user_initials = escapeHtml(initials($user_name));

$sql_related_tickets = mysqli_query($mysqli, "SELECT 1 FROM tickets
    WHERE ticket_assigned_to = $user_id AND ticket_resolved_at IS NULL AND ticket_closed_at IS NULL");

$ticket_count = mysqli_num_rows($sql_related_tickets);

// Related Recurring Tickets Query
$sql_related_recurring_tickets = mysqli_query($mysqli, "SELECT 1 FROM recurring_tickets WHERE recurring_ticket_assigned_to = $user_id");

$recurring_ticket_count = mysqli_num_rows($sql_related_recurring_tickets);

// Generate the HTML form content using output buffering.
ob_start();
?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-user-slash me-2"></i>Archiving user:
        <strong><?= $user_name ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="user_id" value="<?= $user_id ?>">
    <div class="modal-body">


        <center class="mb-3">
            <?php if (!empty($user_avatar)) { ?>
                <img class="img-fluid" src="<?= "../uploads/users/$user_id/$user_avatar" ?>">
            <?php } else { ?>
                <span class="fa-stack fa-4x">
                    <i class="fa fa-circle fa-stack-2x text-secondary"></i>
                    <span class="fa fa-stack-1x text-white"><?= $user_initials ?></span>
                </span>
            <?php } ?>
        </center>


        <div class="mb-3">
            <label>Reassign <?= $ticket_count ?> Open Tickets and <?= $recurring_ticket_count ?> Recurring Tickets To:</label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                <select class="form-control select2" name="ticket_assign" required>
                    <option value="0">No one</option>
                    <?php
                    $sql_users = mysqli_query($mysqli, "SELECT user_id, user_name FROM users WHERE user_type = 1 AND user_archived_at IS NULL");
                    while ($row = mysqli_fetch_assoc($sql_users)) {
                        $user_id_select = intval($row['user_id']);
                        $user_name_select = escapeHtml($row['user_name']);

                        ?>
                        <option value="<?= $user_id_select ?>"><?= $user_name_select ?></option>
                    <?php } ?>

                </select>
            </div>
        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="archive_user" class="btn btn-danger text-bold"><i class="fas fa-archive me-2"></i>Archive</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once "../../../includes/modal_footer.php";
