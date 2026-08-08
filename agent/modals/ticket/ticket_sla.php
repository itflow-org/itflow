<?php

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support', 2);

$ticket_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT ticket_client_id, ticket_number, ticket_prefix, ticket_sla_id FROM tickets
    WHERE ticket_id = $ticket_id
    LIMIT 1"
);

$row = mysqli_fetch_assoc($sql);
$ticket_prefix = escapeHtml($row['ticket_prefix']);
$ticket_number = intval($row['ticket_number']);
$ticket_sla_id = intval($row['ticket_sla_id']);
$client_id = intval($row['ticket_client_id']);

if ($client_id) {
    enforceClientAccess();
}

$sql_slas = mysqli_query($mysqli, "SELECT sla_id, sla_name FROM slas WHERE sla_archived_at IS NULL ORDER BY sla_name ASC");

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-stopwatch mr-2"></i>Editing SLA: <strong><?= "$ticket_prefix$ticket_number" ?></strong></h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="ticket_id" value="<?= $ticket_id ?>">

    <div class="modal-body">

        <div class="form-group">
            <label>SLA</label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-stopwatch"></i></span>
                </div>
                <select class="form-control select2" name="sla_id" required>
                    <option value="0" <?php if ($ticket_sla_id == 0) { echo "selected"; } ?>>None</option>
                    <?php while ($sla_row = mysqli_fetch_assoc($sql_slas)) { ?>
                        <option value="<?= intval($sla_row['sla_id']) ?>" <?php if ($ticket_sla_id == intval($sla_row['sla_id'])) { echo "selected"; } ?>><?= escapeHtml($sla_row['sla_name']) ?></option>
                    <?php } ?>
                </select>
            </div>
            <small class="text-muted">Response and resolution targets are recalculated from the ticket's creation time.</small>
        </div>

    </div>

    <div class="modal-footer">
        <button type="submit" name="edit_ticket_sla" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>

</form>

<?php

require_once '../../../includes/modal_footer.php';
