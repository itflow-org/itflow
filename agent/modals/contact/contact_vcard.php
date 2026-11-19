<?php

/*
 * A contact into a phone's contacts: a QR code of their card to scan with the phone's camera, or
 * the card as a .vcf to download (functions/contact.php contactVcard()).
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_client');

$contact_id = intval($_GET['id'] ?? 0);

$row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT contact_name, contact_client_id FROM contacts WHERE contact_id = $contact_id LIMIT 1"));
if (!$row) {
    echo json_encode(['error' => 'That contact no longer exists.']);
    exit;
}
$client_id = intval($row['contact_client_id']);
enforceClientAccess();

$vcard = contactVcard($contact_id);

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title text-truncate"><i class="fas fa-fw fa-mobile-alt me-2"></i>Save <strong><?= escapeHtml($row['contact_name']) ?></strong> to your phone</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body text-center">
    <div class="itflow-vcard-qr mx-auto mb-3"><?= qrCodeSvg($vcard, 'role="img" aria-label="QR code of the contact card"') ?></div>
    <p class="mb-0">Point your phone's camera at the code and tap to add them to your contacts.</p>
    <p class="small text-body-secondary mb-0">Or download the card and open it on your phone or computer.</p>
</div>
<div class="modal-footer">
    <a class="btn btn-primary" href="post.php?download_contact_vcard=<?= $contact_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"><i class="fas fa-download me-2"></i>Download .vcf</a>
    <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Close</button>
</div>

<?php
require_once '../../../includes/modal_footer.php';
