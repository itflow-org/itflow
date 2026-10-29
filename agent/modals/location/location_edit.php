<?php

/*
 * Edit a location - the same form as New Location (location_form.php).
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_client', 2);

$location_id = intval($_GET['id'] ?? 0);
$row = locationRow($location_id);
if (!$row) {
    echo json_encode(['error' => 'That location no longer exists.']);
    exit;
}

$client_id = intval($row['location_client_id']);
enforceClientAccess();

$location_form_mode = 'edit';
$location_name = escapeHtml($row['location_name']);
$location_description = escapeHtml($row['location_description']);
$location_address = escapeHtml($row['location_address']);
$location_city = escapeHtml($row['location_city']);
$location_state = escapeHtml($row['location_state']);
$location_zip = escapeHtml($row['location_zip']);
$location_country = escapeHtml($row['location_country']);
$location_phone_country_code = escapeHtml($row['location_phone_country_code']);
$location_phone = escapeHtml(formatPhoneNumber($row['location_phone'], $row['location_phone_country_code'], false));
$location_extension = escapeHtml($row['location_phone_extension']);
$location_fax_country_code = escapeHtml($row['location_fax_country_code']);
$location_fax = escapeHtml(formatPhoneNumber($row['location_fax'], $row['location_fax_country_code'], false));
$location_hours = escapeHtml($row['location_hours']);
$location_notes = escapeHtml($row['location_notes']);
$location_photo = escapeHtml($row['location_photo']);
$location_primary = intval($row['location_primary']);
$location_contact_id = intval($row['location_contact_id']);

$location_tag_ids = [];
$sql_location_tags = mysqli_query($mysqli, "SELECT tag_id FROM location_tags WHERE location_id = $location_id");
while ($tag_row = mysqli_fetch_row($sql_location_tags)) {
    $location_tag_ids[] = intval($tag_row[0]);
}

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title text-truncate"><i class="fa fa-fw fa-map-marker-alt me-2"></i>Editing location: <strong><?= $location_name ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="location_id" value="<?= $location_id ?>">

    <div class="modal-body">
        <?php require 'location_form.php'; ?>
    </div>
    <div class="modal-footer">
        <button type="submit" name="edit_location" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
