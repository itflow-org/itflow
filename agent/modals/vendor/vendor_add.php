<?php

/*
 * New vendor - the client's it was opened from (?client_id=), one of ours
 * (no client), or for a client picked here (?view=clients, the all-clients
 * list). ?template_id= starts it from that template; ?from_template=1 (New
 * from Template) opens on the template picker.
 */

require_once '../../../includes/modal_header.php';

$client_id = intval($_GET['client_id'] ?? 0);
$vendor_pick_client = !$client_id && ($_GET['view'] ?? '') === 'clients';
if ($vendor_pick_client) {
    enforceUserPermission('module_client', 2);
} else {
    vendorEnforce($client_id, 2);
}

$vendor_form_mode = 'add';
$vendor_id = 0;
$vendor_name = $vendor_description = $vendor_account_number = $vendor_code = '';
$vendor_contact_name = $vendor_contact_phone_country_code = $vendor_contact_phone = $vendor_contact_email = '';
$vendor_phone_country_code = $vendor_phone = $vendor_extension = $vendor_email = $vendor_website = '';
$vendor_hours = $vendor_sla = $vendor_notes = '';
$vendor_favorite = 0;
$vendor_template_id = 0;
$vendor_tag_ids = [];
$vendor_template_focus = !empty($_GET['from_template']);

// Started from a template - its fields, escaped like the rest
$template = vendorTemplateRow($_GET['template_id'] ?? 0);
if ($template) {
    foreach (vendorTemplateFormFields($template) as $field => $value) {
        if ($field === 'vendor_template_id') {
            $vendor_template_id = intval($value);
        } else {
            ${"vendor_$field"} = escapeHtml($value);
        }
    }
}

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-building me-2"></i>New <?= $client_id || $vendor_pick_client ? '' : 'Company ' ?>Vendor</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <?php if (!$vendor_pick_client) { ?>
        <input type="hidden" name="client_id" value="<?= $client_id ?>">
    <?php } ?>

    <div class="modal-body">
        <?php require 'vendor_form.php'; ?>
    </div>
    <div class="modal-footer">
        <button type="submit" name="add_vendor" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Create</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
