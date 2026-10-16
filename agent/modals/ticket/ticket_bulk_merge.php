<?php

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support', 2);

$ticket_ids = array_values(array_unique(array_filter(array_map('intval', (array) ($_GET['ticket_ids'] ?? [])))));

$count = count($ticket_ids);
if (!$count) {
    echo json_encode(['error' => 'Tick the tickets to merge first.']);
    exit;
}
$ids = implode(',', $ticket_ids);

// Tickets merge into one of their own client's, so they have to share one
$merge_client_ids = [];
$sql_clients = mysqli_query($mysqli, "SELECT DISTINCT ticket_client_id FROM tickets WHERE ticket_id IN ($ids)");
while ($row = mysqli_fetch_row($sql_clients)) {
    $merge_client_ids[] = intval($row[0]);
}
if (count($merge_client_ids) !== 1) {
    echo json_encode(['error' => 'Those tickets are for different clients - tickets only merge into one of the same client\'s.']);
    exit;
}
$client_id = $merge_client_ids[0];
if ($client_id) {
    enforceClientAccess();
}

$sql_merge = mysqli_query($mysqli, "SELECT client_name, ticket_id, ticket_number, ticket_prefix, ticket_status_name, ticket_subject FROM tickets
    LEFT JOIN ticket_statuses ON ticket_status = ticket_status_id
    LEFT JOIN clients ON client_id = ticket_client_id
    WHERE ticket_closed_at IS NULL
    AND ticket_id NOT IN ($ids)
    AND ticket_client_id = $client_id
    ORDER BY ticket_status ASC, ticket_id DESC"
);

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-clone me-2"></i>Merge & close <strong><?= $count ?></strong> ticket<?= $count === 1 ? '' : 's' ?></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <?php foreach ($ticket_ids as $ticket_id) { ?><input type="hidden" name="ticket_ids[]" value="<?= $ticket_id ?>"><?php } ?>
    <input type="hidden" id="current_ticket_id" value="0"> <!-- Can't currently bulk check this -->
    <input type="hidden" name="merge_move_replies" value="0"> <!-- Default 0 -->
    <div class="modal-body">

        <div class="alert alert-dark">
            Selected tickets will be closed once merging is complete.
        </div>

        <div class="mb-3">
            <label>Ticket number to merge this ticket into <strong class="text-danger">*</strong></label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                <select class="form-select select2" name="merge_into_ticket_id" required>
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

        <div class="mb-3">
            <label>Reason for merge <strong class="text-danger">*</strong></label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-sticky-note"></i></span>
                <input type="text" class="form-control" name="merge_comment" placeholder="Comments">
            </div>
        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="bulk_merge_tickets" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Merge</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php require_once '../../../includes/modal_footer.php';
