<?php

/*
 * Edit a certificate - the host it is served on, its name, what it is for,
 * the star, the domain it belongs to, the certificate itself (or its issuer
 * and expiry by hand) and notes. Saving doesn't check the host again unless
 * it changed; Check now does that. Its history is in the quick look.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support', 2);

$certificate_id = intval($_GET['id'] ?? 0);
$certificate = certificateRow($certificate_id);
if (!$certificate) {
    echo json_encode(['error' => 'That certificate no longer exists.']);
    exit;
}
$client_id = intval($certificate['certificate_client_id']);
enforceClientAccess();

$certificate_form_mode = 'edit';
$certificate_name = escapeHtml($certificate['certificate_name']);
$certificate_description = escapeHtml($certificate['certificate_description']);
$certificate_domain = escapeHtml($certificate['certificate_domain']);
$certificate_domain_id = intval($certificate['certificate_domain_id']);
$certificate_issued_by = escapeHtml($certificate['certificate_issued_by']);
$certificate_expire = escapeHtml($certificate['certificate_expire']);
$certificate_notes = escapeHtml($certificate['certificate_notes']);
$certificate_favorite = intval($certificate['certificate_favorite']);
// Only a real certificate is shown to keep - anything else on file is cleared on save
$certificate_public_key = certificateParse($certificate['certificate_public_key']) ? escapeHtml(trim($certificate['certificate_public_key'])) : '';

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title text-truncate"><i class="fa fa-fw fa-lock me-2"></i>Editing certificate: <strong><?= $certificate_name ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="certificate_id" value="<?= $certificate_id ?>">
    <input type="hidden" name="client_id" value="<?= $client_id ?>">

    <div class="modal-body">
        <?php require 'certificate_form.php'; ?>
    </div>
    <div class="modal-footer">
        <button type="submit" name="edit_certificate" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
