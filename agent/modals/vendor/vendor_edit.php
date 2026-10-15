<?php

require_once '../../../includes/modal_header.php';

$vendor_id = intval($_GET['id'] ?? 0);
$vendor = vendorRow($vendor_id);
if (!$vendor) {
    echo json_encode(['error' => 'That vendor no longer exists.']);
    exit;
}
$client_id = intval($vendor['vendor_client_id']);
vendorEnforce($client_id, 2);

$vendor_form_mode = 'edit';
$vendor_pick_client = false;
$vendor_name = escapeHtml($vendor['vendor_name']);
$vendor_description = escapeHtml($vendor['vendor_description']);
$vendor_account_number = escapeHtml($vendor['vendor_account_number']);
$vendor_code = escapeHtml($vendor['vendor_code']);
$vendor_contact_name = escapeHtml($vendor['vendor_contact_name']);
$vendor_contact_phone_country_code = escapeHtml($vendor['vendor_contact_phone_country_code']);
$vendor_contact_phone = escapeHtml(formatPhoneNumber($vendor['vendor_contact_phone'], $vendor['vendor_contact_phone_country_code'], false));
$vendor_contact_email = escapeHtml($vendor['vendor_contact_email']);
$vendor_phone_country_code = escapeHtml($vendor['vendor_phone_country_code']);
$vendor_phone = escapeHtml(formatPhoneNumber($vendor['vendor_phone'], $vendor['vendor_phone_country_code'], false));
$vendor_extension = escapeHtml($vendor['vendor_extension']);
$vendor_email = escapeHtml($vendor['vendor_email']);
$vendor_website = escapeHtml($vendor['vendor_website']);
$vendor_hours = escapeHtml($vendor['vendor_hours']);
$vendor_sla = escapeHtml($vendor['vendor_sla']);
$vendor_notes = escapeHtml($vendor['vendor_notes']);
$vendor_favorite = intval($vendor['vendor_favorite']);
$vendor_template_id = intval($vendor['vendor_template_id']);
$vendor_tag_ids = array_map(function ($tag) {
    return intval($tag['tag_id']);
}, tagsForRecord('vendor', $vendor_id));

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title text-truncate"><i class="fa fa-fw fa-building me-2"></i>Editing vendor: <strong><?= $vendor_name ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="vendor_id" value="<?= $vendor_id ?>">

    <div class="modal-body">
        <?php require 'vendor_form.php'; ?>
    </div>
    <div class="modal-footer">
        <button type="submit" name="edit_vendor" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
