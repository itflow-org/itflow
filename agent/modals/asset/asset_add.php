<?php

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support', 2);

$client_id = intval($_GET['client_id'] ?? 0);
if ($client_id) {
    enforceClientAccess();
}

// The assets list passes the tab it was on - start on that kind of asset
$type_presets = ['workstation' => 'Desktop', 'server' => 'Server', 'virtual' => 'Virtual Machine', 'network' => 'Switch'];
$type_param = strtolower((string) ($_GET['type'] ?? ''));

$asset_form_mode = 'add';
$asset_id = 0;
$asset_type = $type_presets[$type_param] ?? '';
$asset_name = $asset_description = $asset_make = $asset_model = $asset_serial = $asset_os = '';
$asset_ip = $asset_ipv6 = $asset_nat_ip = $asset_mac = $asset_uri = $asset_uri_2 = $asset_uri_client = '';
$asset_status = $asset_purchase_reference = $asset_purchase_date = $asset_install_date = $asset_warranty_expire = '';
$asset_physical_location = $asset_notes = $asset_photo = '';
$asset_favorite = 0;
$asset_location_id = $asset_vendor_id = $asset_network_id = 0;
$asset_contact_id = intval($_GET['contact_id'] ?? 0);
$asset_tag_id_array = [];

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-desktop me-2"></i>New Asset</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <?php if ($client_id) { ?>
        <input type="hidden" name="client_id" value="<?= $client_id ?>">
    <?php } ?>

    <div class="modal-body ui-front">
        <?php require 'asset_form.php'; ?>
    </div>
    <div class="modal-footer">
        <button type="submit" name="add_asset" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Create</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php

require_once '../../../includes/modal_footer.php';
