<?php

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support', 2);

$rack_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT rack_client_id, rack_depth, rack_description, rack_location_id,
    rack_model, rack_name, rack_notes, rack_photo, rack_photo_rear, rack_physical_location, rack_power_capacity,
    rack_type, rack_units, rack_units_descending FROM racks WHERE rack_id = $rack_id LIMIT 1");

$row = mysqli_fetch_assoc($sql);
if (!$row) {
    echo json_encode(['error' => 'That rack no longer exists.']);
    exit;
}
$rack_name = escapeHtml($row['rack_name']);
$rack_description = escapeHtml($row['rack_description']);
$rack_model = escapeHtml($row['rack_model']);
$rack_depth = escapeHtml($row['rack_depth']);
$rack_type = escapeHtml($row['rack_type']);
$rack_units = intval($row['rack_units']);
$rack_units_descending = intval($row['rack_units_descending']);
$rack_power_capacity = intval($row['rack_power_capacity']);
$rack_photo = escapeHtml($row['rack_photo']);
$rack_photo_rear = escapeHtml($row['rack_photo_rear']);
$rack_physical_location = escapeHtml($row['rack_physical_location']);
$rack_notes = escapeHtml($row['rack_notes']);
$rack_location_id = intval($row['rack_location_id']);
$client_id = intval($row['rack_client_id']);

enforceClientAccess();

// The rack can't shrink below what's mounted in it - the field's minimum says so
$rack_highest_used_unit = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT MAX(unit_end_number) FROM rack_units WHERE unit_rack_id = $rack_id"))[0]);

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-server me-2"></i>Editing rack: <strong><?= $rack_name ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="rack_id" value="<?= $rack_id ?>">

    <div class="modal-body">
        <?php require 'rack_form.php'; ?>
    </div>
    <div class="modal-footer">
        <button type="submit" name="edit_rack" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
