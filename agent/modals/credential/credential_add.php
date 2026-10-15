<?php

/*
 * New credential - for the client it was opened from, or pick the client
 * here. Opened from an asset or a contact (?asset_id= / ?contact_id=) it
 * starts belonging to them; from a vendor's page (?vendor_id=) it is linked
 * to that vendor once saved.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_credential', 2);

$client_id = intval($_GET['client_id'] ?? 0);
if ($client_id) {
    enforceClientAccess();
}

$credential_form_mode = 'add';
$credential_id = 0;
$credential_name = $credential_description = $credential_username = $credential_password = $credential_otp_secret = '';
$credential_uri = $credential_uri_2 = $credential_note = '';
$credential_favorite = 0;
$credential_tag_ids = [];
$credential_contact_id = $client_id ? assetClientOwnedId('contact', $_GET['contact_id'] ?? 0, $client_id) : 0;
$credential_asset_id = $client_id ? assetClientOwnedId('asset', $_GET['asset_id'] ?? 0, $client_id) : 0;

// Opened from a vendor's page - their portal login, linked to them
$vendor_id = $client_id ? assetClientOwnedId('vendor', $_GET['vendor_id'] ?? 0, $client_id) : 0;
$for_name = $vendor_id ? getFieldById('vendors', $vendor_id, 'vendor_name')
    : ($credential_asset_id ? getFieldById('assets', $credential_asset_id, 'asset_name')
    : ($credential_contact_id ? getFieldById('contacts', $credential_contact_id, 'contact_name') : ''));

// Without the vault open there is nothing to encrypt with - say so before anything is typed
$vault_open = encryptCredentialEntry('') !== false;

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title text-truncate"><i class="fa fa-fw fa-key me-2"></i>New Credential<?php if ($for_name) { ?> <small class="opacity-75 ms-1">for <?= escapeHtml($for_name) ?></small><?php } ?></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="vendor_id" value="<?= $vendor_id ?>">
    <?php if ($client_id) { ?>
        <input type="hidden" name="client_id" value="<?= $client_id ?>">
    <?php } ?>

    <div class="modal-body">
        <?php if (!$vault_open) { ?>
            <div class="alert alert-warning small"><i class="fas fa-lock me-2"></i>Your vault session has expired, so a new login cannot be encrypted. Sign out and back in first.</div>
        <?php } ?>
        <?php require 'credential_form.php'; ?>
    </div>
    <div class="modal-footer">
        <button type="submit" name="add_credential" class="btn btn-primary text-bold" <?= $vault_open ? '' : 'disabled' ?>><i class="fa fa-check me-2"></i>Create</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
