<?php

/*
 * Export an asset's interfaces - the columns to include, and the format.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support');

$asset_id = intval($_GET['asset_id'] ?? 0);
$client_id = intval(getFieldById('assets', $asset_id, 'asset_client_id'));
enforceClientAccess();
$asset_name = getFieldById('assets', $asset_id, 'asset_name');

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title text-truncate"><i class="fas fa-fw fa-download me-2"></i>Export interfaces of <strong><?= escapeHtml($asset_name) ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="asset_id" value="<?= $asset_id ?>">

    <div class="modal-body">
        <?php renderExportColumnPicker('asset_interfaces'); ?>
    </div>
    <div class="modal-footer">
        <?php renderExportButtons('export_asset_interfaces'); ?>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
