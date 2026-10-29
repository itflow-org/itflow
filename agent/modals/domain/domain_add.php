<?php

/*
 * New domain - for the client it was opened from, or pick the client here.
 * One domain, or several pasted at once. Each is looked up as it is saved:
 * its records, its registration and expiry, and the certificate on its
 * website. Opened from a vendor's page (?vendor_id=) that vendor starts as
 * the registrar.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support', 2);

$client_id = intval($_GET['client_id'] ?? 0);
if ($client_id) {
    enforceClientAccess();
}

$domain_form_mode = 'add';
$domain_id = 0;
$domain_name = $domain_description = $domain_expire = $domain_notes = '';
$domain_favorite = $domain_auto_renew = 0;
$domain_tag_ids = [];
$domain_vendor_ids = array_fill_keys(array_keys(DOMAIN_ROLES), 0);
if ($client_id) {
    $domain_vendor_ids['registrar'] = domainVendorId($_GET['vendor_id'] ?? 0, $client_id);
}

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-globe me-2"></i>New Domain</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <?php if ($client_id) { ?>
        <input type="hidden" name="client_id" value="<?= $client_id ?>">
    <?php } ?>

    <div class="modal-body">
        <?php require 'domain_form.php'; ?>
    </div>
    <div class="modal-footer">
        <button type="submit" name="add_domain" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Create</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
