<?php

/*
 * New document - in the folder it was opened from, or linked to the asset or
 * contact it was opened from.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support', 2);

$client_id = intval($_GET['client_id'] ?? 0);
enforceClientAccess();

$contact_id = assetClientOwnedId('contact', $_GET['contact_id'] ?? 0, $client_id);
$asset = linkRecord('asset', $_GET['asset_id'] ?? 0);
$asset_id = $asset && intval($asset['client_id']) === $client_id ? intval($asset['id']) : 0;
$link_name = $asset_id ? $asset['name'] : ($contact_id ? getFieldById('contacts', $contact_id, 'contact_name') : '');

$document_form_mode = 'add';
$document_name = $document_description = $document_content = '';
$document_folder_id = folderOwnedId($_GET['folder_id'] ?? 0, $client_id);
$document_client_visible = 0;

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-file-alt me-2"></i>New document<?php if ($link_name) { ?> <small class="opacity-75 ms-1">for <?= escapeHtml($link_name) ?></small><?php } ?></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="client_id" value="<?= $client_id ?>">
    <input type="hidden" name="contact" value="<?= $contact_id ?>">
    <input type="hidden" name="asset" value="<?= $asset_id ?>">
    <div class="modal-body">
        <?php require 'document_form.php'; ?>
    </div>
    <div class="modal-footer">
        <button type="submit" name="add_document" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Create</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
