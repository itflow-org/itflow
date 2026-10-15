<?php

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_credential', 2);

$credential_id = intval($_GET['id'] ?? 0);
$credential = credentialRow($credential_id);
if (!$credential) {
    echo json_encode(['error' => 'That credential no longer exists.']);
    exit;
}
$client_id = intval($credential['credential_client_id']);
enforceClientAccess();

// The form shows the password, so opening it counts as a view
logAudit("Credential", "View", "$session_name viewed credential " . escapeSql($credential['credential_name']) . " (edit)", $client_id, $credential_id);

$credential_form_mode = 'edit';
$credential_name = escapeHtml($credential['credential_name']);
$credential_description = escapeHtml($credential['credential_description']);
$credential_username = escapeHtml(credentialUsername($credential));
$credential_password = escapeHtml((string) decryptCredentialEntry($credential['credential_password']));
$credential_otp_secret = escapeHtml($credential['credential_otp_secret']);
$credential_uri = escapeHtml($credential['credential_uri']);
$credential_uri_2 = escapeHtml($credential['credential_uri_2']);
$credential_note = escapeHtml($credential['credential_note']);
$credential_favorite = intval($credential['credential_favorite']);
$credential_contact_id = intval($credential['credential_contact_id']);
$credential_asset_id = intval($credential['credential_asset_id']);
$credential_tag_ids = array_map(function ($tag) {
    return intval($tag['tag_id']);
}, tagsForRecord('credential', $credential_id));

// Without the vault open the password shows blank and would be saved back blank - stop that here
$vault_open = encryptCredentialEntry('') !== false;

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title text-truncate"><i class="fas fa-fw fa-key me-2"></i>Editing credential: <strong><?= $credential_name ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="credential_id" value="<?= $credential_id ?>">
    <div class="modal-body">
        <?php if (!$vault_open) { ?>
            <div class="alert alert-warning small"><i class="fas fa-lock me-2"></i>Your vault session has expired, so this login cannot be read or saved. Sign out and back in first.</div>
        <?php } ?>
        <?php require 'credential_form.php'; ?>
    </div>
    <div class="modal-footer">
        <button type="submit" name="edit_credential" class="btn btn-primary text-bold" <?= $vault_open ? '' : 'disabled' ?>><i class="fa fa-check me-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
