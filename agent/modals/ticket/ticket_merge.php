<?php

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support', 2);

// The ticket, after the module and client checks - or the modal's error
$row = ticketForModal($_GET['ticket_id'] ?? 0, 2, true);
$ticket_id = intval($row['ticket_id']);

$ticket_prefix = escapeHtml($row['ticket_prefix']);
$ticket_number = intval($row['ticket_number']);
$client_name = escapeHtml($row['client_name']);
$client_id = intval($row['ticket_client_id']);

// The same client's open tickets - another client's would see this conversation
$sql_merge = mysqli_query($mysqli, "SELECT client_name, ticket_id, ticket_number, ticket_prefix, ticket_status_name, ticket_subject FROM tickets
    LEFT JOIN ticket_statuses ON ticket_status = ticket_status_id
    LEFT JOIN clients ON client_id = ticket_client_id
    WHERE ticket_closed_at IS NULL
    AND ticket_id != $ticket_id
    AND ticket_client_id = $client_id
    ORDER BY ticket_status ASC, ticket_id DESC"
);

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-clone me-2"></i>Merge into another ticket <small class="ms-1 opacity-75"><?= "$ticket_prefix$ticket_number" ?></small></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" id="current_ticket_id" name="ticket_id" value="<?= $ticket_id ?>">
    <div class="modal-body">

        <div class="alert alert-info small">
            <strong><?= "$ticket_prefix$ticket_number" ?></strong> is closed, and its subject and description are added to the ticket you pick as an internal note.
        </div>

        <div class="mb-3">
            <label>Merge into <strong class="text-danger">*</strong></label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                <select class="form-select select2" name="merge_into_ticket_id" required>
                    <option value=''>- Pick the ticket to keep -</option>
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
                            <?= "$ticket_prefix_merge$ticket_number_merge ($ticket_status_name_merge) $client_name_merge - $ticket_subject_merge" ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
        </div>

        <div class="mb-3">
            <label>Reason <strong class="text-danger">*</strong></label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-sticky-note"></i></span>
                <input type="text" class="form-control" name="merge_comment" placeholder="Noted on both tickets" required>
            </div>
        </div>

        <div class="mb-3">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="checkMoveReplies" name="merge_move_replies" value="1">
                <label class="form-check-label" for="checkMoveReplies">
                    Also move this ticket's replies onto it
                </label>
            </div>
        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="merge_ticket" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Merge and close</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
