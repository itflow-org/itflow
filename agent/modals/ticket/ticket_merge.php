<?php

require_once '../../../includes/modal_header.php';

$ticket_id = intval($_GET['ticket_id']);

$sql = mysqli_query($mysqli, "SELECT * FROM tickets
    LEFT JOIN clients ON client_id = ticket_client_id
    WHERE ticket_id = $ticket_id
    LIMIT 1"
);

$row = mysqli_fetch_assoc($sql);
$ticket_prefix = escapeHtml($row['ticket_prefix']);
$ticket_number = intval($row['ticket_number']);
$client_name = escapeHtml($row['client_name']);
$client_id = intval($row['ticket_client_id']);

enforceClientAccess();

$sql_merge = mysqli_query($mysqli, "SELECT * FROM tickets
    LEFT JOIN ticket_statuses ON ticket_status = ticket_status_id
    LEFT JOIN clients ON client_id = ticket_client_id
    WHERE ticket_closed_at IS NULL
    AND ticket_id != $ticket_id
    $access_permission_query
    ORDER BY ticket_status ASC, ticket_id DESC"
);

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-clone mr-2"></i>Merge & Close <?= "$ticket_prefix$ticket_number" ?> into another ticket</h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" id="current_ticket_id" name="ticket_id" value="<?= $ticket_id ?>">
    <div class="modal-body">

        <div class="alert alert-dark">
            The current ticket <strong><?= "$ticket_prefix$ticket_number" ?></strong> will be closed once merging is complete.
        </div>

        <div class="form-group">
            <label>Ticket number to merge this ticket into <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                </div>
                <select class="form-control select2" name="merge_into_ticket_id" required>
                    <option value=''>- Select a Ticket -</option>
                    <?php
                    while ($row = mysqli_fetch_assoc($sql_merge)) {
                        $ticket_id_merge = intval($row['ticket_id']);
                        $ticket_prefix_merge = escapeHtml($row['ticket_prefix']);
                        $ticket_number_merge = intval($row['ticket_number']);
                        $ticket_status_name_merge = escapeHtml($row['ticket_status_name']);
                        $client_name_merge = escapeHtml($row['client_name']);
                        $ticket_subject_merge = escapeHtml($row['ticket_subject']);
                        ?>
                        <option value="<?= $ticket_id_merge ?>">
                            <?= "$ticket_prefix_merge$ticket_number_merge ($ticket_status_name_merge) $client_name_merge -  $ticket_subject_merge" ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label>Reason for merge <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-sticky-note"></i></span>
                </div>
                <input type="text" class="form-control" name="merge_comment" placeholder="Comments" required>
            </div>
        </div>

        <div class="form-group">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="merge_move_replies" value="1">
                <label class="form-check-label" for="checkMoveReplies">
                    Move notes & replies to the new parent ticket
                </label>
            </div>
        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="merge_ticket" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Merge</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
