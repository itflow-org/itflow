<?php

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support', 2);

$unit_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT unit_asset_id, unit_device, unit_end_number, unit_face, unit_full_depth, unit_notes,
    unit_power_watts, unit_rack_id, unit_start_number, unit_type, rack_client_id, rack_name, rack_units, asset_name
    FROM rack_units
    LEFT JOIN racks ON rack_id = unit_rack_id
    LEFT JOIN assets ON asset_id = unit_asset_id
    WHERE unit_id = $unit_id LIMIT 1");

$row = mysqli_fetch_assoc($sql);
if (!$row) {
    echo json_encode(['error' => 'That item is no longer in the rack.']);
    exit;
}
$rack_id = intval($row['unit_rack_id']);
$rack_name = escapeHtml($row['rack_name']);
$rack_units = intval($row['rack_units']);
$client_id = intval($row['rack_client_id']);

$unit_asset_id = intval($row['unit_asset_id']);
$unit_device = escapeHtml($row['unit_device']);
$unit_type = $row['unit_type'] ?: 'Device';
$unit_start = intval($row['unit_start_number']);
$unit_height = max(1, intval($row['unit_end_number']) - $unit_start + 1);
$unit_face = $row['unit_face'] === 'rear' ? 'rear' : 'front';
$unit_full_depth = intval($row['unit_full_depth']);
$unit_power_watts = intval($row['unit_power_watts']);
$unit_notes = escapeHtml($row['unit_notes']);
$unit_name = escapeHtml(rackUnitName($row['asset_name'], $row['unit_device'], $row['unit_type']));

enforceClientAccess();

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title text-truncate"><i class="fa fa-fw fa-edit me-2"></i><?= $unit_name ?> <span class="fw-normal text-white-50">in <?= $rack_name ?></span></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="unit_id" value="<?= $unit_id ?>">

    <div class="modal-body">
        <?php require 'rack_device_form.php'; ?>
    </div>
    <div class="modal-footer">
        <a class="btn btn-link text-danger me-auto confirm-link" href="post.php?remove_rack_unit=<?= $unit_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
            <i class="fas fa-fw fa-minus-circle me-1"></i>Remove from rack
        </a>
        <button type="submit" name="edit_rack_unit" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
