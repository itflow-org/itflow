<?php

/*
 * New license - for the client it was opened from, or pick the client here.
 * Opened from an asset or a contact (?asset_id= / ?contact_id=), that device
 * or user starts with a seat on it; ?template_id= starts it from a template.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support', 2);

$client_id = intval($_GET['client_id'] ?? 0);
if ($client_id) {
    enforceClientAccess();
}

$software_form_mode = 'add';
$software_id = 0;
$software_name = $software_version = $software_type = $software_description = $software_license_type = '';
$software_seats = $software_key = $software_purchase_reference = $software_purchase = $software_expire = $software_notes = '';
$software_vendor_id = 0;
$software_favorite = 0;

// Opened from a device or a user - they get the first seat
$software_asset_ids = $client_id ? softwareClientIds('asset', [$_GET['asset_id'] ?? 0], $client_id) : [];
$software_contact_ids = $client_id ? softwareClientIds('contact', [$_GET['contact_id'] ?? 0], $client_id) : [];
$for_name = $software_asset_ids ? getFieldById('assets', $software_asset_ids[0], 'asset_name') : ($software_contact_ids ? getFieldById('contacts', $software_contact_ids[0], 'contact_name') : '');

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-cube me-2"></i>New License<?php if ($for_name) { ?> <small class="opacity-75 ms-1">for <?= escapeHtml($for_name) ?></small><?php } ?></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <?php if ($client_id) { ?>
        <input type="hidden" name="client_id" value="<?= $client_id ?>">
    <?php } ?>

    <div class="modal-body">
        <?php require 'software_form.php'; ?>
    </div>
    <div class="modal-footer">
        <button type="submit" name="add_software" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Create</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
