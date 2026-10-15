<?php

/*
 * Add individual keys to a license - paste them one per line. Blank lines and
 * keys the license already has are skipped. Each is then handed to a device
 * or user from the Keys tab.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support', 2);

$software_id = intval($_GET['software_id'] ?? 0);
$row = softwareRow($software_id);
if (!$row) {
    echo json_encode(['error' => 'That license no longer exists.']);
    exit;
}
$client_id = intval($row['software_client_id']);
enforceClientAccess();

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title text-truncate"><i class="fas fa-fw fa-key me-2"></i>Add keys to <strong><?= escapeHtml($row['software_name']) ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="software_id" value="<?= $software_id ?>">
    <div class="modal-body">
        <label class="form-label">Keys</label>
        <textarea class="form-control font-monospace" name="keys" rows="8" placeholder="One key per line" required autofocus></textarea>
        <div class="form-text">Paste straight from the vendor's portal or the order email - one per line. Keys it already has are skipped.</div>
    </div>
    <div class="modal-footer">
        <button type="submit" name="add_software_keys" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Add</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
