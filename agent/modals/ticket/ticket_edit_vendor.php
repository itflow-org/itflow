<?php

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support', 2);

// The ticket, after the module and client checks - or the modal's error
$row = ticketForModal($_GET['ticket_id'] ?? 0, 2, true);
$ticket_id = intval($row['ticket_id']);

$ticket_prefix = escapeHtml($row['ticket_prefix']);
$ticket_number = intval($row['ticket_number']);
$vendor_id = intval($row['ticket_vendor_id']);
$vendor_ticket_number = escapeHtml($row['ticket_vendor_ticket_number']);
$client_id = intval($row['ticket_client_id']);

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-building me-2"></i>Vendor <small class="ms-1 opacity-75"><?= "$ticket_prefix$ticket_number" ?></small></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="ticket_id" value="<?= $ticket_id ?>">
    <div class="modal-body">

        <div class="mb-3">
            <label>Vendor</label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-building"></i></span>
                <select class="form-select select2" name="vendor">
                    <option value="0">No vendor</option>
                    <?php

                    // The client's vendors, starred first - and the one already set, even if archived since
                    $sql_vendors = mysqli_query($mysqli, "SELECT vendor_id, vendor_name, vendor_description, vendor_archived_at FROM vendors
                        WHERE vendor_client_id = $client_id AND (vendor_archived_at IS NULL OR vendor_id = $vendor_id)
                        ORDER BY vendor_favorite DESC, vendor_name ASC");
                    while ($row = mysqli_fetch_assoc($sql_vendors)) {
                        $vendor_id_select = intval($row['vendor_id']);
                        $vendor_name = escapeHtml($row['vendor_name']);
                        ?>
                        <option <?php if ($vendor_id == $vendor_id_select) { echo "selected"; } ?> value="<?= $vendor_id_select ?>"<?= selectOptionVendor($row['vendor_name'], $row['vendor_description']) ?>><?= $vendor_name ?><?= $row['vendor_archived_at'] ? ' (archived)' : '' ?></option>

                        <?php
                    }
                    ?>
                </select>
            </div>
        </div>

        <div class="mb-3">
            <label>Vendor's ticket number</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-hashtag"></i></span>
                <input type="text" class="form-control" name="vendor_ticket_number" placeholder="Their reference for this issue" maxlength="255" value="<?= $vendor_ticket_number ?>">
            </div>
        </div>

    </div>

    <div class="modal-footer">
        <button type="submit" name="edit_ticket_vendor" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>

</form>

<?php
require_once '../../../includes/modal_footer.php';
