<?php
require_once '../../../includes/modal_header.php';

$ticket_id = intval($_GET['ticket_id']);
$current_client_id = intval(getFieldById('tickets', $ticket_id, 'ticket_client_id'));
$ticket_prefix = nullable_htmlentities(getFieldById('tickets', $ticket_id, 'ticket_prefix'));
$ticket_number = nullable_htmlentities(getFieldById('tickets', $ticket_id, 'ticket_number'));

ob_start();
?>

<div class="modal-header bg-dark">
    <h5 class="modal-title">
        <i class="fa fa-fw fa-people-carry mr-2"></i>
        Change <?php echo "$ticket_prefix$ticket_number"; ?> to another client
    </h5>
    <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
</div>

<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="ticket_id" value="<?php echo $ticket_id; ?>">

    <div class="modal-body">
        <div class="form-group">
            <label>New Client <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-users"></i></span>
                </div>
                <select class="form-control select2" name="new_client_id" id="client_select" required>
                    <?php
                    $sql_clients = mysqli_query($mysqli, "SELECT client_id, client_name FROM clients WHERE client_lead = 0 AND client_archived_at IS NULL ORDER BY client_name ASC");
                    while ($row = mysqli_fetch_array($sql_clients)) {
                        $client_id_select = intval($row['client_id']);
                        $client_name = nullable_htmlentities($row['client_name']);
                        ?>
                        <option value="<?= $client_id_select ?>" <?php if ($current_client_id == $client_id_select) echo 'selected'; ?>>
                            <?= $client_name ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label>New Contact</label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                </div>
                <select class="form-control select2" name="new_contact_id" id="contact_select">
                    <option value="">- Select a contact -</option>
                </select>
            </div>
        </div>
    </div>

    <div class="modal-footer">
        <button type="submit" name="change_client_ticket" class="btn btn-primary text-bold">
            <i class="fa fa-check mr-2"></i>Change
        </button>
        <button type="button" class="btn btn-light" data-dismiss="modal">
            <i class="fa fa-times mr-2"></i>Cancel
        </button>
    </div>
</form>

<script src="/agent/js/ticket_change_client.js"></script>

<?php require_once '../../../includes/modal_footer.php'; ?>
