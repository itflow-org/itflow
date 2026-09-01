<?php

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support', 2);

$ticket_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT ticket_billable, ticket_client_id, ticket_number, ticket_prefix FROM tickets WHERE ticket_id = $ticket_id LIMIT 1");

$row = mysqli_fetch_assoc($sql);
$ticket_prefix = escapeHtml($row['ticket_prefix']);
$ticket_number = intval($row['ticket_number']);
$ticket_billable = intval($row['ticket_billable']);
$client_id = intval($row['ticket_client_id']);

enforceClientAccess();

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title">
        <i class="fa fa-fw fa-money-bill me-2"></i>Editing Billable Status: <strong><?= "$ticket_prefix$ticket_number" ?></strong>
    </h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <input type="hidden" name="ticket_id" value="<?= $ticket_id ?>">
        <div class="mb-3">
            <label>Billable?</label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-money-bill"></i></span>
                <select class="form-select" name="billable_status">
                    <option <?php if ($ticket_billable == 1) { echo "selected"; } ?> value="1">Yes</option>
                    <option <?php if ($ticket_billable == 0) { echo "selected"; } ?> value="0">No</option>
                </select>
            </div>
        </div>

    </div>

    <div class="modal-footer">
        <button type="submit" name="edit_ticket_billable_status" class="btn btn-primary text-bold">
            <i class="fa fa-check me-2"></i>Save
        </button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">
            <i class="fa fa-times me-2"></i>Cancel
        </button>
    </div>

</form>

<?php

require_once '../../../includes/modal_footer.php';
