<?php

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support', 2);

$client_id = intval($_GET['client_id'] ?? 0);

enforceClientAccess();

// A new rack - the shared form reads these
$rack_name = '';
$rack_type = '';
$rack_units = 0;
$rack_units_descending = 0;
$rack_highest_used_unit = 0;
$rack_model = '';
$rack_depth = '';
// Opened from a location's page - start at that location
$rack_location_id = $client_id ? assetClientOwnedId('location', $_GET['location_id'] ?? 0, $client_id) : 0;
$rack_physical_location = '';
$rack_power_capacity = 0;
$rack_description = '';
$rack_photo = '';
$rack_photo_rear = '';
$rack_notes = '';

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-server me-2"></i>New Rack</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="client_id" value="<?= $client_id ?>">

    <div class="modal-body">
        <?php require 'rack_form.php'; ?>
    </div>
    <div class="modal-footer">
        <button type="submit" name="add_rack" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Create</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php

require_once '../../../includes/modal_footer.php';
