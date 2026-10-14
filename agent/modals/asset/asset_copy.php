<?php

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support', 2);

$source_asset_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT asset_client_id, asset_contact_id, asset_description,
    asset_install_date, asset_location_id, asset_make, asset_model, asset_name,
    asset_notes, asset_os, asset_physical_location, asset_purchase_date,
    asset_purchase_reference, asset_status, asset_type, asset_vendor_id, asset_warranty_expire,
    interface_network_id FROM assets
    LEFT JOIN asset_interfaces ON interface_asset_id = asset_id AND interface_primary = 1
    WHERE asset_id = $source_asset_id LIMIT 1"
);

$row = mysqli_fetch_assoc($sql);
if (!$row) {
    echo json_encode(['error' => 'That asset no longer exists.']);
    exit;
}
$client_id = intval($row['asset_client_id']);

enforceClientAccess();

/*
 * A copy is the next unit of the same kind - same model, place and paperwork,
 * but its own serial, addresses, links and photo, so those start empty.
 */
$asset_form_mode = 'copy';
$asset_id = 0;
$asset_type = escapeHtml($row['asset_type']);
$asset_name = escapeHtml($row['asset_name']);
$asset_description = escapeHtml($row['asset_description']);
$asset_make = escapeHtml($row['asset_make']);
$asset_model = escapeHtml($row['asset_model']);
$asset_os = escapeHtml($row['asset_os']);
$asset_status = escapeHtml($row['asset_status']);
$asset_purchase_reference = escapeHtml($row['asset_purchase_reference']);
$asset_purchase_date = escapeHtml($row['asset_purchase_date']);
$asset_warranty_expire = escapeHtml($row['asset_warranty_expire']);
$asset_install_date = escapeHtml($row['asset_install_date']);
$asset_physical_location = escapeHtml($row['asset_physical_location']);
$asset_notes = escapeHtml($row['asset_notes']);
$asset_vendor_id = intval($row['asset_vendor_id']);
$asset_location_id = intval($row['asset_location_id']);
$asset_contact_id = intval($row['asset_contact_id']);
$asset_network_id = intval($row['interface_network_id']);
$asset_serial = $asset_ip = $asset_ipv6 = $asset_nat_ip = $asset_mac = '';
$asset_uri = $asset_uri_2 = $asset_uri_client = $asset_photo = '';
$asset_favorite = 0;

$device_icon = getAssetIcon($row['asset_type']);

// Tags come along
$asset_tag_id_array = array();
$sql_asset_tags = mysqli_query($mysqli, "SELECT asset_tag_tag_id FROM asset_tags WHERE asset_tag_asset_id = $source_asset_id");
while ($row = mysqli_fetch_assoc($sql_asset_tags)) {
    $asset_tag_id_array[] = intval($row['asset_tag_tag_id']);
}

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title text-truncate"><i class='fa fa-fw fa-<?= $device_icon ?> me-2'></i>Copying asset: <strong><?= $asset_name ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="client_id" value="<?= $client_id ?>">

    <div class="modal-body ui-front">
        <div class="alert alert-info small py-2"><i class="fas fa-info-circle me-2"></i>Serial, addresses, links and photo start empty - they belong to the unit you're copying.</div>
        <?php require 'asset_form.php'; ?>
    </div>
    <div class="modal-footer">
        <button type="submit" name="add_asset" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Create Copy</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
