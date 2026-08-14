<?php

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support', 2);

$ticket_id = intval($_GET['ticket_id']);

$sql = mysqli_query($mysqli, "SELECT ticket_client_id, ticket_number, ticket_prefix FROM tickets WHERE ticket_id = $ticket_id LIMIT 1");

$row = mysqli_fetch_assoc($sql);
$ticket_prefix = escapeHtml($row['ticket_prefix']);
$ticket_number = intval($row['ticket_number']);
$client_id = intval($row['ticket_client_id']);

if ($client_id) {
    enforceClientAccess();
}

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title">
        <i class="fa fa-fw fa-people-carry me-2"></i>
        Change <?= "$ticket_prefix$ticket_number" ?> to another client
    </h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="ticket_id" value="<?= $ticket_id ?>">

    <div class="modal-body">
        <div class="mb-3">
            <label>New Client <strong class="text-danger">*</strong></label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-users"></i></span>
                <select class="form-control select2" name="new_client_id" id="client_select" required>
                    <?php
                    $sql_clients = mysqli_query($mysqli, "SELECT client_id, client_name FROM clients WHERE client_lead = 0 AND client_archived_at IS NULL " . clientScopeSql('clients.client_id') . " ORDER BY client_name ASC");
                    while ($row = mysqli_fetch_assoc($sql_clients)) {
                        $client_id_select = intval($row['client_id']);
                        $client_name = escapeHtml($row['client_name']);
                        ?>
                        <option value="<?= $client_id_select ?>" <?php if ($client_id == $client_id_select) echo 'selected'; ?>>
                            <?= $client_name ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
        </div>

        <div class="mb-3">
            <label>New Contact</label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                <select class="form-control select2" name="new_contact_id" id="contact_select">
                    <option value="">- Select a contact -</option>
                </select>
            </div>
        </div>
    </div>

    <div class="modal-footer">
        <button type="submit" name="change_client_ticket" class="btn btn-primary text-bold">
            <i class="fa fa-check me-2"></i>Change
        </button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">
            <i class="fa fa-times me-2"></i>Cancel
        </button>
    </div>
</form>

<script src="/agent/js/ticket_change_client.js"></script>

<?php require_once '../../../includes/modal_footer.php'; ?>
