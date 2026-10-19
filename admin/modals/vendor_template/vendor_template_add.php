<?php

/*
 * New vendor template - the fields are vendor_template_form.php. With
 * from_vendor (a vendor's id) it starts from that vendor - not its account
 * number, PIN or account manager, which are its client's own - and the
 * vendor is linked to the new template.
 */

require_once '../../includes/modal_header.php';

$vt_id = 0;
$vt = [];
$from_vendor = null;
$from_vendor_id = intval($_GET['from_vendor'] ?? 0);
if ($from_vendor_id) {
    $from_vendor = vendorRow($from_vendor_id);
    if ($from_vendor) {
        $vt = [
            'name' => $from_vendor['vendor_name'],
            'description' => $from_vendor['vendor_description'],
            'phone_country_code' => $from_vendor['vendor_phone_country_code'],
            'phone' => $from_vendor['vendor_phone'],
            'extension' => $from_vendor['vendor_extension'],
            'hours' => $from_vendor['vendor_hours'],
            'sla' => $from_vendor['vendor_sla'],
            'email' => $from_vendor['vendor_email'],
            'website' => $from_vendor['vendor_website'],
            'notes' => $from_vendor['vendor_notes'],
        ];
    }
}

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-building me-2"></i>New Vendor Template</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="/admin/post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="modal-body">
        <?php if ($from_vendor) { ?>
            <input type="hidden" name="from_vendor" value="<?= intval($from_vendor['vendor_id']) ?>">
            <div class="alert alert-info small py-2" data-from-vendor>
                <i class="fas fa-fw fa-info-circle me-1"></i>Starting from <strong><?= escapeHtml($from_vendor['vendor_name']) ?></strong><?= $from_vendor['client_name'] !== null ? ' for ' . escapeHtml($from_vendor['client_name']) : '' ?> - its account number, PIN and account manager stay its own. It is linked to the new template.
            </div>
        <?php } ?>
        <?php require __DIR__ . '/vendor_template_form.php'; ?>
    </div>
    <div class="modal-footer">
        <button type="submit" name="add_vendor_template" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Create Vendor Template</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
