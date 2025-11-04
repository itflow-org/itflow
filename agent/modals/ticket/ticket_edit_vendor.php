<?php

require_once '../../../includes/modal_header.php';

$ticket_id = intval($_GET['ticket_id'] ?? 0);

$client_id = intval(getFieldById('tickets', $ticket_id, 'ticket_client_id') ?? 0);
$vendor_id = intval(getFieldById('tickets', $ticket_id, 'ticket_vendor_id') ?? 0);

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-building mr-2"></i>Editing ticket Vendor</h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="ticket_id" value="<?= $ticket_id ?>">
    <div class="modal-body">

        <div class="form-group">
            <label>Vendor</label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-building"></i></span>
                </div>
                <select class="form-control select2" name="vendor">
                    <option value="0">- None -</option>
                    <?php

                    $sql_vendors = mysqli_query($mysqli, "SELECT vendor_id, vendor_name FROM vendors WHERE vendor_client_id = $client_id AND vendor_archived_at IS NULL ORDER BY vendor_name ASC");
                    while ($row = mysqli_fetch_array($sql_vendors)) {
                        $vendor_id_select = intval($row['vendor_id']);
                        $vendor_name = nullable_htmlentities($row['vendor_name']);
                        ?>
                        <option <?php if ($vendor_id == $vendor_id_select) { echo "selected"; } ?> value="<?= $vendor_id_select ?>"><?= $vendor_name ?></option>

                        <?php
                    }
                    ?>
                </select>
            </div>
        </div>

    </div>

    <div class="modal-footer">
        <button type="submit" name="edit_ticket_vendor" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>

</form>

<?php
require_once '../../../includes/modal_footer.php';
