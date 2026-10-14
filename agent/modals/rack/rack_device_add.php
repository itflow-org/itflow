<?php

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support', 2);

$rack_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT rack_client_id, rack_name, rack_units FROM racks WHERE rack_id = $rack_id LIMIT 1");

$row = mysqli_fetch_assoc($sql);
if (!$row) {
    echo json_encode(['error' => 'That rack no longer exists.']);
    exit;
}
$rack_name = escapeHtml($row['rack_name']);
$rack_units = intval($row['rack_units']);
$client_id = intval($row['rack_client_id']);

enforceClientAccess();

// A new item. Clicking an empty U on the rack page passes where it was.
$unit_id = 0;
$unit_asset_id = 0;
$unit_device = '';
$unit_type = 'Device';
$unit_start = min($rack_units, max(0, intval($_GET['u'] ?? 0)));
$unit_height = 1;
$unit_face = ($_GET['face'] ?? '') === 'rear' ? 'rear' : 'front';
$unit_full_depth = 1;
$unit_power_watts = 0;
$unit_notes = '';

// "Rack it" on the rack page passes the asset - start from what that kind of kit usually is
$unit_asset_id = intval($_GET['asset_id'] ?? 0);
if ($unit_asset_id) {
    $preset_asset_type = getFieldById('assets', $unit_asset_id, 'asset_type');
    $unit_height = $preset_asset_type === 'Server' ? 2 : 1;
    $unit_full_depth = in_array($preset_asset_type, ['Access Point', 'Firewall/Router'], true) ? 0 : 1;
}

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-plus me-2"></i>Add to <strong><?= $rack_name ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="rack_id" value="<?= $rack_id ?>">

    <div class="modal-body">
        <?php require 'rack_device_form.php'; ?>
    </div>
    <div class="modal-footer">
        <button type="submit" name="add_rack_unit" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Add to Rack</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
