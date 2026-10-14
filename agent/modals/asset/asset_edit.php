<?php

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support', 2);

$asset_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT asset_client_id, asset_contact_id, asset_description,
    asset_favorite, asset_id, asset_install_date, asset_location_id, asset_make, asset_model,
    asset_name, asset_notes, asset_os, asset_photo, asset_physical_location,
    asset_purchase_date, asset_purchase_reference, asset_serial, asset_status, asset_type,
    asset_uri, asset_uri_2, asset_uri_client, asset_vendor_id, asset_warranty_expire,
    interface_ip, interface_ipv6, interface_mac, interface_nat_ip, interface_network_id FROM assets
    LEFT JOIN asset_interfaces ON interface_asset_id = asset_id AND interface_primary = 1
    WHERE asset_id = $asset_id LIMIT 1"
);

$row = mysqli_fetch_assoc($sql);
if (!$row) {
    echo json_encode(['error' => 'That asset no longer exists.']);
    exit;
}
$client_id = intval($row['asset_client_id']);

enforceClientAccess();

$asset_form_mode = 'edit';
$asset_type = escapeHtml($row['asset_type']);
$asset_name = escapeHtml($row['asset_name']);
$asset_description = escapeHtml($row['asset_description']);
$asset_make = escapeHtml($row['asset_make']);
$asset_model = escapeHtml($row['asset_model']);
$asset_serial = escapeHtml($row['asset_serial']);
$asset_os = escapeHtml($row['asset_os']);
$asset_ip = escapeHtml($row['interface_ip']);
$asset_ipv6 = escapeHtml($row['interface_ipv6']);
$asset_nat_ip = escapeHtml($row['interface_nat_ip']);
$asset_mac = escapeHtml($row['interface_mac']);
$asset_uri = escapeHtml($row['asset_uri']);
$asset_uri_2 = escapeHtml($row['asset_uri_2']);
$asset_uri_client = escapeHtml($row['asset_uri_client']);
$asset_status = escapeHtml($row['asset_status']);
$asset_purchase_reference = escapeHtml($row['asset_purchase_reference']);
$asset_purchase_date = escapeHtml($row['asset_purchase_date']);
$asset_warranty_expire = escapeHtml($row['asset_warranty_expire']);
$asset_install_date = escapeHtml($row['asset_install_date']);
$asset_photo = escapeHtml($row['asset_photo']);
$asset_physical_location = escapeHtml($row['asset_physical_location']);
$asset_notes = escapeHtml($row['asset_notes']);
$asset_favorite = intval($row['asset_favorite']);
$asset_vendor_id = intval($row['asset_vendor_id']);
$asset_location_id = intval($row['asset_location_id']);
$asset_contact_id = intval($row['asset_contact_id']);
$asset_network_id = intval($row['interface_network_id']);

$device_icon = getAssetIcon($row['asset_type']);

// History, newest first
$sql_asset_history = mysqli_query($mysqli, "SELECT asset_history_created_at, asset_history_description, asset_history_status FROM asset_history
    WHERE asset_history_asset_id = $asset_id
    ORDER BY asset_history_id DESC
    LIMIT 25"
);

// Tags
$asset_tag_id_array = array();
$sql_asset_tags = mysqli_query($mysqli, "SELECT asset_tag_tag_id FROM asset_tags WHERE asset_tag_asset_id = $asset_id");
while ($row = mysqli_fetch_assoc($sql_asset_tags)) {
    $asset_tag_id_array[] = intval($row['asset_tag_tag_id']);
}

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title text-truncate"><i class='fa fa-fw fa-<?= $device_icon ?> me-2'></i>Editing asset: <strong><?= $asset_name ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="asset_id" value="<?= $asset_id ?>">

    <div class="modal-body ui-front">
        <?php require 'asset_form.php'; ?>
    </div>
    <div class="modal-footer">
        <button type="submit" name="edit_asset" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
