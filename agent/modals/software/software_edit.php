<?php

/*
 * Edit a license - the same form as New License (software_form.php), with
 * its devices and users already picked.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support', 2);

$software_id = intval($_GET['id'] ?? 0);
$row = softwareRow($software_id);
if (!$row) {
    echo json_encode(['error' => 'That license no longer exists.']);
    exit;
}

$client_id = intval($row['software_client_id']);
enforceClientAccess();

$software_form_mode = 'edit';
$software_name = escapeHtml($row['software_name']);
$software_version = escapeHtml($row['software_version']);
$software_type = escapeHtml($row['software_type']);
$software_description = escapeHtml($row['software_description']);
$software_license_type = escapeHtml($row['software_license_type']);
$software_seats = $row['software_seats'] !== null && intval($row['software_seats']) > 0 ? intval($row['software_seats']) : '';
$software_key = escapeHtml($row['software_key']);
$software_purchase_reference = escapeHtml($row['software_purchase_reference']);
$software_purchase = escapeHtml($row['software_purchase']);
$software_expire = escapeHtml($row['software_expire']);
$software_notes = escapeHtml($row['software_notes']);
$software_vendor_id = intval($row['software_vendor_id']);
$software_favorite = intval($row['software_favorite']);

$holders = softwareSeatHolders($software_id);
$software_asset_ids = array_map('intval', array_column($holders['assets'], 'asset_id'));
$software_contact_ids = array_map('intval', array_column($holders['contacts'], 'contact_id'));

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title text-truncate"><i class="fa fa-fw fa-cube me-2"></i>Editing license: <strong><?= $software_name ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="software_id" value="<?= $software_id ?>">

    <div class="modal-body">
        <?php require 'software_form.php'; ?>
    </div>
    <div class="modal-footer">
        <button type="submit" name="edit_software" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
