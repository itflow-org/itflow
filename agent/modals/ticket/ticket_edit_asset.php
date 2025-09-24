<?php

require_once '../../../includes/modal_header.php';

$ticket_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT * FROM tickets LEFT JOIN clients ON client_id = ticket_client_id WHERE ticket_id = $ticket_id LIMIT 1");

$row = mysqli_fetch_array($sql);
$client_id = intval($row['client_id']);
$client_name = nullable_htmlentities($row['client_name']);
$ticket_prefix = nullable_htmlentities($row['ticket_prefix']);
$ticket_number = intval($row['ticket_number']);
$ticket_category = intval($row['ticket_category']);
$ticket_subject = nullable_htmlentities($row['ticket_subject']);
$ticket_details = nullable_htmlentities($row['ticket_details']);
$ticket_priority = nullable_htmlentities($row['ticket_priority']);
$ticket_billable = intval($row['ticket_billable']);
$ticket_vendor_ticket_number = nullable_htmlentities($row['ticket_vendor_ticket_number']);
$ticket_created_at = nullable_htmlentities($row['ticket_created_at']);
$ticket_due_at = nullable_htmlentities($row['ticket_due_at']);
$ticket_assigned_to = intval($row['ticket_assigned_to']);
$contact_id = intval($row['ticket_contact_id']);
$asset_id = intval($row['ticket_asset_id']);
$location_id = intval($row['ticket_location_id']);
$vendor_id = intval($row['ticket_vendor_id']);
$project_id = intval($row['ticket_project_id']);

// Additional Assets Selected
$additional_assets_array = array();
$sql_additional_assets = mysqli_query($mysqli, "SELECT asset_id FROM ticket_assets WHERE ticket_id = $ticket_id");
while ($row = mysqli_fetch_array($sql_additional_assets)) {
    $additional_asset_id = intval($row['asset_id']);
    $additional_assets_array[] = $additional_asset_id;
}

// Generate the HTML form content using output buffering.
ob_start();
?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-desktop mr-2"></i>Editing ticket Asset: <strong><?php echo "$ticket_prefix$ticket_number"; ?></strong> - <?php echo $client_name; ?></h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="ticket_id" value="<?php echo $ticket_id; ?>">
    <div class="modal-body">

        <div class="form-group">
            <label>Asset</label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-desktop"></i></span>
                </div>
                <select class="form-control select2" name="asset">
                    <option value="0">- None -</option>
                    <?php

                    $sql_assets = mysqli_query($mysqli, "SELECT asset_id, asset_name, contact_name FROM assets LEFT JOIN contacts ON contact_id = asset_contact_id WHERE asset_client_id = $client_id AND asset_archived_at IS NULL ORDER BY asset_name ASC");
                    while ($row = mysqli_fetch_array($sql_assets)) {
                        $asset_id_select = intval($row['asset_id']);
                        $asset_name_select = nullable_htmlentities($row['asset_name']);
                        $asset_contact_name_select = nullable_htmlentities($row['contact_name']);
                        ?>
                        <option <?php if ($asset_id == $asset_id_select) { echo "selected"; } ?> value="<?php echo $asset_id_select; ?>"><?php echo "$asset_name_select - $asset_contact_name_select"; ?></option>

                        <?php
                    }
                    ?>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label>Additional Assets</label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-desktop"></i></span>
                </div>
                <select class="form-control select2" name="additional_assets[]" data-tags="true" data-placeholder="- Select Additional Assets -" multiple>
                    <option value=""></option>
                    <?php

                    $sql_assets = mysqli_query($mysqli, "SELECT asset_id, asset_name, contact_name FROM assets LEFT JOIN contacts ON contact_id = asset_contact_id WHERE asset_client_id = $client_id AND asset_id != $asset_id AND asset_archived_at IS NULL ORDER BY asset_name ASC");
                    while ($row = mysqli_fetch_array($sql_assets)) {
                        $asset_id_select = intval($row['asset_id']);
                        $asset_name_select = nullable_htmlentities($row['asset_name']);
                        $asset_contact_name_select = nullable_htmlentities($row['contact_name']);
                    ?>
                        <option value="<?php echo $asset_id_select; ?>" 
                            <?php if (in_array($asset_id_select, $additional_assets_array)) { echo "selected"; } ?>
                            ><?php echo "$asset_name_select - $asset_contact_name_select"; ?></option>

                    <?php } ?>
                </select>
            </div>
        </div>

    </div>

    <div class="modal-footer">
        <button type="submit" name="edit_ticket_asset" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>

</form>

<?php
require_once '../../../includes/modal_footer.php';
