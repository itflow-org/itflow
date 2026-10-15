<?php

/*
 * New location - for the client it was opened from, or pick the client here.
 * Opened from an "Add primary location" link (?primary=1) it starts as the
 * primary; a client's first location is made primary either way.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_client', 2);

$client_id = intval($_GET['client_id'] ?? 0);
if ($client_id) {
    enforceClientAccess();
}

$location_form_mode = 'add';
$location_id = 0;
$location_name = $location_description = $location_address = $location_city = $location_state = $location_zip = '';
$location_country = escapeHtml($session_company_country ?? '');
$location_phone = $location_phone_country_code = $location_extension = $location_fax = $location_fax_country_code = '';
$location_hours = $location_notes = $location_photo = '';
$location_contact_id = 0;
$location_tag_ids = [];

// The first location a client has is its primary
$client_has_location = $client_id ? intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM locations WHERE location_client_id = $client_id AND location_archived_at IS NULL"))[0]) : 1;
$location_primary = !empty($_GET['primary']) || !$client_has_location ? 1 : 0;

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-map-marker-alt me-2"></i>New Location</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <?php if ($client_id) { ?>
        <input type="hidden" name="client_id" value="<?= $client_id ?>">
    <?php } ?>

    <div class="modal-body">
        <?php require 'location_form.php'; ?>
    </div>
    <div class="modal-footer">
        <button type="submit" name="add_location" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Create</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
