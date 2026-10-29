<?php

/*
 * New certificate - for the client it was opened from, or pick the client
 * here. Give the host it is served on and it is checked as it is saved: the
 * certificate it serves, its issuer, dates and names, and whether it is OK.
 * For a host ITFlow can't reach, paste the certificate or type the issuer
 * and expiry. Opened from a domain (?domain_id=) it starts on that domain.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support', 2);

$client_id = intval($_GET['client_id'] ?? 0);
$certificate_domain_id = 0;
$certificate_domain = '';

// From a domain - its client, the domain, and the domain as the host
$from_domain = domainRow($_GET['domain_id'] ?? 0);
if ($from_domain) {
    $client_id = intval($from_domain['domain_client_id']);
    $certificate_domain_id = intval($from_domain['domain_id']);
    $certificate_domain = escapeHtml($from_domain['domain_name']);
}
if ($client_id) {
    enforceClientAccess();
}

$certificate_form_mode = 'add';
$certificate_id = 0;
$certificate_name = $certificate_description = $certificate_issued_by = $certificate_expire = $certificate_public_key = $certificate_notes = '';
$certificate_favorite = 0;

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-lock me-2"></i>New Certificate</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <?php if ($client_id) { ?>
        <input type="hidden" name="client_id" value="<?= $client_id ?>">
    <?php } ?>

    <div class="modal-body">
        <?php require 'certificate_form.php'; ?>
    </div>
    <div class="modal-footer">
        <button type="submit" name="add_certificate" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Create</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
