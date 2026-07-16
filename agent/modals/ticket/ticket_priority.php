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
$ticket_priority = escapeHtml($row['ticket_priority']);
$client_name = escapeHtml($row['client_name']);
$client_id = intval($row['ticket_client_id']);

if ($client_id) {
    enforceClientAccess();
}

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-thermometer-half mr-2"></i>Editing priority: <strong><?php echo "$ticket_prefix$ticket_number"; ?></strong></h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="ticket_id" value="<?php echo $ticket_id; ?>">

    <div class="modal-body">

        <div class="form-group">
            <label>Priority</label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-thermometer-half"></i></span>
                </div>
                <select class="form-control select2" name="priority" required>
                    <option <?php if ($ticket_priority == 'Low') { echo "selected"; } ?> >Low</option>
                    <option <?php if ($ticket_priority == 'Medium') { echo "selected"; } ?> >Medium</option>
                    <option <?php if ($ticket_priority == 'High') { echo "selected"; } ?> >High</option>
                </select>
            </div>
        </div>

    </div>

    <div class="modal-footer">
        <button type="submit" name="edit_ticket_priority" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>

</form>

<?php

require_once '../../../includes/modal_footer.php';
