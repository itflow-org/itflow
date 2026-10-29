<?php

/*
 * Export vendor templates to a file - every current one, or the ones picked,
 * the archived ones if asked, and their account manager if asked - to import
 * into another ITFlow (export_vendor_templates,
 * functions/vendor_template_transfer.php). Never an account number or PIN.
 */

require_once '../../includes/modal_header.php';

$sql = mysqli_query($mysqli, "SELECT vendor_template_id, vendor_template_name, vendor_template_description, vendor_template_archived_at FROM vendor_templates
    ORDER BY vendor_template_archived_at IS NOT NULL, vendor_template_name ASC");

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-file-download me-2"></i>Export vendor templates</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="/admin/post.php" method="post" autocomplete="off" data-vendor-template-export>
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="modal-body">
        <p class="small text-secondary">A file to import into another ITFlow, or keep. Each template goes with what a vendor made from it starts with - what they are, the support line, hours, SLA, support email, site, status page and portal, and its notes. Never an account number or PIN, and who should have one stays here.</p>

        <div class="mb-3">
            <label class="form-label">Vendor templates</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-building"></i></span>
                <select class="form-select select2" name="vendor_template_ids[]" multiple data-placeholder="Every current one">
                    <?php while ($row = mysqli_fetch_assoc($sql)) { ?>
                        <option value="<?= intval($row['vendor_template_id']) ?>"<?= selectOptionVendor($row['vendor_template_name'], $row['vendor_template_archived_at'] ? 'Archived' : $row['vendor_template_description']) ?>><?= escapeHtml($row['vendor_template_name']) ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="form-text">Leave it empty for every current one.</div>
        </div>

        <div class="form-check form-switch mb-2">
            <input class="form-check-input" type="checkbox" role="switch" name="archived" value="1" id="exportVendorTemplatesArchived">
            <label class="form-check-label" for="exportVendorTemplatesArchived">With the archived ones too</label>
        </div>
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" role="switch" name="manager" value="1" id="exportVendorTemplatesManager">
            <label class="form-check-label" for="exportVendorTemplatesManager">With the account manager</label>
            <div class="form-text mt-0">Your rep at each vendor - for a copy of your own, not one you share.</div>
        </div>
    </div>

    <div class="modal-footer">
        <button type="submit" name="export_vendor_templates" class="btn btn-primary text-bold"><i class="fa fa-download me-2"></i>Download</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php

require_once '../../../includes/modal_footer.php';
